<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Http\Services\LadderService;
use \App\Http\Services\AdminService;
use \Carbon\Carbon;
use \App\User;

class AdminController extends Controller
{
    private $ladderService;
    private $adminService;

    public function __construct()
    {
        $this->ladderService = new LadderService();
        $this->adminService = new AdminService();
    }

    public function getAdminIndex(Request $request)
    {
        return view("admin.index", ["ladders" => $this->ladderService->getLatestLadders(),
                                    "all_ladders" => \App\Ladder::all()]);
    }

    public function getLadderSetupIndex(Request $request, $ladderId = null)
    {
        $ladder = \App\Ladder::find($ladderId);

        if ($ladder === null)
            return null;

        $ladders = $this->ladderService->getLatestLadders();
        $rule = $ladder->qmLadderRules;
        $qmMaps = $ladder->qmMaps()->valid()->orderby('bit_idx', 'ASC')->get();
        $maps = \App\Map::orderby('name', 'ASC')->get();
        $user = $request->user();

        return view("admin.ladder-setup", compact('ladders', 'ladder', 'rule', 'qmMaps', 'maps', 'user'));
    }

    public function postLadderSetupRules(Request $request)
    {
        return $this->adminService->saveQMLadderRulesRequest($request);
    }

    public function getManageUsersIndex(Request $request)
    {
        return view("admin.manage-users", ["ladders" => $this->ladderService->getLatestLadders()]);
    }

    public function getManageGameIndex(Request $request, $cncnetGame = null)
    {
        $ladder = \App\Ladder::where("abbreviation", "=", $cncnetGame)->first();

        if ($ladder == null)
            return "No ladder";

        $date = Carbon::now();
        $start = $date->startOfMonth()->toDateTimeString();
        $end = $date->endOfMonth()->toDateTimeString();

        $history = \App\LadderHistory::where("starts", "=", $start)
            ->where("ends", "=", $end)
            ->where("ladder_id", "=", $ladder->id)
            ->first();

        $games = \App\Game::where("ladder_history_id", "=", $history->id)->orderBy("id", "DESC")->limit(100);
        return view("admin.manage-games", ["games" => $games, "ladder" => $ladder, "history" => $history]);
    }

    public function deleteGame(Request $request)
    {
        $game = \App\Game::find($request->game_id);
        if ($game == null) return "Game not found";

        // Just remove the game_report_id linkage rather than actually delete anything
        $game->game_report_id = null;
        $game->save;

        return redirect()->back();
    }

    public function switchGameReport(Request $request)
    {
        $game = \App\Game::find($request->game_id);
        if ($game === null) return "Game not found";

        $gameReport = $game->allReports()->find($request->game_report_id);
        if ($gameReport === null) return "Game Report not found";

        $currentReport = $game->report()->first();
        if ($currentReport !== null)
        {
            $currentReport->best_report = false;
            $currentReport->save();
        }

        $game->game_report_id = $request->game_report_id;
        $game->save();

        $gameReport->best_report = true;
        $gameReport->save();

        return redirect()->back();
    }
    public function washGame(Request $request)
    {
        $game = \App\Game::find($request->game_id);
        if ($game === null) return "Game not found";

        $gameReport = $game->report()->first();
        if ($gameReport === null) return "Game Report not found";

        $gameReport->best_report = false;

        $wash = new \App\GameReport();
        $wash->game_id = $gameReport->game_id;
        $wash->player_id = $gameReport->player_id;
        $wash->best_report = true;
        $wash->manual_report = true;
        $wash->duration = $gameReport->duration;
        $wash->valid = true;
        $wash->finished = false;
        $wash->fps = $gameReport->fps;
        $wash->oos = false;
        $wash->save();

        $game->game_report_id = $wash->id;
        $game->save();
        $gameReport->save();

        return redirect()->back();
    }

