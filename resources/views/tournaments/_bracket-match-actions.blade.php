<div class="bracket-card-actions">
    @if($match->participantA && $match->participantB)
    <button class="btn secondary bracket-icon-button match-details-trigger" type="button" data-match-details-trigger
        data-match-number="{{ $displayMatchNumber ?? $match->match_number }}"
        data-red-code="{{ $match->participantA->team_code }}" data-red-name="{{ $match->participantA->team_name }}" data-red-school="{{ $match->participantA->school }}"
        data-blue-code="{{ $match->participantB->team_code }}" data-blue-name="{{ $match->participantB->team_name }}" data-blue-school="{{ $match->participantB->school }}"
        aria-haspopup="dialog" aria-label="{{ __('ui.team_details') }}" title="{{ __('ui.team_details') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7.5h.01"/></svg></button>
    @endif
    @if($canEnterScore || $canEditScore)
    <button
        class="btn bracket-icon-button score-modal-trigger {{ $canEditScore ? 'edit-result-button' : 'record-result-button' }}"
        type="button"
        data-score-modal-trigger
        data-match-id="{{ $match->id }}"
        data-match-number="{{ $displayMatchNumber ?? $match->match_number }}"
        data-action="{{ route('matches.results.store', [$tournament, $match]) }}"
        data-team-a="{{ $nameA }}"
        data-team-b="{{ $nameB }}"
        data-score-a="{{ $match->score_a !== null ? (float) $match->score_a : 0 }}"
        data-score-b="{{ $match->score_b !== null ? (float) $match->score_b : 0 }}"
        data-editing="{{ $canEditScore ? 'true' : 'false' }}"
        aria-haspopup="dialog"
        aria-label="{{ $canEditScore ? __('ui.edit_score') : __('ui.enter_score') }}"
        title="{{ $canEditScore ? __('ui.edit_score') : __('ui.enter_score') }}"
    ><svg viewBox="0 0 24 24" aria-hidden="true">@if($canEditScore)<circle cx="12" cy="12" r="9"/><path d="m8.5 15.5.7-3.1 4.9-4.9 2.4 2.4-4.9 4.9Z"/>@else<path d="M8 4.5h8l2 2V19.5H6V4.5Z"/><path d="M15.5 4.5V7H18"/><path d="M8.5 11h7M8.5 14h4"/><path d="m13.5 15.5 1.4 1.4 2.8-3.4"/>@endif</svg></button>
    @endif
</div>
