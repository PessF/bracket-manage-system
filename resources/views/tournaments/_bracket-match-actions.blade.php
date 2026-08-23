<div class="bracket-card-actions">
    @if($canEnterScore && $match->status === App\Enums\MatchStatus::READY)
    <form method="post" action="{{ route('matches.progress.store', [$tournament, $match]) }}">
        @csrf
        <button class="btn secondary bracket-icon-button progress-button" type="submit" aria-label="{{ __('ui.mark_in_progress_short') }}" title="{{ __('ui.mark_in_progress_short') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m10.5 8.5 5.5 3.5-5.5 3.5Z"/></svg></button>
    </form>
    @elseif($match->status === App\Enums\MatchStatus::LIVE)
    <div class="bracket-current"><span aria-hidden="true"></span>{{ __('ui.current_match') }}</div>
    @endif
    @if($canEnterScore || $canEditScore)
    <button
        class="btn bracket-icon-button score-modal-trigger"
        type="button"
        data-score-modal-trigger
        data-match-id="{{ $match->id }}"
        data-match-number="{{ $match->match_number }}"
        data-action="{{ route('matches.results.store', [$tournament, $match]) }}"
        data-team-a="{{ $nameA }}"
        data-team-b="{{ $nameB }}"
        data-score-a="{{ $match->score_a !== null ? (float) $match->score_a : 0 }}"
        data-score-b="{{ $match->score_b !== null ? (float) $match->score_b : 0 }}"
        data-editing="{{ $canEditScore ? 'true' : 'false' }}"
        aria-haspopup="dialog"
        aria-label="{{ $canEditScore ? __('ui.edit_score') : __('ui.enter_score') }}"
        title="{{ $canEditScore ? __('ui.edit_score') : __('ui.enter_score') }}"
    ><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/>@if($canEditScore)<path d="m8.5 15.5.7-3.1 4.9-4.9 2.4 2.4-4.9 4.9Z"/>@else<path d="M12 8v8M8 12h8"/>@endif</svg></button>
    @endif
</div>