    public function postQuickMatchMap(Request $request)
    {
        if ($request->id == "new")
        {
            $qmMap = new \App\QmMap;
            $message = "Successfully created new map";
        }
        else {
            $qmMap = \App\QmMap::where('id', $request->id)->first();
            $message = "Sucessfully updated map";
        }

        if ($qmMap == null)
        {
            $request->session()->flash('error', 'Unable to update Map');
            return redirect()->back();
        }

        $qmMap->ladder_id = $request->ladder_id;
        $qmMap->map_id = $request->map_id;
        $qmMap->description = $request->description;
        $qmMap->admin_description = $request->admin_description;
        $qmMap->bit_idx = $request->bit_idx;
        $qmMap->valid = $request->valid;
        $qmMap->allowed_sides = implode(",", $request->allowed_sides);
        $qmMap->game_mode = $request->game_mode;
        $qmMap->spawn_order = $request->spawn_order;
        $qmMap->speed = $request->speed;
        $qmMap->credits = $request->credits;
        $qmMap->units = $request->units;
        $qmMap->tech = $request->tech;
        $qmMap->bases = ini_to_b($request->bases);
        $qmMap->short_game = ini_to_b($request->short_game);
        $qmMap->fog = ini_to_b($request->fog);
        $qmMap->redeploy = ini_to_b($request->redeploy);
        $qmMap->crates = ini_to_b($request->crates);
        $qmMap->multi_eng = ini_to_b($request->multi_eng);
        $qmMap->multi_factory = ini_to_b($request->multi_factory);
        $qmMap->allies = ini_to_b($request->allies);
        $qmMap->dog_kill = ini_to_b($request->dog_kill);
        $qmMap->bridges = ini_to_b($request->bridges);
        $qmMap->supers = ini_to_b($request->supers);
        $qmMap->build_ally = ini_to_b($request->build_ally);
        $qmMap->spawn_preview = ini_to_b($request->spawn_preview);
        $qmMap->firestorm = ini_to_b($request->firestorm);
        $qmMap->ra2_mode = ini_to_b($request->ra2_mode);
        $qmMap->aimable_sams = ini_to_b($request->aimable_sams);
        $qmMap->attack_neutral = ini_to_b($request->attack_neutral);
        $qmMap->harv_truce = ini_to_b($request->harv_truce);
        $qmMap->ore_regenerates = ini_to_b($request->ore_regenerates);
        $qmMap->aftermath = ini_to_b($request->aftermath);
        $qmMap->fix_ai_ally = ini_to_b($request->fix_ai_ally);
        $qmMap->ally_reveal = ini_to_b($request->ally_reveal);
        $qmMap->am_fast_build = ini_to_b($request->am_fast_build);
        $qmMap->parabombs = ini_to_b($request->parabombs);
        $qmMap->fix_formation_speed = ini_to_b($request->fix_formation_speed);
        $qmMap->fix_magic_build = ini_to_b($request->fix_magic_build);
        $qmMap->fix_range_exploit = ini_to_b($request->fix_range_exploit);
        $qmMap->super_tesla_fix = ini_to_b($request->super_tesla_fix);
        $qmMap->forced_alliances = ini_to_b($request->forced_alliances);
        $qmMap->tech_center_fix = ini_to_b($request->tech_center_fix);
        $qmMap->no_screen_shake = ini_to_b($request->no_screen_shake);
        $qmMap->no_tesla_delay = ini_to_b($request->no_tesla_delay);
        $qmMap->dead_player_radar = ini_to_b($request->dead_player_radar);
        $qmMap->capture_flag = ini_to_b($request->capture_flag);
        $qmMap->slow_unit_build = ini_to_b($request->slow_unit_build);
        $qmMap->shroud_regrows = ini_to_b($request->shroud_regrows);
        $qmMap->ai_player_count = $request->ai_player_count;
        $qmMap->save();

        $request->session()->flash('success', $message);
        return redirect()->back();
    }

    public function remSide(Request $request, $ladderId = null)
    {
        $ladder = \App\Ladder::find($ladderId);

        if ($ladder === null || $ladderId === null)
        {
            $request->session()->flash('error', "Ladder not found");
            return redirect()->back();
        }

        $side = $ladder->sides()->where('local_id', '=', $request->local_id)->first();
        if ($side === null)
        {
            $request->session()->flash('error', 'Side not found');
            return redirect()->back();
        }
        $side->delete();

        $request->session()->flash('success', 'Side deleted');
        return redirect()->back();
    }

    public function addSide(Request $request, $ladderId = null)
    {
        $ladder = \App\Ladder::find($ladderId);

        if ($ladder === null || $ladderId === null)
        {
            $request->session()->flash('error', "Ladder not found");
            return redirect()->back();
        }

        $side = $ladder->sides()->where('local_id', '=', $request->local_id)->first();
        if ($side === null)
        {
            $side = new \App\Side;
            $side->ladder_id = $ladder->id;
            $side->local_id = $request->local_id;
        }
        $side->name = $request->name;
        $side->save();
        $request->session()->flash('success', "Side has been added or updated");

        return redirect()->back();
    }

    public function removeQuickMatchMap(Request $request)
    {
        $qmMap = \App\QmMap::find($request->map_id);

        if ($qmMap !== null)
        {
            \App\QmMap::valid()
                      ->where('ladder_id', '=', $request->ladder_id)
                      ->where('bit_idx', '>', $qmMap->bit_idx)
                      ->decrement('bit_idx');
            $qmMap->valid = false;
            $qmMap->save();
        }

        $request->session()->flash('success', "Map Deleted");
        return redirect()->back();
    }

