<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Services\AuthService;
use App\Http\Services\LadderService;
use App\Http\Services\PlayerService;
use App\Models\PlayerActiveHandle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use JWTAuth;
use Log;

class ApiUserController extends Controller
{
    private $authService;
    private $ladderService;
    private $playerService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->playerService = new PlayerService;
        $this->ladderService = new LadderService;
    }

    public function getAccount(Request $request)
    {
        $user = $request->user();

        foreach (\App\Models\Ladder::all() as $ladder)
        {
            $players = $user->usernames()->where('ladder_id', '=', $ladder->id)->get();

            if ($players->count() < 1)
            {
                // Auto-register a player for each ladder if there isn't already a player registered for this user
                $playerCreated = false;
                $oLadders = \App\Models\Ladder::where('abbreviation', '=', $ladder->abbreviation)
                    ->where('id', '<>', $ladder->id)
                    ->get();
                foreach ($oLadders as $other)
                {
                    $oPlayers = $other->players()->where('user_id', '=', $user->id)->get();
                    foreach ($oPlayers as $op)
                    {
                        $this->playerService->addPlayerToUser($op->username, $user, $ladder->id);
                        $playerCreated = true;
                    }
                }
                if (!$playerCreated)
                {
                    $this->playerService->addPlayerToUser($user->name, $user, $ladder->id);
                }
            }
        }
        return $this->getActivePlayerListV2($user);
    }

    private function getActivePlayerListV2($user)
    {
        $date = Carbon::now();
        $startOfMonth = $date->startOfMonth()->toDateTimeString();
        $endOfMonth = $date->endOfMonth()->toDateTimeString();

        $activeHandles = PlayerActiveHandle::getUserActiveHandles($user->id, $startOfMonth, $endOfMonth)->get();

        $players = [];
        foreach ($activeHandles as $activeHandle)
        {
            $player = $activeHandle->player;
            $player["ladder"] = \App\Models\Ladder::where('id', $player->ladder_id)->first();
            $players[] = $player;
        }

        // If they haven't selected a nickname yet
        // Get their last created

        if (count($players) == 0)
        {
            return $this->getTempNicks($user->id);
        }

        return $players;
    }

    /**
     * Returns a SINGLE nickname for all 3 ladder types
     */
    private function getTempNicks($userId)
    {
        $tempNicks = [];
        foreach (\App\Models\Ladder::all() as $ladder)
        {
            $tempNick = $this->getTempNickByLadderType($ladder->id, $userId);
            if ($tempNick != null)
            {
                $tempNicks[] = $tempNick;
            }
        }
        return $tempNicks;
    }

    /**
     * Limit nicknames to the expired date, one per ladder type
     */
    private function getTempNickByLadderType($ladderId, $userId)
    {
        // Get this months ladder
        $date = Carbon::now();
        $start = $date->startOfMonth()->toDateTimeString();
        $end = $date->endOfMonth()->toDateTimeString();

        $ladderHistory = \App\Models\LadderHistory::where("starts", "=", $start)
            ->where("ends", "=", $end)
            ->first();

        // Detect if the player is active in the this months ladder already
        $tempNick = \App\Models\PlayerHistory::join('players as p', 'p.id', '=', 'player_histories.player_id')
            ->where("player_histories.ladder_history_id", "=", $ladderHistory->id)
            ->where("user_id", $userId)
            ->where('ladder_id', $ladderId)
            ->select(["p.id", "p.username", "p.ladder_id", "p.card_id"])
            ->orderBy("id", "desc")
            ->first();

        // If they are, set their nick as the active handle
        if ($tempNick != null)
        {
            PlayerActiveHandle::setPlayerActiveHandle($ladderId, $tempNick->id, $userId);
            return $tempNick;
        }

        $limitLatestNickByDate = Carbon::create("2019", "09", "01");

        // Get nick last created limited by this new 1 nick rule
        $tempNick = \App\Models\Player::where("user_id", $userId)
            ->where("created_at", "<=", $limitLatestNickByDate)
            ->where('ladder_id', $ladderId)
            ->orderBy("id", "desc")
            ->first();

        return $tempNick;
    }
}
