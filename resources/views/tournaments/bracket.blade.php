@extends('layouts.app')
@section('title', __('ui.title_bracket').' · '.$tournament->name)
@section('container-class', 'container-wide')

@push('styles')
<style>
    .bracket-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .bracket-hint { display:flex; align-items:center; gap:7px; color:var(--muted); font-size:13px; }
    .bracket-hint svg { width:15px; height:15px; }
    .bracket-section { margin:0 0 30px; }
    .bracket-section-head { display:flex; align-items:center; gap:10px; margin:0 0 10px; }
    .bracket-section-head h2 { margin:0; font-size:16px; }
    .bracket-count { color:var(--muted); font-size:12px; }
    .bracket-admin-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .bracket-admin-actions form { margin:0; }
    .bracket-viewport { position:relative; overflow:auto; overscroll-behavior-inline:contain; min-height:190px; border:1px solid var(--line); border-radius:7px; background:#0c1219; scrollbar-color:#3a4653 transparent; -webkit-overflow-scrolling:touch; }
    .bracket-canvas { position:relative; min-width:100%; }
    .bracket-connectors { position:absolute; inset:0; z-index:1; overflow:visible; pointer-events:none; }
    .bracket-connector { fill:none; stroke:#cbd5e1; stroke-width:2; stroke-linejoin:round; vector-effect:non-scaling-stroke; }
    .bracket-round-title { position:absolute; top:0; height:44px; display:flex; align-items:center; color:#71717a; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.055em; }
    .bracket-match-node { position:absolute; z-index:2; width:272px; min-height:126px; padding:10px; border:1px solid var(--line); border-radius:7px; background:var(--card); box-shadow:none; transition:border-color .14s; }
    .bracket-match-node:hover { z-index:4; border-color:var(--line-strong); box-shadow:none; transform:none; }
    .bracket-match-meta { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:6px; min-height:24px; margin-bottom:6px; color:var(--muted); font-size:11px; }
    .bracket-match-number { display:inline-flex; align-items:baseline; gap:4px; font-weight:700; color:#52525b; }
    .bracket-match-number strong { font-size:16px; font-weight:900; }
    .bracket-match-number span { font-size:10px; }
    .bracket-team { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:7px; align-items:center; min-height:35px; margin:0; padding:6px 7px; background:var(--soft); border:1px solid transparent; border-radius:5px; font-weight:inherit; }
    .bracket-team + .bracket-team { margin-top:3px; }
    .bracket-team.winner { background:#f0fdf4; border-color:#dcfce7; color:#166534; font-weight:650; }
    .bracket-team.waiting { color:#a1a1aa; font-size:13px; }
    .bracket-team-name { display:flex; align-items:center; gap:7px; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .bracket-seed { display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; min-width:21px; height:21px; padding:0 5px; border-radius:4px; background:#e4e4e7; color:#52525b; font:700 11px ui-monospace,monospace; }
    .bracket-score { min-width:24px; text-align:center; font:700 14px ui-monospace,SFMono-Regular,monospace; }
    .bracket-card-actions { display:flex; align-items:center; justify-content:flex-end; gap:5px; margin-top:5px; }
    .bracket-card-actions .bracket-icon-button { display:grid; place-items:center; width:36px; min-width:36px; min-height:36px; height:36px; margin:0; padding:0; border-radius:6px; }
    .bracket-icon-button svg { display:block; width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .bracket-current { display:inline-flex; align-items:center; gap:6px; min-height:36px; margin-right:auto; color:var(--warn); font-size:10px; font-weight:750; }
    .bracket-current span { width:7px; height:7px; border-radius:50%; background:#f97316; box-shadow:0 0 0 3px rgb(249 115 22 / .14); }
    .score-modal { width:min(440px,calc(100vw - 24px)); max-height:calc(100dvh - 24px); margin:auto; padding:0; overflow:auto; border:1px solid var(--line-strong); border-radius:8px; background:var(--card); color:var(--ink); box-shadow:0 24px 70px rgb(0 0 0 / .55); }
    .score-modal::backdrop { background:rgb(2 7 12 / .76); backdrop-filter:blur(2px); }
    .score-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 15px; border-bottom:1px solid var(--line); }
    .score-modal-head h2 { margin:0; font-size:16px; }
    .score-modal-close { width:32px; min-width:32px; min-height:32px; padding:4px; border:0; border-radius:5px; background:transparent; color:var(--muted); font-size:22px; line-height:1; cursor:pointer; }
    .score-modal-close:hover { background:var(--soft); color:var(--ink); }
    .score-modal-form { padding:15px; }
    .score-modal-teams { display:grid; gap:9px; }
    .score-modal-team { display:grid; grid-template-columns:minmax(0,1fr) 150px; align-items:center; gap:12px; margin:0; padding:10px; border:1px solid var(--line); border-radius:6px; background:var(--soft); }
    .score-modal-team-name { display:flex; align-items:center; gap:7px; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .score-modal-team .score-stepper { min-width:0; }
    .score-modal-actions { display:flex; justify-content:flex-end; gap:7px; margin-top:14px; }
    .bracket-destinations { display:flex; align-items:center; gap:5px; margin-top:7px; color:#71717a; font-size:12px; }
    .bracket-destination { display:inline-flex; align-items:center; gap:4px; min-width:0; height:20px; padding:0 6px; border:1px solid var(--line); border-radius:999px; background:var(--soft); white-space:nowrap; }
    .bracket-destination strong { font-weight:800; }
    .bracket-destination.win { border-color:#9bd9bf; background:#e8f8f0; color:#116f4f; }
    .bracket-destination.loss { border-color:#c9d7e2; background:#f2f7fb; color:#5b6e7e; }
    .bracket-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:12px; padding:14px; }
    .bracket-grid .bracket-match-node { position:relative; width:auto; height:auto !important; min-height:118px; left:auto !important; top:auto !important; }
    .bracket-legend { display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:12px; color:var(--muted); }
    .bracket-legend span { display:inline-flex; align-items:center; gap:5px; }
    .legend-line { display:inline-block; width:22px; border-top:2px solid #cbd5e1; }
    .legend-win { display:inline-block; width:12px; height:12px; border-radius:3px; background:#f0fdf4; border:1px solid #dcfce7; }
    body[data-theme="dark"] .bracket-viewport { background:#0c1219; box-shadow:none; scrollbar-color:#3a4653 transparent; }
    body[data-theme="dark"] .bracket-connector, body[data-theme="dark"] .legend-line { stroke:#4e7797; border-color:#4e7797; }
    body[data-theme="dark"] .bracket-round-title, body[data-theme="dark"] .bracket-destinations { color:#afc8dd; }
    body[data-theme="dark"] .bracket-match-node { border-color:var(--line); background:var(--card); box-shadow:none; }
    body[data-theme="dark"] .bracket-match-node:hover { border-color:var(--line-strong); box-shadow:none; }
    body[data-theme="dark"] .bracket-match-number { color:#d7e9f7; }
    body[data-theme="dark"] .bracket-team { background:#132e47; }
    body[data-theme="dark"] .bracket-team.winner, body[data-theme="dark"] .legend-win { border-color:#28775c; background:#103b31; color:#94efc0; }
    body[data-theme="dark"] .bracket-team.waiting { color:#8ea9bf; }
    body[data-theme="dark"] .bracket-seed { background:#26465f; color:#d8ebf8; }
    .viewer-event-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:10px; }
    .viewer-event-head h1 { margin:0; color:var(--ink); font-size:22px; line-height:1.25; }
    .viewer-event-head p { margin:2px 0 0; color:var(--muted); font-size:13px; }
    .viewer-bracket-help { margin:0 0 10px; color:var(--muted); font-size:12px; }
    body[data-theme="dark"] .viewer-event-head h1 { color:#f2f8ff; }
    @media(max-width:680px){.bracket-viewport{margin-left:-10px;margin-right:-10px;border-radius:0;border-left:0;border-right:0;scroll-snap-type:x proximity;touch-action:pan-x pan-y}.bracket-toolbar{align-items:flex-start;flex-direction:column}.bracket-legend{gap:8px 12px}.bracket-legend span:nth-child(-n+2){display:none}.bracket-match-node{width:min(252px,calc(100vw - 48px));scroll-snap-align:start}.bracket-grid{grid-template-columns:minmax(0,1fr);padding:10px}.bracket-grid .bracket-match-node{width:100%}.bracket-round-title{font-size:11px}.bracket-section{margin-bottom:22px}.bracket-section-head{padding:0 2px}.viewer-event-head{align-items:flex-start}.viewer-event-head>div{min-width:0}.viewer-event-head h1,.viewer-event-head p{overflow-wrap:anywhere}.viewer-event-head h1{font-size:19px}.viewer-event-head .badge{flex:0 0 auto}.match-side{min-width:36px;padding-inline:5px;font-size:9px}.bracket-team-name{gap:5px}.score-modal-team{grid-template-columns:1fr;gap:8px}.score-modal-actions{display:grid;grid-template-columns:1fr 1fr}.score-modal-actions .btn{width:100%}}
    @media(max-width:380px){.score-modal-actions{grid-template-columns:1fr}.bracket-destinations{align-items:flex-start;flex-direction:column;gap:2px}.bracket-destinations span{white-space:normal}}
</style>
@endpush

@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $isAdmin = auth()->user()?->isAdmin() ?? false;
@endphp
@if($isPublicView)
<div class="viewer-event-head">
    <div><h1>{{ $tournament->name }}</h1><p>{{ $tournament->competition }} · {{ $tournament->division }}</p></div>
    <span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span>
</div>
@includeWhen($tournament->status === App\Enums\TournamentStatus::LIVE, 'tournaments._live_refresh')
@if($isAdmin)
@include('tournaments._tabs')
@if($tournament->status === App\Enums\TournamentStatus::LIVE)
<div class="bracket-toolbar">
    <div class="bracket-hint">{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-admin-actions">
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
    </div>
</div>
@endif
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
        @if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
        @endif
    </div>
</div>
@endif

@if($tournament->status === App\Enums\TournamentStatus::COMPLETED && $podium->isNotEmpty())
<section class="card bracket-results-summary">
    <h2>{{ __('ui.results') }}</h2>
    <div class="podium-grid">
        @foreach($podium as $row)
        <div class="podium-card rank-{{ $row['rank'] }}">
            <span class="podium-rank">#{{ $row['rank'] }}</span>
            <div>
                <div class="podium-team" title="{{ $row['participant']->team_name }}">{{ $row['participant']->team_name }}</div>
                @if($row['source'])
                <div class="podium-source">{{ __('ui.match') }} #{{ $row['source']->match_number }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

@forelse($matches as $type => $group)
@php
    $isGrid = in_array($type, ['ROUND_ROBIN', 'RANKING'], true);
    $hasGrandFinal = $group->contains(fn ($match): bool => $match->bracket_type === App\Enums\BracketType::GRAND_FINAL);
@endphp
<section class="bracket-section">
    <div class="bracket-section-head"><h2>{{ __('ui.bracket_labels.'.$type) }}</h2><span class="bracket-count">{{ trans_choice('ui.match_count', $group->count(), ['count' => $group->count()]) }}</span></div>
    <div class="bracket-viewport {{ $isGrid ? 'bracket-grid' : '' }}" data-bracket-section data-bracket-type="{{ $type }}" data-has-grand-final="{{ $hasGrandFinal ? 'true' : 'false' }}">
        @if(!$isGrid)<div class="bracket-canvas" data-bracket-canvas></div>@endif
        @foreach($group as $match)
        @php
            $nameA = $match->participantA?->team_name ?? $match->participantALabel();
            $nameB = $match->participantB?->team_name ?? $match->participantBLabel();
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
        @endphp
        <article class="bracket-match-node {{ $match->status === App\Enums\MatchStatus::LIVE ? 'in-progress' : '' }} {{ $match->status === App\Enums\MatchStatus::FINISHED ? 'is-finished' : '' }} {{ $match->status === App\Enums\MatchStatus::READY ? 'is-ready' : '' }} {{ $isUnscored ? 'is-unscored' : '' }}"
            data-match-id="{{ $match->id }}" data-round="{{ $match->round_number }}" data-number="{{ $match->match_number }}"
            data-winner-next="{{ $match->winner_next_match_id }}" data-loser-next="{{ $match->loser_next_match_id }}">
            <div class="bracket-match-meta"><span class="bracket-match-number"><span>{{ $type === 'GRAND_FINAL' ? __('ui.grand_final') : __('ui.match') }}</span><strong>#{{ $type === 'GRAND_FINAL' ? $loop->iteration : $match->match_number }}</strong></span><span class="badge {{ $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span></div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_a_id ? 'winner' : '' }} {{ !$match->participant_a_id ? 'waiting' : '' }}">
                <span class="bracket-team-name"><i class="match-side red">{{ __('ui.red_side') }}</i>@if($match->participantA?->seed_number)<i class="bracket-seed">{{ $match->participantA->seed_number }}</i>@endif<span title="{{ $nameA }}">{{ $nameA }}</span></span><span class="bracket-score">{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</span>
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_b_id ? 'winner' : '' }} {{ !$match->participant_b_id ? 'waiting' : '' }}">
                <span class="bracket-team-name"><i class="match-side blue">{{ __('ui.blue_side') }}</i>@if($match->participantB?->seed_number)<i class="bracket-seed">{{ $match->participantB->seed_number }}</i>@endif<span title="{{ $nameB }}">{{ $nameB }}</span></span><span class="bracket-score">{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</span>
            </div>
            @if($canEnterScore || $canEditScore || $match->status === App\Enums\MatchStatus::LIVE)@include('tournaments._bracket-match-actions')@endif
            @if($match->winnerNextMatch || $match->loserNextMatch)<div class="bracket-destinations">@if($match->winnerNextMatch)<span class="bracket-destination win">{{ __('ui.winner_to_match', ['number' => $match->winnerNextMatch->match_number]) }}</span>@endif @if($match->loserNextMatch)<span class="bracket-destination loss">{{ __('ui.loser_to_match', ['number' => $match->loserNextMatch->match_number]) }}</span>@endif</div>@endif
        </article>
        @endforeach
    </div>
</section>
@empty
<div class="card empty">{{ __('ui.bracket_empty') }}</div>
@endforelse

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
@endsection

@push('scripts')
<script>
@if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
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

    document.querySelectorAll('[data-score-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => openModal(trigger));
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
@endif

(() => {
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const HEADER = 58;
    const GAP_X = 54;
    const GAP_Y = 18;
    const ACTION_GUTTER = 48;
    const ROUND_LABEL = @json(__('ui.round'));
    const FINAL_LABEL = @json(__('ui.final'));
    const SEMIFINAL_LABEL = @json(__('ui.semifinals'));
    const FINALS_LABEL = @json(__('ui.finals'));
    const LOSERS_ROUND_LABEL = @json(__('ui.losers_round'));

    document.querySelectorAll('[data-bracket-section]:not(.bracket-grid)').forEach((viewport) => {
        const canvas = viewport.querySelector('[data-bracket-canvas]');
        const nodes = [...viewport.querySelectorAll('.bracket-match-node')];
        if (!canvas || !nodes.length) return;

        nodes.forEach((node) => canvas.appendChild(node));
        const matches = nodes.map((node) => ({
            node, id: node.dataset.matchId, round: Number(node.dataset.round), number: Number(node.dataset.number),
            winnerNext: node.dataset.winnerNext || null, loserNext: node.dataset.loserNext || null,
        }));
        const ids = new Set(matches.map((match) => match.id));
        const rounds = [...new Set(matches.map((match) => match.round))].sort((a,b) => a-b);
        const roundIndex = new Map(rounds.map((round, index) => [round, index]));
        const layout = () => {
            canvas.querySelectorAll('.bracket-connectors, .bracket-round-title').forEach((element) => element.remove());
            nodes.forEach((node) => { node.style.width = ''; });

            const cardWidth = Math.max(...nodes.map((node) => node.offsetWidth));
            const cardHeight = Math.max(...nodes.map((node) => node.offsetHeight));
            const base = cardHeight + GAP_Y;
            const columnWidth = cardWidth + GAP_X;
            const y = new Map();

            rounds.forEach((round) => {
                const inRound = matches.filter((match) => match.round === round).sort((a,b) => a.number-b.number);
                let leafIndex = 0;
                let previousY = -base;
                inRound.forEach((match) => {
                    const feeders = matches.filter((source) => source.winnerNext === match.id || source.loserNext === match.id);
                    const feederY = feeders.map((source) => y.get(source.id)).filter((value) => value !== undefined);
                    let proposed = feederY.length ? feederY.reduce((sum,value) => sum+value, 0) / feederY.length : leafIndex++ * base;
                    proposed = Math.max(proposed, previousY + base);
                    y.set(match.id, proposed);
                    previousY = proposed;
                });
            });

            const maxY = Math.max(0, ...y.values());
            const width = Math.max(viewport.clientWidth, rounds.length * columnWidth - GAP_X + ACTION_GUTTER + 28);
            const height = maxY + cardHeight + HEADER + 24;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;

            const roundTitle = (round, index) => {
                const type = viewport.dataset.bracketType;
                const hasGrandFinal = viewport.dataset.hasGrandFinal === 'true';
                const remaining = rounds.length - index;

                if (type === 'LOSERS') return `${LOSERS_ROUND_LABEL} ${index + 1}`;
                if (type === 'GRAND_FINAL') return rounds.length > 1 ? `${FINAL_LABEL} ${index + 1}` : FINAL_LABEL;
                if (type === 'WINNERS') {
                    if (hasGrandFinal && remaining === 1) return FINALS_LABEL;
                    if (remaining === 1 || (hasGrandFinal && remaining === 2)) return SEMIFINAL_LABEL;
                    return `${ROUND_LABEL} ${index + 1}`;
                }

                if (rounds.length === 1) return FINAL_LABEL;
                if (remaining === 1) return FINALS_LABEL;
                if (remaining === 2) return SEMIFINAL_LABEL;
                return `${ROUND_LABEL} ${index + 1}`;
            };

            rounds.forEach((round, index) => {
                const title = document.createElement('div');
                title.className = 'bracket-round-title';
                title.dataset.roundTone = ['pink', 'blue', 'orange', 'cyan', 'green', 'violet'][index % 6];
                title.style.left = `${index * columnWidth + 14}px`;
                title.style.top = '10px';
                title.style.width = `${cardWidth}px`;
                title.textContent = roundTitle(round, index);
                canvas.appendChild(title);
            });

            matches.forEach((match) => {
                match.node.style.left = `${(roundIndex.get(match.round) || 0) * columnWidth + 14}px`;
                match.node.style.top = `${(y.get(match.id) || 0) + HEADER}px`;
                match.node.style.width = `${cardWidth}px`;
            });

            const svg = document.createElementNS(SVG_NS, 'svg');
            svg.classList.add('bracket-connectors');
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

            matches.forEach((source) => {
                [source.winnerNext, source.loserNext].forEach((targetId) => {
                    if (!targetId || !ids.has(targetId)) return;
                    const target = matches.find((candidate) => candidate.id === targetId);
                    const x1 = (roundIndex.get(source.round) || 0) * columnWidth + 14 + cardWidth;
                    const y1 = (y.get(source.id) || 0) + HEADER + source.node.offsetHeight / 2;
                    const x2 = (roundIndex.get(target.round) || 0) * columnWidth + 14;
                    const y2 = (y.get(target.id) || 0) + HEADER + target.node.offsetHeight / 2;
                    const midX = x1 + (x2 - x1) / 2;
                    const path = document.createElementNS(SVG_NS, 'path');
                    path.setAttribute('class', 'bracket-connector');
                    path.setAttribute('d', `M ${x1} ${y1} H ${midX} V ${y2} H ${x2}`);
                    svg.appendChild(path);
                });
            });
            canvas.prepend(svg);
        };

        layout();
        let observedWidth = Math.round(viewport.getBoundingClientRect().width);
        let resizeFrame = null;
        new ResizeObserver(([entry]) => {
            const nextWidth = Math.round(entry.contentRect.width);
            if (nextWidth === observedWidth) return;
            observedWidth = nextWidth;
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(layout);
        }).observe(viewport);
    });
})();
</script>
@endpush