    public function moveDownQuickMatchMap(Request $request, $mapId)
    {
        $qmMap = \App\QmMap::find($mapId);
        if ($qmMap !== null)
        {
            if ($qmMap->bit_idx < 31)
            {
                $mapBelow = \App\QmMap::valid()
                                      ->where('ladder_id', '=', $qmMap->ladder_id)
                                      ->where('bit_idx', '=', $qmMap->bit_idx + 1)
                                      ->first();
                $mapBelow->bit_idx--;
                $mapBelow->save();

                $qmMap->bit_idx++;
                $qmMap->save();
            }
        }
        $request->session()->flash('success', "Map Moved");
        return redirect()->back();
    }

    public function moveUpQuickMatchMap(Request $request, $mapId)
    {
        $qmMap = \App\QmMap::find($mapId);
        if ($qmMap !== null)
        {
            if ($qmMap->bit_idx > 0)
            {
                $mapAbove = \App\QmMap::where('valid', true)
                                      ->where('ladder_id', '=', $qmMap->ladder_id)
                                      ->where('bit_idx', '=', $qmMap->bit_idx - 1)
                                      ->first();
                $mapAbove->bit_idx++;
                $mapAbove->save();

                $qmMap->bit_idx--;
                $qmMap->save();
            }
        }
        $request->session()->flash('success', "Map Moved");
        return redirect()->back();
    }

