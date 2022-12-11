 <div class="player-container">
     <div class="player-row">
         <div class="me-3">
             <a href="{{ $playerProfileUrl }}" title="View {{ $playerGameReport->player->username }}'s profile">
                 @include('components.avatar', ['avatar' => $playerGameReport->player->user->getUserAvatar(), 'size' => 45])
             </a>
         </div>
         <div class="player-username">
             <a href="{{ $playerUrl or '' }}" title="View {{ $playerGameReport->player->username }}'s profile">
                 <p class="fw-bold mb-1">{{ $playerGameReport->player->username }}</p>
             </a>
         </div>
     </div>

     @if ($playerGameReport->stats)
         <div class="player-points d-flex">
             @php $playerStats2 = \App\Stats2::where("id", $playerGameReport->stats->id)->first(); @endphp
             @php $playerCountry = $playerStats2->faction($history->ladder->game, $playerGameReport->stats->cty); @endphp
             <div class="player-faction player-faction-{{ $playerCountry }}"></div>

             <div class="game-status status-{{ $playerGameReport->won ? 'won' : 'lost' }}">
                 <span class="status-text">
                     {{ $playerGameReport->won == true ? 'Won' : 'Lost' }}
                 </span>

                 <span class="points">
                     {{ $playerGameReport->points >= 0 ? "+$playerGameReport->points" : $playerGameReport->points }}
                 </span>
             </div>
         </div>
     @endif
 </div>