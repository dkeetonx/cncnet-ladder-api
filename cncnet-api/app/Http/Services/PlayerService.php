<?php

namespace App\Http\Services;

use App\Ban;
use App\Ladder;
use App\Player;
use App\PlayerActiveHandle;
use App\PlayerRating;
use App\UserRating;
use Carbon\Carbon;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class PlayerService
{
    public function __construct()
    {
    }


    /**
     * Creates Player, PlayerActiveHandle if the username is not taken.
     * Returns null if username taken.
     * @param mixed $username 
     * @param mixed $user 
     * @param mixed $ladderId 
     * @return Player|null 
     */
    public function addPlayerToUser($username, $user, $ladderId)
    {
        $username = str_replace([",", ";", "="], "-", $username); // Dissallowed by qm client

        $player = \App\Player::where("username", "=", $username)
            ->where("ladder_id", "=", $ladderId)
            ->first();

        $ladder = Ladder::find($ladderId);

        if ($player == null)
        {
            $player = new \App\Player();
            $player->username = $username;
            $player->user_id = $user->id;
            $player->ladder_id = $ladderId;
            $player->save();

            $activeHandle = new \App\PlayerActiveHandle();
            $activeHandle->ladder_id = $ladderId;
            $activeHandle->player_id = $player->id;
            $activeHandle->user_id = $user->id;
            $activeHandle->save();

            return $player;
        }

        return null;
    }

    public function addPlayerToUserAccount($username, $user, $ladderId)
    {
        $username = str_replace([",", ";", "="], "-", $username); // Dissallowed by qm client

        $player = \App\Player::where("username", "=", $username)
            ->where("ladder_id", "=", $ladderId)
            ->first();

        $ladder = Ladder::find($ladderId);

        if ($player == null)
        {
            $player = new \App\Player();
            $player->username = $username;
            $player->user_id = $user->id;
            $player->ladder_id = $ladderId;
            $player->save();

            return $player;
        }

        return null;
    }

    public function findUserRatingByPlayerId($pid)
    {
        $player = Player::find($pid);
        $user = $player->user;
        $userRating = $user->getOrCreateLiveUserRating();

        return $userRating;
    }

    public function findPlayerByUsername($name, $ladder)
    {
        return \App\Player::where("username", "=", $name)
            ->where("ladder_id", "=", $ladder->id)
            ->first();
    }

    public function updatePlayerStats($player, $points, $won = false)
    {
        $player->points = $points;
        $player->games_count += 1;

        if ($won)
        {
            $player->win_count += 1;
            $player->points += $points;
        }
        else
        {
            $player->loss_count = $player->loss_count > 0 ? $player->loss_count -= 1 : 0;
            $player->points = $player->points > 0 ? $player->points -= $points : 0;
        }

        $player->save();
    }

    public function getEloKvalue($users)
    {
        // For players with less than 10 games, K will be 32, otherwise 16
        foreach ($users as $userRating)
        {
            if ($userRating->rated_games < 10)
            {
                return 32;
            }
        }
        return 16;
    }

    public function updateUserRating($playerID, $newRating)
    {
        $userRating = $this->findUserRatingByPlayerId($playerID);
        if ($newRating > $userRating->peak_rating)
        {
            $userRating->peak_rating = $newRating;
        }

        $userRating->rating = $newRating;
        $userRating->rated_games = $userRating->rated_games + 1;
        $userRating->save();
    }

    public function setActiveUsername($player, $ladder)
    {
        $date = Carbon::now();
        $startOfMonth = $date->startOfMonth()->toDateTimeString();
        $endOfMonth = $date->endOfMonth()->toDateTimeString();

        // Player checks - ensure nick is registered as an active handle
        $hasActiveHandle = PlayerActiveHandle::getPlayerActiveHandle($player->id, $ladder->id, $startOfMonth, $endOfMonth);
        if ($hasActiveHandle == null)
        {
            PlayerActiveHandle::setPlayerActiveHandle($ladder->id, $player->id, $player->user->id);
        }
    }

    public function checkPlayerForBans($player, $ip)
    {
        $ban = $player->user->getBan(true);
        if ($ban !== null)
        {
            return $ban;
        }

        $ban = \App\IpAddress::findByIP($ip)->getBan(true);
        if ($ban !== null)
        {
            return $ban;
        }

        return null;
    }

    public function checkPlayerHasVerifiedEmail($player)
    {
        if (!$player->user->email_verified)
        {
            if (!$player->user->verificationSent())
            {
                $player->user->sendNewVerification();
            }

            return false;
        }

        return true;
    }

    public function createPlayerRatingIfNull($player)
    {
        $rating = $player->rating()->first();
        if ($rating == null)
        {
            $playerRating = new PlayerRating();
            $playerRating->player_id = $player->id;
            $playerRating->save();
        }
        return $rating;
    }
}
