@php($isEditingScore = $match->status === App\Enums\MatchStatus::FINISHED)
@if($isEditingScore)<details class="score-editor"><summary>{{ __('ui.edit_score') }}</summary>@endif
<form class="easy-score-form" method="post" action="{{ route('matches.results.store', [$tournament, $match]) }}" @if($isEditingScore)data-confirm="{{ __('ui.score_correction_confirm') }}"@endif>
    @csrf
    <div class="score-pair">
        <label class="score-team-control"><span title="{{ $nameA }}">{{ $nameA }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $nameA]) }}" type="number" min="0" step="any" name="score_a" value="{{ $isEditingScore ? (float) $match->score_a : 0 }}" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
        <label class="score-team-control"><span title="{{ $nameB }}">{{ $nameB }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $nameB]) }}" type="number" min="0" step="any" name="score_b" value="{{ $isEditingScore ? (float) $match->score_b : 0 }}" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
    </div>
    <button class="btn small score-submit">{{ $isEditingScore ? __('ui.save_corrected_score') : __('ui.confirm_score') }}</button>
</form>
@if($isEditingScore)</details>@endif
