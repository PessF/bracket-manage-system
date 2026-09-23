@extends('layouts.app')
@section('title', __('ui.title_bracket').' · '.$tournament->name)
@section('container-class', 'container-wide')
@section('body-class', 'bracket-page')


@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $isAdmin = ! $isPublicView && (auth()->user()?->isAdmin() ?? false);
@endphp
@if($isPublicView)
<div class="viewer-event-head">
    <div><h1>{{ $tournament->name }}</h1><p>{{ $tournament->competition }} · {{ $tournament->division }}</p></div>
    <span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span>
</div>
@if($isAdmin)
@include('tournaments._tabs')
@if(in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE], true))
<div class="bracket-toolbar">
    <div class="bracket-hint">{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-admin-actions">
        @if($tournament->status === App\Enums\TournamentStatus::READY)
        <form method="post" action="{{ route('tournaments.start', $tournament) }}">
            @csrf
            <button class="btn record-result-button" type="submit">{{ __('ui.start_tournament') }}</button>
        </form>
        @endif
        @if($tournament->status === App\Enums\TournamentStatus::LIVE)
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
        @endif
    </div>
</div>
@endif
@endif
@if(! $isAdmin)
@include('tournaments._tabs')
@endif
<div class="viewer-bracket-help">{{ __('ui.viewer_bracket_help') }}</div>
@else
<div class="page-head">
    <div>
        <div class="actions" style="margin-bottom:5px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div>
        <div class="muted">{{ $tournament->competition }} · {{ $tournament->division }} · {{ __('ui.format_labels.'.$tournament->format->value) }}</div>
    </div>
</div>
@include('tournaments._tabs')

<div class="bracket-toolbar">
    <div class="bracket-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-admin-actions">
        <div class="bracket-legend"><span><i class="legend-line"></i>{{ __('ui.advances_to') }}</span><span><i class="legend-win"></i>{{ __('ui.winner') }}</span><span>{{ __('ui.scroll_rounds') }}</span></div>
        @if($isAdmin && $tournament->status === App\Enums\TournamentStatus::READY)
        <form method="post" action="{{ route('tournaments.start', $tournament) }}">
            @csrf
            <button class="btn record-result-button" type="submit">{{ __('ui.start_tournament') }}</button>
        </form>
        @endif
        @if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
        @endif
    </div>
</div>
@endif

@includeWhen($tournament->status === App\Enums\TournamentStatus::LIVE, 'tournaments._live_refresh', [
    'interval' => 1,
    'refreshTarget' => '[data-live-bracket]',
])

@if(($bracketViewGroups ?? collect())->isNotEmpty())
@php
    $currentBracketView = $activeBracketView ?? 'all';
@endphp
<div class="bracket-view-select">
    <label for="bracket-view-select">{{ __('ui.bracket_view_mobile_label') }}</label>
    <select id="bracket-view-select" data-bracket-view-select aria-label="{{ __('ui.bracket_view_filter') }}">
        <option value="{{ request()->fullUrlWithQuery(['view' => 'all']) }}" @selected($currentBracketView === 'all')>{{ __('ui.all_groups') }}</option>
        @foreach($bracketViewGroups as $viewGroup)
        <option value="{{ request()->fullUrlWithQuery(['view' => 'group:'.$viewGroup->id]) }}" @selected($currentBracketView === 'group:'.$viewGroup->id)>{{ $viewGroup->name }}</option>
        @endforeach
        <option value="{{ request()->fullUrlWithQuery(['view' => 'playoff']) }}" @selected($currentBracketView === 'playoff')>{{ __('ui.playoff_stage') }}</option>
    </select>
</div>
<nav class="bracket-view-switcher" aria-label="{{ __('ui.bracket_view_filter') }}">
    <a class="{{ $currentBracketView === 'all' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'all']) }}">{{ __('ui.all_groups') }}</a>
    @foreach($bracketViewGroups as $viewGroup)
    <a class="{{ $currentBracketView === 'group:'.$viewGroup->id ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'group:'.$viewGroup->id]) }}">{{ $viewGroup->name }}</a>
    @endforeach
    <a class="{{ $currentBracketView === 'playoff' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'playoff']) }}">{{ __('ui.playoff_stage') }}</a>
</nav>
@endif

<div class="bracket-search" role="search" data-bracket-search>
    <div class="field">
        <label for="bracket-search">{{ __('search.bracket_label') }}</label>
        <input id="bracket-search" type="search" maxlength="100" autocomplete="off" placeholder="{{ __('search.participant_placeholder') }}" aria-describedby="bracket-search-status" data-bracket-search-input>
    </div>
    <button type="button" class="btn secondary" data-bracket-search-next disabled>{{ __('search.next') }}</button>
    <button type="button" class="btn secondary" data-bracket-search-clear disabled>{{ __('search.clear') }}</button>
    <span id="bracket-search-status" class="muted" role="status" aria-live="polite" data-bracket-search-status data-count="{{ __('search.count') }}" data-empty="{{ __('search.empty') }}" data-hint="{{ __('search.hint') }}">{{ __('search.hint') }}</span>
</div>

<div data-live-bracket>
@if($matches->isNotEmpty())
<div class="bracket-zoom-toolbar" data-bracket-zoom-toolbar hidden>
    <span class="bracket-zoom-label">{{ __('ui.bracket_zoom') }}</span>
    <div class="bracket-zoom-controls" role="group" aria-label="{{ __('ui.bracket_zoom') }}">
        <button class="bracket-zoom-button" type="button" data-bracket-zoom-out aria-label="{{ __('ui.bracket_zoom_out') }}" title="{{ __('ui.bracket_zoom_out') }}">−</button>
        <button class="bracket-zoom-button bracket-zoom-level" type="button" data-bracket-zoom-reset aria-label="{{ __('ui.bracket_zoom_reset') }}" title="{{ __('ui.bracket_zoom_reset') }}"><span data-bracket-zoom-level>80%</span></button>
        <button class="bracket-zoom-button" type="button" data-bracket-zoom-in aria-label="{{ __('ui.bracket_zoom_in') }}" title="{{ __('ui.bracket_zoom_in') }}">+</button>
    </div>
</div>
@endif

@if($tournament->status === App\Enums\TournamentStatus::COMPLETED && $podium->isNotEmpty())
@php
    $podiumMedalPaths = [
        1 => 'assets/images/medals/1st.png',
        2 => 'assets/images/medals/2nd.png',
        3 => 'assets/images/medals/3nd.png',
    ];
@endphp
<section class="card bracket-results-summary">
    <h2>{{ __('ui.results') }}</h2>
    <div class="podium-grid">
        @foreach($podium as $row)
        <div class="podium-card rank-{{ $row['rank'] }}">
            @php
                $medalPath = $podiumMedalPaths[$row['rank']] ?? null;
            @endphp
            @if($medalPath && is_file(public_path($medalPath)))
            <img class="podium-medal" src="{{ asset($medalPath) }}" alt="{{ __('ui.rank') }} {{ $row['rank'] }}">
            @else
            <span class="podium-rank">#{{ $row['rank'] }}</span>
            @endif
            <div>
                <div class="podium-team" title="{{ $row['participant']->team_name }}">{{ $row['participant']->team_name }}</div>
                @if($row['source'])
                <div class="podium-source">{{ __('ui.match') }} #{{ $row['source']->match_number }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @if($isPublicView)
    <div class="podium-more"><a class="btn record-result-button" href="{{ route('public.tournaments.results', ['tournament' => $tournament->public_token]) }}">See more</a></div>
    @endif
</section>
@endif

@php
    $displayMatchNumbersById = $matches
        ->flatten(1)
        ->unique('id')
        ->mapWithKeys(fn ($match): array => [(string) $match->id => (int) $match->match_number]);
    $isThirdPlaceMatch = fn ($match): bool => $match->participant_a_source_outcome === App\Enums\MatchOutcome::LOSER
        && $match->participant_b_source_outcome === App\Enums\MatchOutcome::LOSER;

    $participantDisplayName = function ($match, string $side) use ($displayMatchNumbersById): string {
        $participant = $side === 'a' ? $match->participantA : $match->participantB;
        if ($participant) {
            return $participant->team_name;
        }

        $sourceId = $side === 'a' ? $match->participant_a_source_match_id : $match->participant_b_source_match_id;
        $sourceOutcome = $side === 'a' ? $match->participant_a_source_outcome : $match->participant_b_source_outcome;
        if ($sourceId !== null && $sourceOutcome !== null) {
            $number = $displayMatchNumbersById->get((string) $sourceId);
            if ($number !== null) {
                return $sourceOutcome === App\Enums\MatchOutcome::WINNER
                    ? __('ui.source_winner_label', ['number' => $number])
                    : __('ui.source_loser_label', ['number' => $number]);
            }
        }

        return $side === 'a' ? $match->participantALabel() : $match->participantBLabel();
    };
@endphp

@forelse($matches as $type => $group)
@php
    $isGrid = in_array($type, ['ROUND_ROBIN', 'RANKING'], true);
    $isGroupSection = str_starts_with((string) $type, 'GROUP:');
    $isPlayoffSection = str_starts_with((string) $type, 'PLAYOFF:');
    $sectionType = $isPlayoffSection ? substr((string) $type, 8) : $type;
    $groupSectionType = $isGroupSection ? substr((string) $type, strrpos((string) $type, ':') + 1) : null;
    $groupName = $group->first()?->stageGroup?->name ?? __('ui.group');
    $sectionLabel = $isGroupSection ? ($groupName.' · '.__('ui.bracket_labels.'.$groupSectionType)) : ($isPlayoffSection ? __('ui.playoff_stage').' · '.__('ui.bracket_labels.'.$sectionType) : __('ui.bracket_labels.'.$type));
    if ($isPlayoffSection && $sectionType === App\Enums\BracketType::WINNERS->value) {
        $sectionLabel = __('ui.playoff_stage');
    }
    $isGrid = $isGrid || ($isGroupSection && $group->every(fn ($match): bool => in_array($match->bracket_type, [App\Enums\BracketType::ROUND_ROBIN, App\Enums\BracketType::RANKING], true)));
    $hasGrandFinal = $group->contains(fn ($match): bool => $match->bracket_type === App\Enums\BracketType::GRAND_FINAL);
    $lastRoundNumber = (int) $group->max('round_number');
@endphp
<section class="bracket-section">
    <div class="bracket-section-head"><h2>{{ $sectionLabel }}</h2><span class="bracket-count">{{ trans_choice('ui.match_count', $group->count(), ['count' => $group->count()]) }}</span></div>
    <div class="bracket-viewport {{ $isGrid ? 'bracket-grid' : '' }}" data-bracket-section tabindex="0" role="region" aria-label="{{ $sectionLabel }}" data-bracket-type="{{ $type }}" data-has-grand-final="{{ $hasGrandFinal ? 'true' : 'false' }}">
        @if(!$isGrid)
        <div class="bracket-zoom-stage" data-bracket-zoom-stage><div class="bracket-canvas" data-bracket-canvas></div></div>
        @endif
        @foreach($group as $match)
        @php
            $nameA = $participantDisplayName($match, 'a');
            $nameB = $participantDisplayName($match, 'b');
            $canEnterScore = $isAdmin
                && $tournament->status === App\Enums\TournamentStatus::LIVE
                && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true)
                && !$match->is_bye && $match->participant_a_id && $match->participant_b_id;
            $canEditScore = $isAdmin
                && $tournament->status === App\Enums\TournamentStatus::LIVE
                && $match->status === App\Enums\MatchStatus::FINISHED && !$match->is_bye
                && $match->participant_a_id && $match->participant_b_id;
            $isUnscored = !$match->is_bye && $match->participant_a_id && $match->participant_b_id
                && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true)
                && ($match->score_a === null || $match->score_b === null);
            $displayMatchNumber = $displayMatchNumbersById->get((string) $match->id, $loop->iteration);
            $winnerDestinationNumber = $displayMatchNumbersById->get((string) $match->winner_next_match_id, $match->winnerNextMatch?->match_number);
            $loserDestinationNumber = $displayMatchNumbersById->get((string) $match->loser_next_match_id, $match->loserNextMatch?->match_number);
            $isAwardMatch = !$isGrid && in_array($match->bracket_type, [App\Enums\BracketType::WINNERS, App\Enums\BracketType::GRAND_FINAL], true);
            $isThirdPlace = $isAwardMatch && $isThirdPlaceMatch($match);
            $isChampionship = $isAwardMatch && !$isThirdPlace && (int) $match->round_number === $lastRoundNumber && $match->winner_next_match_id === null;
            $championshipBadgeLabel = $isGroupSection ? __('ui.group_championship_match_badge', ['group' => $groupName]) : __('ui.championship_match_badge');
            $layoutSortNumber = $isThirdPlace ? $match->match_number + 100000 : $match->match_number;
        @endphp
        <article class="bracket-match-node {{ $match->status === App\Enums\MatchStatus::LIVE ? 'in-progress' : '' }} {{ $match->status === App\Enums\MatchStatus::FINISHED ? 'is-finished' : '' }} {{ $match->status === App\Enums\MatchStatus::READY ? 'is-ready' : '' }} {{ $isUnscored ? 'is-unscored' : '' }}"
            data-match-id="{{ $match->id }}" data-match-number="{{ $displayMatchNumber }}" data-round="{{ $match->round_number }}" data-number="{{ $layoutSortNumber }}" data-bracket-kind="{{ $match->bracket_type->value }}"
            data-winner-next="{{ $match->winner_next_match_id }}" data-loser-next="{{ $match->loser_next_match_id }}" data-third-place="{{ $isThirdPlace ? 'true' : 'false' }}">
            <div class="bracket-match-meta">
                <span class="bracket-match-number"><span>{{ __('ui.display_match') }}</span><strong>#{{ $displayMatchNumber }}</strong>@if(isset($estimatedStartTimes[(string) $match->id]))<i class="bracket-scheduled-time">{{ $estimatedStartTimes[(string) $match->id] }} {{ __('ui.time_suffix') }}</i>@endif</span>
                @if($isChampionship)
                <span class="bracket-award-badge champion">{{ $championshipBadgeLabel }}</span>
                @elseif($isThirdPlace)
                <span class="bracket-award-badge third">{{ __('ui.third_place_match_badge') }}</span>
                @else
                <span class="badge {{ $match->is_bye ? 'BYE' : $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : ($match->status === App\Enums\MatchStatus::READY && $tournament->status !== App\Enums\TournamentStatus::LIVE ? __('ui.match_waiting_to_start') : __('ui.match_status_labels.'.$match->status->value)) }}</span>
                @endif
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_a_id ? 'winner' : '' }} {{ $match->winner_id && $match->winner_id === $match->participant_a_id && $match->winner_next_match_id ? 'advancing' : '' }} {{ !$match->participant_a_id ? 'waiting' : '' }}" @if($match->participantA) data-participant-search="{{ json_encode([$match->participantA->team_name, ...$match->participantA->members->pluck('name')->all()], JSON_UNESCAPED_UNICODE) }}" @endif data-bracket-slot-state="{{ $match->participant_a_id ? 'confirmed' : 'waiting' }}">
                <span class="bracket-team-name">
                    <i class="match-side red">{{ __('ui.red_side') }}</i>
                    @if($match->participantA?->seed_number)
                    <i class="bracket-seed">{{ $match->participantA->seed_number }}</i>
                    @endif
                    <span title="{{ $nameA }}">{{ $nameA }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</span>
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_b_id ? 'winner' : '' }} {{ $match->winner_id && $match->winner_id === $match->participant_b_id && $match->winner_next_match_id ? 'advancing' : '' }} {{ !$match->participant_b_id ? 'waiting' : '' }}" @if($match->participantB) data-participant-search="{{ json_encode([$match->participantB->team_name, ...$match->participantB->members->pluck('name')->all()], JSON_UNESCAPED_UNICODE) }}" @endif data-bracket-slot-state="{{ $match->participant_b_id ? 'confirmed' : 'waiting' }}">
                <span class="bracket-team-name">
                    <i class="match-side blue">{{ __('ui.blue_side') }}</i>
                    @if($match->participantB?->seed_number)
                    <i class="bracket-seed">{{ $match->participantB->seed_number }}</i>
                    @endif
                    <span title="{{ $nameB }}">{{ $nameB }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</span>
            </div>
            @if($winnerDestinationNumber || $loserDestinationNumber)
            <div class="bracket-destinations" aria-label="{{ __('ui.match_destinations') }}">
                @if($winnerDestinationNumber)<span class="bracket-destination win"><strong>{{ __('ui.winner_short') }}</strong> → #{{ $winnerDestinationNumber }}</span>@endif
                @if($loserDestinationNumber)<span class="bracket-destination loss"><strong>{{ __('ui.loser_short') }}</strong> → #{{ $loserDestinationNumber }}</span>@endif
            </div>
            @endif
            @if($canEnterScore || $canEditScore || $match->status === App\Enums\MatchStatus::LIVE)
            @include('tournaments._bracket-match-actions')
            @endif
        </article>
        @endforeach
    </div>
</section>
@empty
<div class="card empty">{{ __('ui.bracket_empty') }}</div>
@endforelse
</div>

@if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
<dialog class="score-modal" data-score-modal aria-labelledby="score-modal-title" data-reopen-match="{{ old('score_modal_match') }}" data-old-score-a="{{ old('score_a') }}" data-old-score-b="{{ old('score_b') }}">
    <div class="score-modal-head"><h2 id="score-modal-title" data-score-modal-title>{{ __('ui.enter_score') }}</h2><button class="score-modal-close" type="button" data-score-modal-close aria-label="{{ __('ui.cancel') }}">×</button></div>
    <form class="score-modal-form" method="post" data-score-modal-form>
        @csrf
        <input type="hidden" name="score_modal_match" data-score-modal-match>
        <div class="score-modal-teams">
            <label class="score-modal-team" data-score-card-a><span class="score-modal-team-name"><i class="match-side red">{{ __('ui.red_side') }}</i><span data-score-team-a></span><em class="score-leader-badge">{{ __('ui.leading_score') }}</em></span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input type="number" inputmode="decimal" min="0" step="any" name="score_a" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
            <div class="score-versus" aria-hidden="true">VS</div>
            <label class="score-modal-team" data-score-card-b><span class="score-modal-team-name"><i class="match-side blue">{{ __('ui.blue_side') }}</i><span data-score-team-b></span><em class="score-leader-badge">{{ __('ui.leading_score') }}</em></span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input type="number" inputmode="decimal" min="0" step="any" name="score_b" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
        </div>
        <div class="score-modal-actions"><button class="btn secondary" type="button" data-score-modal-close>{{ __('ui.cancel') }}</button><button class="btn" type="submit" data-score-modal-submit>{{ __('ui.confirm_score') }}</button></div>
    </form>
</dialog>
@endif

<dialog class="score-modal match-details-modal" data-match-details-modal aria-labelledby="match-details-title">
    <div class="score-modal-head"><h2 id="match-details-title" data-match-details-title>{{ __('ui.team_details') }}</h2><button class="score-modal-close" type="button" data-match-details-close aria-label="{{ __('ui.close') }}">×</button></div>
    <div class="score-modal-form">
        <div class="match-details-grid">
            <div class="match-detail-side red-detail"><strong>{{ __('ui.red_side') }}</strong><dl><dt>{{ __('ui.team_id') }}</dt><dd data-details-red-code>—</dd><dt>{{ __('ui.team_name') }}</dt><dd data-details-red-name>—</dd><dt>{{ __('ui.school') }}</dt><dd data-details-red-school>—</dd></dl></div>
            <div class="match-detail-side blue-detail"><strong>{{ __('ui.blue_side') }}</strong><dl><dt>{{ __('ui.team_id') }}</dt><dd data-details-blue-code>—</dd><dt>{{ __('ui.team_name') }}</dt><dd data-details-blue-name>—</dd><dt>{{ __('ui.school') }}</dt><dd data-details-blue-school>—</dd></dl></div>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
document.addEventListener('change', (event) => {
    const select = event.target instanceof Element ? event.target.closest('[data-bracket-view-select]') : null;
    if (select?.value) window.location.href = select.value;
});

(() => {
    const dialog = document.querySelector('[data-match-details-modal]');
    if (!dialog) return;
    const fields = {
        redCode: dialog.querySelector('[data-details-red-code]'), redName: dialog.querySelector('[data-details-red-name]'), redSchool: dialog.querySelector('[data-details-red-school]'),
        blueCode: dialog.querySelector('[data-details-blue-code]'), blueName: dialog.querySelector('[data-details-blue-name]'), blueSchool: dialog.querySelector('[data-details-blue-school]'),
    };
    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-match-details-trigger]') : null;
        if (!trigger) return;
        dialog.querySelector('[data-match-details-title]').textContent = @json(__('ui.match_details', ['number' => '__NUMBER__'])).replace('__NUMBER__', trigger.dataset.matchNumber);
        fields.redCode.textContent = trigger.dataset.redCode || '—'; fields.redName.textContent = trigger.dataset.redName || '—'; fields.redSchool.textContent = trigger.dataset.redSchool || '—';
        fields.blueCode.textContent = trigger.dataset.blueCode || '—'; fields.blueName.textContent = trigger.dataset.blueName || '—'; fields.blueSchool.textContent = trigger.dataset.blueSchool || '—';
        dialog.showModal();
    });
    dialog.querySelectorAll('[data-match-details-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();

(() => {
    const dialog = document.querySelector('[data-score-modal]');
    if (!dialog) return;

    const form = dialog.querySelector('[data-score-modal-form]');
    const title = dialog.querySelector('[data-score-modal-title]');
    const teamA = dialog.querySelector('[data-score-team-a]');
    const teamB = dialog.querySelector('[data-score-team-b]');
    const scoreA = form.elements.score_a;
    const scoreB = form.elements.score_b;
    const cardA = dialog.querySelector('[data-score-card-a]');
    const cardB = dialog.querySelector('[data-score-card-b]');
    const matchId = form.elements.score_modal_match;
    const submit = dialog.querySelector('[data-score-modal-submit]');
    const titleTemplate = @json(__('ui.score_match_title', ['number' => '__NUMBER__']));
    const scoreLabelTemplate = @json(__('ui.score_for_team', ['team' => '__TEAM__']));
    const correctionConfirm = @json(__('ui.score_correction_confirm'));
    const enterLabel = @json(__('ui.confirm_score'));
    const editLabel = @json(__('ui.save_corrected_score'));

    const openModal = (trigger, restoreOldInput = false) => {
        const editing = trigger.dataset.editing === 'true';
        form.action = trigger.dataset.action;
        matchId.value = trigger.dataset.matchId;
        title.textContent = titleTemplate.replace('__NUMBER__', trigger.dataset.matchNumber);
        teamA.textContent = trigger.dataset.teamA;
        teamB.textContent = trigger.dataset.teamB;
        scoreA.value = restoreOldInput && dialog.dataset.oldScoreA !== '' ? dialog.dataset.oldScoreA : trigger.dataset.scoreA;
        scoreB.value = restoreOldInput && dialog.dataset.oldScoreB !== '' ? dialog.dataset.oldScoreB : trigger.dataset.scoreB;
        scoreA.setAttribute('aria-label', scoreLabelTemplate.replace('__TEAM__', trigger.dataset.teamA));
        scoreB.setAttribute('aria-label', scoreLabelTemplate.replace('__TEAM__', trigger.dataset.teamB));
        submit.textContent = editing ? editLabel : enterLabel;
        if (editing) form.dataset.confirm = correctionConfirm;
        else delete form.dataset.confirm;
        syncLeader();

        dialog.showModal();
        requestAnimationFrame(() => scoreA.focus());
    };

    const scoreValue = (input) => {
        const value = Number(input.value);
        return Number.isFinite(value) ? value : null;
    };

    const syncLeader = () => {
        const a = scoreValue(scoreA);
        const b = scoreValue(scoreB);
        cardA?.classList.remove('leading');
        cardB?.classList.remove('leading');
        if (a === null || b === null || a === b) return;
        (a > b ? cardA : cardB)?.classList.add('leading');
    };

    scoreA.addEventListener('input', syncLeader);
    scoreB.addEventListener('input', syncLeader);

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-score-modal-trigger]') : null;
        if (trigger) openModal(trigger);
    });
    dialog.querySelectorAll('[data-score-modal-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    if (dialog.dataset.reopenMatch) {
        const trigger = document.querySelector(`[data-score-modal-trigger][data-match-id="${CSS.escape(dialog.dataset.reopenMatch)}"]`);
        if (trigger) openModal(trigger, true);
    }
})();

