@php
    $countryName = '';
    $side = null;
    
    if ($game == 'yr') {
        $side = \App\Side::where('local_id', $playerCache->country)
            ->where('ladder_id', $history->ladder->id)
            ->first();
    } else {
        if ($playerCache->side !== null) {
            if (array_key_exists($playerCache->side, $sides)) {
                $countryName = $sides[$playerCache->side];
            }
        }
    }
    
    if ($side !== null) {
        $countryName = $side->name;
    }
@endphp

<div class="player-row rank-{{ $rank }}">
    <div class="player-profile visible-xs">
        <div class="player-rank player-stat">
            #{{ $rank or 'Unranked' }}
        </div>
        <a class="player-avatar player-stat" href="{{ $url }}" title="Go to {{ $username }}'s profile">
            @include('components.avatar', ['avatar' => $avatar, 'size' => 50])
        </a>
        <a class="player-username player-stat" href="{{ $url }}" title="Go to {{ $username }}'s profile">
            {{ $username or '' }}
        </a>
    </div>

    <div class="player-profile hidden-xs">
        <div class="player-rank player-stat">
            #{{ $rank or 'Unranked' }}
        </div>

        <a class="player-avatar player-stat hidden-xs" href="{{ $url }}" title="Go to {{ $username }}'s profile">
            @include('components.avatar', ['avatar' => $avatar, 'size' => 50])
        </a>

        <a class="player-country player-stat" href="{{ $url }}" title="Go to {{ $username }}'s profile">
            @if ($countryName)
                <div class="most-used-country country-{{ $game }}-{{ strtolower($countryName) }}"></div>
            @endif
        </a>
        <a class="player-username player-stat hidden-xs" href="{{ $url }}" title="Go to {{ $username }}'s profile">
            {{ $username or '' }}
        </a>
    </div>

    <div class="player-profile-info">
        <div class="player-social">
            @if ($twitch)
                <a href="{{ $twitch }}"><i class="fa fa-twitch fa-lg"></i></a>
            @endif
            @if ($youtube)
                <a href="{{ $youtube }}"><i class="fa fa-youtube fa-lg"></i></a>
            @endif
            {{-- @if ($discord)
            <a href=" {{ $discord }}"><i class="fa fa-discord"></i></a>
            @endif --}}
        </div>
        <div class="player-points player-stat">{{ $points }} <span>points</span></div>
        <div class="player-wins player-stat">{{ $wins }} <span>won</span></div>
        <div class="player-losses player-stat">{{ $losses }} <span>lost</span></div>
        <div class="player-games player-stat">{{ $totalGames }} <span>games</span></div>
    </div>
    <a href="{{ $url }}" class="player-link">
        <i class="fa fa-chevron-right" aria-hidden="true"></i>
    </a>
</div>