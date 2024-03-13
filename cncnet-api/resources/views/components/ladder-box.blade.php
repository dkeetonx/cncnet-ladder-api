<a href="{{ $url }}" class="game-box-ladder-link col-6 {{ $abbrev ?? $history->ladder->abbreviation }}"
   title="View {{ $history->ladder->name }}">
    <div class="game-box-ladder-link-bg bg-{{ $abbrev ?? $history->ladder->abbreviation }}"></div>
    <div class="ladder-description">
        <div class="logo">
            <img src="{{ \App\Models\URLHelper::getLadderLogoByAbbrev($abbrev ?? $history->ladder->abbreviation) }}"
                 alt="{{ $history->ladder->name }}"/>
        </div>
        <div class="text-center mt-2">
            <h5>
                {{ $history->ladder->name }}
                @if (!$history->ladder->clans_allowed)
                    1vs1 Ladder
                @endif
            </h5>
            <p>
                {{ Carbon\Carbon::parse($history->starts)->format('F Y') }}
            </p>
        </div>
    </div>
</a>
