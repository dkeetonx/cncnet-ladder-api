<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Http\Services\LadderService;
use \App\Http\Services\GameService;
use \App\Http\Services\PlayerService;
use \App\Http\Services\PointService;
use \App\Http\Services\AuthService;
use \Carbon\Carbon;
use DB;
use Log;
use \App\Commands\FindOpponent;

class ApiQuickMatchController extends Controller
{
    private $ladderService;
    private $gameService;
    private $playerService;
    private $pointService;

    public function __construct()
    {
        $this->ladderService = new LadderService();
        $this->gameService = new GameService();
        $this->playerService = new PlayerService();
    }

    public function clientVersion(Request $request, $platform = null)
    {
        return json_encode(DB::table("client_version")->where("platform", $platform)->first());
    }

    public function statsRequest(Request $request, $ladderAbbrev = null)
    {
        $timediff = Carbon::now()->subHour()->toDateTimeString();
        $ladder_id = $this->ladderService->getLadderByGame($ladderAbbrev)->id;
        $recentMatchedPlayers = \App\QmMatchPlayer::where('created_at', '>', $timediff)
                                                  ->where('ladder_id', '=', $ladder_id)
                                                  ->count();
        $queuedPlayers = \App\QmMatchPlayer::where('ladder_id', '=', $ladder_id)->whereNull('qm_match_id')->count();
        $recentMatches = \App\QmMatch::where('created_at', '>', $timediff)
                                     ->where('ladder_id', '=', $ladder_id)
                                     //->where('status', 'like', '%GameSpawned%')
                                     ->count();

        return ['recentMatchedPlayers' => $recentMatchedPlayers,
                'queuedPlayers' => $queuedPlayers,
                'recentMatches' => $recentMatches ];
    }

    public function mapListRequest(Request $request, $ladderAbbrev = null)
    {
        //$qmMaps = \App\QmMap::where('ladder_id', $this->ladderService->getLadderByGame($ladderAbbrev)->id)->get();
        return \App\QmMap::findMapsByLadder($this->ladderService->getLadderByGame($ladderAbbrev)->id);
    }

    public function sidesListRequest(Request $request, $ladderAbbrev = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);
        $rules = $ladder->qmLadderRules()->first();
        $allowed_sides = $rules->allowed_sides();
        $sides = $ladder->sides()->get();