(() => {
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const HEADER = 88;
    const GAP_X = 160;
    const GAP_Y = 64;
    const CARD_WIDTH = 360;
    const PADDING = 36;
    const ROUND_LABEL = @json(__('ui.round'));
    const FINAL_LABEL = @json(__('ui.final'));
    const GRAND_FINAL_LABEL = @json(__('ui.grand_final'));
    const SEMIFINAL_LABEL = @json(__('ui.semifinals'));
    const QUARTERFINAL_LABEL = @json(__('ui.quarterfinals'));
    const FINALS_LABEL = @json(__('ui.finals'));
    const LOSERS_ROUND_LABEL = @json(__('ui.losers_round'));
    const MIN_ZOOM = 0.4;
    const MAX_ZOOM = 1.4;
    const ZOOM_STEP = 0.2;
    const ZOOM_STORAGE_KEY = 'easykids-bracket-zoom';
    let storedZoom = Number.NaN;
    try { storedZoom = Number(sessionStorage.getItem(ZOOM_STORAGE_KEY)); } catch (_) {}
    let bracketZoom = Number.isFinite(storedZoom) && storedZoom >= MIN_ZOOM && storedZoom <= MAX_ZOOM ? storedZoom : 1;
    let resizeObservers = [];

    const clampZoom = (value) => Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(value * 10) / 10));
    const initializeBracket = () => {
        resizeObservers.forEach((observer) => observer.disconnect());
        resizeObservers = [];

        // Measure every card before fixing one size for the entire view. Long names,
        // translated labels and admin actions can increase the shared height.
        const allNodes = [...document.querySelectorAll('[data-live-bracket] .bracket-match-node')];
        allNodes.forEach((node) => { node.style.width = `${CARD_WIDTH}px`; node.style.height = 'auto'; });
        const cardHeight = Math.ceil(Math.max(200, ...allNodes.map((node) => node.offsetHeight)));
        allNodes.forEach((node) => { node.style.height = `${cardHeight}px`; });

        const zoomToolbar = document.querySelector('[data-bracket-zoom-toolbar]');
        const zoomOutButton = zoomToolbar?.querySelector('[data-bracket-zoom-out]');
        const zoomInButton = zoomToolbar?.querySelector('[data-bracket-zoom-in]');
        const zoomResetButton = zoomToolbar?.querySelector('[data-bracket-zoom-reset]');
        const zoomLevelLabel = zoomToolbar?.querySelector('[data-bracket-zoom-level]');
        const zoomStages = [];
        const syncZoom = () => {
            const appliedZoom = bracketZoom;
            zoomStages.forEach(({ stage, canvas }) => {
                const width = Number(canvas.dataset.layoutWidth || 0);
                const height = Number(canvas.dataset.layoutHeight || 0);
                canvas.style.transform = `scale(${appliedZoom})`;
                if (width) stage.style.width = `${width * appliedZoom}px`;
                if (height) stage.style.height = `${height * appliedZoom}px`;
            });
            if (zoomLevelLabel) zoomLevelLabel.textContent = `${Math.round(appliedZoom * 100)}%`;
            if (zoomOutButton) zoomOutButton.disabled = bracketZoom <= MIN_ZOOM;
            if (zoomInButton) zoomInButton.disabled = bracketZoom >= MAX_ZOOM;
            if (zoomResetButton) zoomResetButton.disabled = bracketZoom === 1;
        };
        const setBracketZoom = (value) => {
            bracketZoom = clampZoom(value);
            try { sessionStorage.setItem(ZOOM_STORAGE_KEY, String(bracketZoom)); } catch (_) {}
            syncZoom();
        };

        if (zoomOutButton) zoomOutButton.onclick = () => setBracketZoom(bracketZoom - ZOOM_STEP);
        if (zoomInButton) zoomInButton.onclick = () => setBracketZoom(bracketZoom + ZOOM_STEP);
        if (zoomResetButton) zoomResetButton.onclick = () => setBracketZoom(1);

        document.querySelectorAll('[data-bracket-section]:not(.bracket-grid)').forEach((viewport) => {
        const canvas = viewport.querySelector('[data-bracket-canvas]');
        const stage = viewport.querySelector('[data-bracket-zoom-stage]');
        const nodes = [...viewport.querySelectorAll('.bracket-match-node')];
        if (!canvas || !stage || !nodes.length) return;

        zoomStages.push({ stage, canvas });
        nodes.forEach((node) => canvas.appendChild(node));
        const matches = nodes.map((node) => ({
            node, id: node.dataset.matchId, round: Number(node.dataset.round), number: Number(node.dataset.number), kind: node.dataset.bracketKind,
            winnerNext: node.dataset.winnerNext || null, loserNext: node.dataset.loserNext || null,
        }));
        const ids = new Set(matches.map((match) => match.id));
        const rounds = [...new Set(matches.map((match) => match.round))].sort((a,b) => a-b);
        const roundIndex = new Map(rounds.map((round, index) => [round, index]));
        const layout = () => {
            canvas.querySelectorAll('.bracket-round-group').forEach((group) => group.replaceWith(...group.childNodes));
            canvas.querySelectorAll('.bracket-connectors, .bracket-round-title, .bracket-round-lane').forEach((element) => element.remove());
            const cardWidth = CARD_WIDTH;
            const base = cardHeight + GAP_Y;
            const columnWidth = cardWidth + GAP_X;
            const matchesByRound = rounds.map((round) => matches.filter((match) => match.round === round).sort((a,b) => a.number-b.number));
            const maximumCount = Math.max(...matchesByRound.map((inRound) => inRound.length));
            const y = new Map();
            // Every round has the same pitch, centered on the same horizontal axis.
            matchesByRound.forEach((inRound) => inRound.forEach((match, index) => {
                y.set(match.id, ((maximumCount - inRound.length) / 2 + index) * base);
            }));
            const edges = matches.flatMap((source) => [
                {source, targetId:source.winnerNext, outcome:'winner'},
                {source, targetId:source.loserNext, outcome:'loser'},
            ])
                .filter((edge) => edge.targetId && ids.has(edge.targetId))
                .map((edge) => ({...edge, target:matches.find((candidate) => candidate.id === edge.targetId)}))
                .filter((edge) => edge.target);

            // Skipped rounds travel below all cards, with a separate lane per edge.
            const detours = edges.filter((edge) => roundIndex.get(edge.target.round) !== roundIndex.get(edge.source.round) + 1);
            const bottom = (maximumCount - 1) * base + cardHeight + HEADER;
            const width = rounds.length * columnWidth - GAP_X + PADDING * 2;
            const height = bottom + PADDING + detours.length * 16;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;
            canvas.dataset.layoutWidth = String(width);
            canvas.dataset.layoutHeight = String(height);

            const roundTitle = (round, index) => {
                const type = viewport.dataset.bracketType;
                const sectionType = type.split(':').pop();
                const grandFinalRounds = rounds.filter(value => matches.some(match => match.round === value && match.kind === 'GRAND_FINAL'));
                const eliminationRounds = rounds.filter(value => !grandFinalRounds.includes(value));
                const remaining = eliminationRounds.length - eliminationRounds.indexOf(round);

                if (grandFinalRounds.includes(round)) return grandFinalRounds.length > 1 ? `${GRAND_FINAL_LABEL} ${grandFinalRounds.indexOf(round) + 1}` : GRAND_FINAL_LABEL;
                if (sectionType === 'LOSERS') return `${LOSERS_ROUND_LABEL} ${index + 1}`;
                if (sectionType === 'WINNERS') {
                    if (remaining === 1) return FINALS_LABEL;
                    if (remaining === 2) return SEMIFINAL_LABEL;
                    if (remaining === 3) return QUARTERFINAL_LABEL;
                    return `${ROUND_LABEL} ${index + 1}`;
                }

                if (rounds.length === 1) return FINAL_LABEL;
                if (remaining === 1) return FINALS_LABEL;
                if (remaining === 2) return SEMIFINAL_LABEL;
                if (remaining === 3) return QUARTERFINAL_LABEL;
                return `${ROUND_LABEL} ${index + 1}`;
            };

            rounds.forEach((round, index) => {
                const lane = document.createElement('div');
                lane.className = 'bracket-round-lane';
                lane.setAttribute('aria-hidden', 'true');
                lane.style.left = `${index * columnWidth + PADDING - 12}px`;
                lane.style.width = `${cardWidth + 24}px`;
                lane.style.height = `${bottom + 12}px`;
                canvas.appendChild(lane);
                const title = document.createElement('div');
                title.className = 'bracket-round-title';
                title.style.left = `${index * columnWidth + PADDING}px`;
                title.style.top = '10px';
                title.style.width = `${cardWidth}px`;
                title.textContent = roundTitle(round, index);
                // Keep DOM and keyboard order logical, not just visually positioned.
                const group = document.createElement('section');
                group.className = 'bracket-round-group';
                group.setAttribute('aria-label', title.textContent);
                group.appendChild(title);
                matchesByRound[index].forEach((match) => group.appendChild(match.node));
                canvas.appendChild(group);
            });

            matches.forEach((match) => {
                const index = roundIndex.get(match.round) || 0;
                match.node.style.left = `${index * columnWidth + PADDING}px`;
                match.node.style.top = `${(y.get(match.id) || 0) + HEADER}px`;
                match.node.style.width = `${cardWidth}px`;
            });

            const svg = document.createElementNS(SVG_NS, 'svg');
            svg.classList.add('bracket-connectors');
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);


            const routeGroups = new Map();
            edges.forEach((edge) => {
                // Draw all feeder outlines before their strokes so true junctions
                // remain joined; only unrelated crossings get a separation gap.
                if (!routeGroups.has(edge.targetId)) {
                    const group = document.createElementNS(SVG_NS, 'g');
                    const layers = ['outlines', 'lines', 'ports'].map(() => document.createElementNS(SVG_NS, 'g'));
                    group.append(...layers);
                    svg.appendChild(group);
                    routeGroups.set(edge.targetId, layers);
                }
                const [outlines, lines, ports] = routeGroups.get(edge.targetId);
                const incoming = edges.filter((candidate) => candidate.targetId === edge.targetId);
                const x1 = (roundIndex.get(edge.source.round) || 0) * columnWidth + PADDING + cardWidth;
                const y1 = (y.get(edge.source.id) || 0) + HEADER + cardHeight / 2;
                const x2 = (roundIndex.get(edge.target.round) || 0) * columnWidth + PADDING;
                const y2 = (y.get(edge.target.id) || 0) + HEADER + cardHeight / 2;
                const furthestSourceX = Math.max(...incoming.map((candidate) => (roundIndex.get(candidate.source.round) || 0) * columnWidth + PADDING + cardWidth));
                const trackX = furthestSourceX + (x2 - furthestSourceX) * .5;
                const path = document.createElementNS(SVG_NS, 'path');
                path.setAttribute('class', `bracket-connector is-${edge.outcome}`);
                const detour = detours.indexOf(edge);
                let route = `M ${x1} ${y1} H ${trackX} V ${y2} H ${x2}`;
                if (detour >= 0) {
                    const laneY = bottom + 16 * (detour + 1);
                    // Both vertical legs stay strictly inside the column gutters.
                    const offset = 20 + 60 * (detour + 1) / (detours.length + 1);
                    route = `M ${x1} ${y1} H ${x1 + offset} V ${laneY} H ${x2 - offset} V ${y2} H ${x2}`;
                }
                path.setAttribute('d', route);
                path.dataset.source = edge.source.id;
                path.dataset.target = edge.target.id;
                const outline = document.createElementNS(SVG_NS, 'path');
                outline.setAttribute('class', 'bracket-connector-outline');
                outline.setAttribute('d', route);
                outlines.appendChild(outline);
                lines.appendChild(path);
                for (const [cx, cy] of [[x1, y1], [x2, y2]]) {
                    const port = document.createElementNS(SVG_NS, 'circle');
                    port.setAttribute('class', 'bracket-connector-port');
                    port.setAttribute('cx', cx);
                    port.setAttribute('cy', cy);
                    port.setAttribute('r', '3');
                    ports.appendChild(port);
                }
            });
            canvas.prepend(svg);
            syncZoom();
        };

        layout();
        let observedWidth = Math.round(viewport.getBoundingClientRect().width);
        let resizeFrame = null;
        const observer = new ResizeObserver(([entry]) => {
            const nextWidth = Math.round(entry.contentRect.width);
            if (nextWidth === observedWidth) return;
            observedWidth = nextWidth;
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(layout);
        });
        observer.observe(viewport);
        resizeObservers.push(observer);
        });
        if (zoomStages.length && zoomToolbar) zoomToolbar.hidden = false;
        syncZoom();
    };

    document.fonts.ready.then(initializeBracket);
    document.querySelector('[data-live-bracket]')?.addEventListener('load', (event) => {
        if (event.target instanceof HTMLImageElement) initializeBracket();
    }, true);
    document.addEventListener('easykids:live-content-updated', (event) => {
        if (event.detail?.target?.matches?.('[data-live-bracket]')) initializeBracket();
    });
    initializeBracket();
})();
</script>
@endpush
