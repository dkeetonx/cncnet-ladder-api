<?php namespace App\Commands;

use App\Commands\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldBeQueued;
use Carbon\Carbon;
use App\QmMatch;
use App\QmMatchPlayer;

class FindOpponent extends Command implements SelfHandling, ShouldBeQueued {

	use InteractsWithQueue, SerializesModels;

    public $qmPlayerId = null;
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct($id)
	{
		//
        $this->qmPlayerId = $id;
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle()
	{
        $this->delete();
		//
        $qmPlayer = QmMatchPlayer::find($this->qmPlayerId);

        // A player could cancel out of queue before this function runs
        if ($qmPlayer === null)
        {
            //error_log("qmPlayer is null");
            return;
        }

        // Skip if the player has already been matched up
        if ($qmPlayer->qm_match_id !== null)
        {
            //error_log("qmPlayer->qm_match_id is not null");
            return;
        }

        $player = $qmPlayer->player;

        $rating = $player->rating->rating;

        // map_bitfield is an old and unused bit of code
        $qmPlayer->map_bitfield = 0xffffffff;

        // This will throw an exception when a not null field is null
        $qmPlayer->save();

        $ladder = $qmPlayer->ladder;
        $ladder_rules = $ladder->qmLadderRules;

        /* Try to find a matchup
         * Matchups are based on the player's rating,
         * The absolute value of the difference of me and every other player is calculated.
         * Any players whose difference is greater 100 is thrown out with some exceptions
         * If a player has been waiting a long time for a matchup he should get some special
         * treatment.  To allow for this, the player rating difference gets wait time, in
         * seconds, subtracted from it.
         * If 2 players rated 1200, and 1400 are the only players a match won't be made
         * until one player has been waiting for 100 seconds 1400-1200-100seconds = 100
         *
         * The ratio of seconds to points should be tunable TODO.
         */
        $phpNow = Carbon::now()->toDateTimeString();
        $qmOpns = QmMatchPlayer::where('qm_match_players.id', '<>', $qmPlayer->id)
                               ->where('waiting', true)
                               ->where('ladder_id', $qmPlayer->ladder_id)
                               ->whereRaw("map_bitfield & {$qmPlayer->map_bitfield} > 0")
                               ->whereNull('qm_match_id')
                               ->join('player_ratings', 'player_ratings.player_id', '=', 'qm_match_players.player_id')
                               ->selectRAW( "qm_match_players.id as id, waiting, qm_match_players.player_id as player_id,"
                                           ."ladder_id, map_bitfield, chosen_side, actual_side, ip_address, port, lan_ip"
                                           .",lan_port, ipv6_address, ipv6_port, color, location, qm_match_id, tunnel_id,"
                                           ."map_sides, qm_match_players.created_at as created_at"
                                           .", qm_match_players.updated_at as updated_at"
                                           .", ABS({$rating} - rating)"
                                           ." - ABS(TIMESTAMPDIFF(SECOND, qm_match_players.created_at, '{$phpNow}')) as importance")
                               ->having('importance', '<', $ladder_rules->max_difference)
                               ->orderBy('importance', 'asc')
                               ->get();

        $qmOpns = $qmOpns->shuffle();

        if ($qmOpns->count() >= $ladder_rules->player_count - 1)
        {
            // Randomly choose the opponents from the best matches. To prevent
            // long runs of identical matchups.
            $qmOpns = $qmOpns->shuffle()->take($ladder_rules->player_count - 1);
            // Randomly select a map
            $common_maps = array();

            $qmMaps = \App\QmMap::valid()->where('ladder_id', $qmPlayer->ladder_id)->get();
            foreach ($qmMaps as $qmMap)
            {
                $match = true;
                if (array_key_exists($qmMap->bit_idx, $qmPlayer->map_side_array())
                &&
                $qmPlayer->map_side_array()[$qmMap->bit_idx] > -2
                &&
                in_array($qmPlayer->map_side_array()[$qmMap->bit_idx], $qmMap->sides_array()))
                {
                    foreach ($qmOpns as $opn)
                    {
                        if (array_key_exists($qmMap->bit_idx, $opn->map_side_array())
                        &&
                        ($opn->map_side_array()[$qmMap->bit_idx] < -1
                        ||
                        !in_array($opn->map_side_array()[$qmMap->bit_idx], $qmMap->sides_array())))
                        {
                            $match = false;
                        }
                    }
                }
                else
                    $match = false;

                if ($match)
                    $common_maps[] = $qmMap;

            }
            if (count($common_maps) < 1)
            {
                $qmPlayer->touch();
                return;
            }

            $map_idx = mt_rand(0, count($common_maps) - 1);

            // Create the qm_matches db entry
            $qmMatch = new \App\QmMatch();
            $qmMatch->ladder_id = $qmPlayer->ladder_id;
            $qmMatch->qm_map_id = $common_maps[$map_idx]->id;
            $qmMatch->seed = mt_rand(-2147483647, 2147483647);

            // Create the Game
            $game = \App\Game::genQmEntry($qmMatch);
            $qmMatch->game_id = $game->id;
            $qmMatch->save();

            $game->qm_match_id = $qmMatch->id;
            $game->save();

            $qmMap = $qmMatch->map;
            $spawn_order = explode(',', $qmMap->spawn_order);

            // Set up player specific information
            // Color will be used for spawn location
            $qmPlayer->color = 0;
            $qmPlayer->location = $spawn_order[$qmPlayer->color] - 1;
            $qmPlayer->qm_match_id = $qmMatch->id;
            $qmPlayer->tunnel_id = $qmMatch->seed + $qmPlayer->color;

            $psides = explode(',', $qmPlayer->map_sides);

            if (count($psides) > $qmMap->bit_idx)
                $qmPlayer->actual_side = $psides[$qmMap->bit_idx];


            if ($qmPlayer->actual_side < -1)
            {
                $qmPlayer->actual_side = $qmPlayer->chosen_side;
            }

            $qmPlayer->save();

            $perMS = array_values(array_filter($qmMap->sides_array(), function($s) { return $s >= 0; }));
            $color = 1;
            foreach ($qmOpns as $opn)
            {
                $osides = explode(',', $opn->map_sides);

                if (count($osides) > $qmMap->bit_idx)
                    $opn->actual_side = $osides[$qmMap->bit_idx];

                if ($opn->actual_side  < -1)
                {
                    $opn->actual_side = $opn->chosen_side;
                }

                if ($opn->actual_side == -1)
                {
                    $opn->actual_side = $perMS[mt_rand(0, count($perMS) - 1)];
                }
                $opn->color = $color++;
                $opn->location = $spawn_order[$opn->color] - 1;
                $opn->qm_match_id = $qmMatch->id;
                $opn->tunnel_id = $qmMatch->seed + $opn->color;
                $opn->save();
            }

            if ($qmPlayer->actual_side == -1)
            {
                $qmPlayer->actual_side = $perMS[mt_rand(0, count($perMS) - 1)];
            }
            $qmPlayer->save();
        }
	}

}