    public function addAdmin(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        $user = \App\User::where('email', '=', $request->email)->first();
        if ($request->email === null || $user == null)
        {
            $request->session()->flash('error', "Unable to add the user as admin");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::findOrCreate($user->id, $ladderId);

        $ladderAdmin->admin = true;
        $ladderAdmin->moderator = true;
        $ladderAdmin->tester = true;
        $ladderAdmin->save();

        $request->session()->flash('success', "The user {$user->email} has been made promoted to admin");
        return redirect()->back();
    }

    public function removeAdmin(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        if ($request->ladder_admin_id === null)
        {
            $request->session()->flash('error', "Unable to remove the user");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::find($request->ladder_admin_id);
        if ($ladderAdmin === null)
        {
            $request->session()->flash('error', "Unable to find the requested admin");
            return redirect()->back();
        }

        $ladderAdmin->admin = false;
        $ladderAdmin->moderator = false;
        $ladderAdmin->tester = false;
        $ladderAdmin->save();

        $request->session()->flash('success', "The admin {$ladderAdmin->user->email} has been removed");
        return redirect()->back();
    }

    public function addModerator(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        $user = \App\User::where('email', '=', $request->email)->first();
        if ($request->email === null || $user == null)
        {
            $request->session()->flash('error', "Unable to add the user as moderator");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::findOrCreate($user->id, $ladderId);

        $ladderAdmin->admin = false;
        $ladderAdmin->moderator = true;
        $ladderAdmin->tester = true;
        $ladderAdmin->save();

        $request->session()->flash('success', "The user {$user->email} has been made promoted to moderator");
        return redirect()->back();
    }

    public function removeModerator(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        if ($request->ladder_admin_id === null)
        {
            $request->session()->flash('error', "Unable to remove the user");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::find($request->ladder_admin_id);
        if ($ladderAdmin === null)
        {
            $request->session()->flash('error', "Unable to find the requested moderator");
            return redirect()->back();
        }

        $ladderAdmin->admin = false;
        $ladderAdmin->moderator = false;
        $ladderAdmin->tester = false;
        $ladderAdmin->save();

        $request->session()->flash('success', "The moderator {$ladderAdmin->user->email} has been removed");
        return redirect()->back();
    }

    public function addTester(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        $user = \App\User::where('email', '=', $request->email)->first();
        if ($request->email === null || $user == null)
        {
            $request->session()->flash('error', "Unable to add the user as tester");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::findOrCreate($user->id, $ladderId);

        $ladderAdmin->admin = false;
        $ladderAdmin->moderator = false;
        $ladderAdmin->tester = true;
        $ladderAdmin->save();

        $request->session()->flash('success', "The user {$user->email} has been made promoted to tester");
        return redirect()->back();
    }

    public function removeTester(Request $request, $ladderId = null)
    {
        if ($ladderId === null)
        {
            $request->session()->flash('error', "No ladder specified");
        }

        if ($request->ladder_admin_id === null)
        {
            $request->session()->flash('error', "Unable to remove the user");
            return redirect()->back();
        }

        $ladderAdmin = \App\LadderAdmin::find($request->ladder_admin_id);
        if ($ladderAdmin === null)
        {
            $request->session()->flash('error', "Unable to find the requested tester");
            return redirect()->back();
        }

        $ladderAdmin->admin = false;
        $ladderAdmin->moderator = false;
        $ladderAdmin->tester = false;
        $ladderAdmin->save();

        $request->session()->flash('success', "The tester {$ladderAdmin->user->email} has been removed");
        return redirect()->back();
    }

    public function getLadderPlayer(Request $request, $ladderId = null, $playerId = null)
    {
        $mod = $request->user();
        if ($playerId === null)
            return;

        $player = \App\Player::find($playerId);

        if ($player === null || !$mod->isLadderMod($player->ladder))
            return;

        $user = $player->user;

        $ladderService = new LadderService;
        $history = $ladderService->getActiveLadderByDate(Carbon::now()->format('m-Y'), $player->ladder->game);

        return view("admin.moderate-player",
                    [ "mod"    => $mod,
                      "player" => $player,
                      "user"   => $user,
                      "bans"   => $user->bans()->orderBy('created_at', 'DESC')->get(),
                      "ladder" => $player->ladder,
                      "history" => $history
                    ]);
    }

    public function getUserBan(Request $request, $ladderId = null, $playerId = null, $banType = 0)
    {
        $mod = $request->user();
        if ($playerId === null)
            return;

        $player = \App\Player::find($playerId);

        if ($player === null || !$mod->isLadderMod($player->ladder))
            return;

        $user = $player->user;

        return view("admin.edit-ban",
                    [ "mod"    => $mod,
                      "player" => $player,
                      "user"   => $user,
                      "ladder" => $player->ladder,
                      "id" => null,
                      "expires" => null,
                      "admin_id" => $mod->id,
                      "user_id" => $user->id,
                      "ban_type" => $banType,
                      "internal_note" => "",
                      "plubic_reason" => "",
                      "ip_address_id" => $user->ip->id,
                      "start_or_end" => false,
                      "banDesc" => \App\Ban::typeToDescription($banType) ." - ". \App\Ban::banStyle($banType)
                    ]);
    }

    public function editUserBan(Request $request, $ladderId = null, $playerId = null, $banId = null)
    {
        $mod = $request->user();
        if ($playerId === null)
            return;

        $player = \App\Player::find($playerId);

        if ($player === null || !$mod->isLadderMod($player->ladder))
            return;

        $ban = \App\Ban::find($banId);
        if ($player === null)
            return;

        $user = $player->user;

        return view("admin.edit-ban",
                    [ "mod"    => $mod,
                      "player" => $player,
                      "user"   => $user,
                      "ladder" => $player->ladder,
                      "id" => $ban->id,
                      "expires" => $ban->expires->eq(\App\Ban::unstartedBanTime()) ? null : $ban->expires,
                      "admin_id" => $mod->id,
                      "user_id" => $user->id,
                      "ban_type" => $ban->ban_type,
                      "internal_note" => $ban->internal_note,
                      "plubic_reason" => $ban->plubic_reason,
                      "ip_address_id" => $ban->ip_address_id,
                      "start_or_end" => false,
                      "banDesc" => \App\Ban::typeToDescription($ban->ban_type) ." - ". \App\Ban::banStyle($ban->ban_type)
                    ]);
    }

    public function saveUserBan(Request $request, $ladderId = null, $playerId = null, $banId = null)
    {
        $mod = $request->user();
        if ($playerId === null)
            return;
        $player = \App\Player::find($playerId);

        if ($player === null || !$mod->isLadderMod($player->ladder))
            return;

        if ($player === null)
            return;

        $user = $player->user;

        $ban = \App\Ban::find($banId);
        if ($ban === null)
        {
            $ban = new \App\Ban;
        }

        foreach ($ban->fillable as $col)
        {
            if ($request->has($col))
            {
                $ban[$col] = $request[$col];
            }
        }
        $ban->save();

        $banFlash = \App\Ban::banStyle($request->ban_type);

        if ($request->start_or_end)
        {
            if ($ban->expires !== null && $ban->expires->gt(Carbon::now()))
            {
                $ban->expires = Carbon::now();
                $banFlash = "has ended.";
            }
            else if ($ban->expires === null || $ban->expires->eq(\App\Ban::unstartedBanTime()))
            {
                $ban->checkStartBan(true);
                $banFlash = "has started.";
            }
            else
            {
                $ban->checkStartBan(false);
            }
        }
        else
            $ban->checkStartBan(false);

        $ban->save();

        $request->session()->flash('success', "Ban ". $banFlash);
        return redirect()->action('AdminController@getLadderPlayer', ['ladderId' => $ladderId, 'playerId' => $playerId]);
    }
}

function ini_to_b($string)
{
    if ($string == "Null") return null;
    if ($string == "Random") return -1;
    return $string == "Yes" ? true : false;
}