        return $sides->filter(function ($side) use(&$allowed_sides)
                              {
                                  return in_array($side->local_id, $allowed_sides);
                              } );
    }

    public function matchRequest(Request $request, $ladderAbbrev = null, $playerName = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);
        $ladder_rules = $ladder->qmLadderRules()->first();

        $check = $this->ladderService->checkPlayer($request, $playerName, $ladder);
        if ($check !== null)
        {
            return $check;
        }
        $player = $this->playerService->findPlayerByUsername($playerName, $ladder);

        if ($player == null)
        {
            return array("type"=>"fail", "description" => "$playerName is not registered in $ladderAbbrev");
        }

        $ban = $player->user->getBan(true);
        if ($ban !== null)
        {
            return ['type' => 'fatal', 'message' => $ban ];
        }

        $ban = \App\IpAddress::findByIP($request->getClientIp())->getBan(true);
        if ($ban !== null)
        {
            return ['type' => 'fatal', 'message' => $ban ];
        }

        $rating = $player->rating()->first()->rating;

        $qmPlayer = \App\QmMatchPlayer::where('player_id', $player->id)
                                      ->where('waiting', true)->first();

        switch ($request->type ) {
        case "quit":
            if ($qmPlayer != null)
            {
                if ($qmPlayer->qm_match_id !== null)
                {
                    $qmPlayer->qmMatch->save();
                }
                $qmPlayer->delete();
            }
            return array("type" => "quit");
            break;

        case "update":

            if ($request->seed)
            {
                $qmMatch = \App\QmMatch::where('seed', '=', $request->seed)
                                       ->join('qm_match_players', 'qm_match_id', '=', 'qm_matches.id')
                                       ->where('qm_match_players.player_id', '=', $player->id)
                                       ->select('qm_matches.*')->first();
                if ($qmMatch !== null)
                {
                    switch($request->status)
                    {
                    case 'touch':
                        $qmMatch->touch();
                        break;
                    default:
                        $qmState = new \App\QmMatchState;
                        $qmState->player_id = $player->id;
                        $qmState->qm_match_id = $qmMatch->id;
                        $qmState->state_type_id = \App\StateType::findByName($request->status)->id;
                        $qmState->save();
                        break;
                    }
                    $qmMatch->save();
                    return ["message"  => "update qm match: ".$request->status ];
                }
            }
            return ["type" => "update"];
            break;

        case "match me up":
            // Deprecate older versions
            if ($request->version  < 1.48)
            {
                return array("type" => "fatal",
                             "message" => "Quick Match Version {$request->version} is no longer supported.\n".
                                          "Please restart the client to get the latest updates.");
            }

            /* This matchup system is restful, a player will have to check in to see if there
             * is a matchup waitin.
             * If there is already a matchup then all these top level ifs will fall through
             * and the game info will be sent.
             * Else we'll try to set up a match.
             */
            if ($qmPlayer == null)
            {
                $qmPlayer = new \App\QmMatchPlayer();
                $qmPlayer->player_id = $player->id;
                $qmPlayer->ladder_id = $player->ladder_id;
                $qmPlayer->map_bitfield = $request->map_bitfield;
                $qmPlayer->waiting = true;

                // color, chosen_side, actual_side and saving is done in the next if-statement
                $qmPlayer->qm_match_id = null;
                $qmPlayer->tunnel_id = null;

                $qmPlayer->ip_address = $request->ip_address;
                $qmPlayer->port = $request->ip_port;

                $qmPlayer->lan_ip = $request->lan_ip;
                $qmPlayer->lan_port = $request->lan_port;

                $qmPlayer->ipv6_address = $request->ipv6_address;
                $qmPlayer->ipv6_port = $request->ipv6_port;

                $qmPlayer->chosen_side = $request->side;

                $all_sides = $ladder_rules->all_sides();
                $sides = $ladder_rules->allowed_sides();

                if ($request->side == -1)
                {
                    $qmPlayer->actual_side = $all_sides[rand(0, count($all_sides) - 1)];
                }
                else if (in_array($request->side, $sides))
                {
                    $qmPlayer->actual_side = $request->side;
                }
                else {
                    return array("type" => "error", "description" => "Side ({$request->side}) is not allowed");
                }
                if ($request->map_sides)
                    $qmPlayer->map_sides = join(',', $request->map_sides);

                if ($request->version && $request->platform)
                {
                    $qmPlayer->version = $request->version;
                    $qmPlayer->platform = $request->platform;
                }
            }

            if ($request->ai_dat)
                $qmPlayer->ai_dat = $request->ai_dat;

            $qmPlayer->save();

            if ($qmPlayer->qm_match_id === null)
            {
                // Push a job to find an opponent
                 $this->dispatch(new FindOpponent($qmPlayer->id));

                $qmPlayer->touch();
                return array("type" => "please wait", "checkback" => 5, "no_sooner_than" => 1);
            }
            // If we've made it this far, lets send the spawn details

            $spawnStruct = array("type" => "spawn");
            $qmPlayer->waiting = false;
            $qmMatch = \App\QmMatch::find($qmPlayer->qm_match_id);
            $spawnStruct["gameID"] = $qmMatch->game_id;
            $qmMap = $qmMatch->map;
            $map = $qmMap->map;

            $spawnStruct["spawn"]["SpawnLocations"] = array();

            srand($qmMatch->seed); // Seed the RNG for possibly random boolean options

            $spawnStruct["spawn"]["Settings"] = array_filter(
                [  "UIGameMode" =>     $qmMap->game_mode
                  ,"UIMapName" =>      $qmMap->description
                  ,"MapHash" =>        $map->hash
                  ,"GameSpeed" =>      $qmMap->speed
                  ,"Seed" =>           $qmMatch->seed
                  ,"GameID" =>         $qmMatch->seed
                  ,"WOLGameID" =>      $qmMatch->seed
                  ,"Credits" =>        $qmMap->credits
                  ,"UnitCount" =>      $qmMap->units
                  ,"TechLevel" =>      $qmMap->tech
                  ,"Host" =>           "No"
                  ,"IsSpectator" =>    "No"
                  ,"AIPlayers" =>      0
                  ,"Name" =>           $qmPlayer->player()->first()->username
                  ,"Port" =>           $qmPlayer->port
                  ,"Side" =>           $qmPlayer->actual_side
                  ,"Color" =>          $qmPlayer->color
                  ,"Firestorm" =>      b_to_ini($qmMap->firestorm)
                  ,"Ra2Mode" =>        b_to_ini($qmMap->ra2_mode)
                  ,"ShortGame" =>      b_to_ini($qmMap->short_game)
                  ,"MultiEngineer" =>  b_to_ini($qmMap->multi_eng)
                  ,"MCVRedeploy" =>    b_to_ini($qmMap->redeploy)
                  ,"Crates" =>         b_to_ini($qmMap->crates)
                  ,"Bases" =>          b_to_ini($qmMap->bases)
                  ,"HarvesterTruce" => b_to_ini($qmMap->harv_truce)
                  ,"AlliesAllowed" =>  b_to_ini($qmMap->allies)
                  ,"BridgeDestroy" =>  b_to_ini($qmMap->bridges)
                  ,"FogOfWar" =>       b_to_ini($qmMap->fog)
                  ,"BuildOffAlly" =>   b_to_ini($qmMap->build_ally)
                  ,"MultipleFactory"=> b_to_ini($qmMap->mutli_factory)
                  ,"AimableSams" =>    b_to_ini($qmMap->aimable_sams)
                  ,"AttackNeutralUnits" => b_to_ini($qmMap->attack_neutral)
                  ,"Superweapons" =>   b_to_ini($qmMap->supers)
                  ,"OreRegenerates" => b_to_ini($qmMap->ore_regenerates)
                  ,"Aftermath" =>      b_to_ini($qmMap->aftermath)
                  ,"FixAIAlly"    =>   b_to_ini($qmMap->fix_ai_ally)
                  ,"AllyReveal"=>      b_to_ini($qmMap->ally_reveal)
                  ,"AftermathFastBuildSpeed" =>   b_to_ini($qmMap->am_fast_build)
                  ,"ParabombsInMultiplayer" =>   b_to_ini($qmMap->parabombs)
                  ,"FixFormationSpeed" =>   b_to_ini($qmMap->fix_formation_speed)
                  ,"FixMagicBuild"   =>   b_to_ini($qmMap->fix_magic_build)
                  ,"FixRangeExploit" =>   b_to_ini($qmMap->fix_range_exploit)
                  ,"SuperTeslaFix"   =>   b_to_ini($qmMap->super_tesla_fix)
                  ,"ForcedAlliances" =>   b_to_ini($qmMap->forced_alliances)
                  ,"TechCenterBugFix"=>   b_to_ini($qmMap->tech_center_fix)
                  ,"NoScreenShake"   =>   b_to_ini($qmMap->no_screen_shake)
                  ,"NoTeslaZapEffectDelay"=>   b_to_ini($qmMap->no_tesla_delay)
                  ,"DeadPlayersRadar"=>   b_to_ini($qmMap->dead_player_radar)
                  ,"CaptureTheFlag"  =>   $qmMap->capture_flag
                  ,"SlowUnitBuild"   =>   $qmMap->slow_unit_build
                  ,"ShroudRegrows"   =>   $qmMap->shroud_regrows
                  ,"AIPlayers"       =>   $qmMap->ai_player_count
                  ,"Tournament"      =>   1
                   // Filter null values
            ], function($var){return !is_null($var);} );


            if ($ladder_rules->ladder()->first()->abbreviation == "ra")
            {
                $spawnStruct["spawn"]["Settings"]["Bases"] = $spawnStruct["spawn"]["Settings"]["Bases"] == "Yes" ? 1 : 0;
                $spawnStruct["spawn"]["Settings"]["OreRegenerates"] = $spawnStruct["spawn"]["Settings"]["OreRegenerates"] == "Yes" ? 1 : 0;
            }

            // Write the Others sections

            $allPlayers = $qmMatch->players()->where('id', '<>', $qmPlayer->id)->orderBy('color', 'ASC')->get();
            $other_idx = 1;

            $multi_idx = $qmPlayer->color + 1;
            $spawnStruct["spawn"]["SpawnLocations"]["Multi{$multi_idx}"] = $qmPlayer->location;

            foreach ($allPlayers as $opn)
            {
                $spawnStruct["spawn"]["Other{$other_idx}"] = [
                    "Name" => $opn->player()->first()->username,
                    "Side" => $opn->actual_side,
                    "Color" => $opn->color,
                    "Ip" => $opn->ip_address,
                    "Port" => $opn->port,
                    "IPv6" => $opn->ipv6_address,
                    "PortV6" => $opn->ipv6_port,
                    "LanIP" => $opn->lan_ip,
                    "LanPort" => $opn->lan_port
                ];
                $multi_idx = $opn->color + 1;
                $spawnStruct["spawn"]["SpawnLocations"]["Multi{$multi_idx}"] = $opn->location;
                $other_idx++;
            }
            $qmPlayer->waiting = false;
            $qmPlayer->save();

            return $spawnStruct;
            break;
        default:
            return array("type" => "error", "description" => "unknown type: {$request->type}");
            break;
        }
    }
}

function b_to_ini($bool)
{
    if ($bool === null) return $bool;
    if ($bool == -1) return rand(0,1) ? "Yes" : "No"; // Pray the seed was set earlier or this will cause recons
    return $bool ? "Yes" : "No";
}
