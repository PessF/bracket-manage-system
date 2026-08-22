@extends('layouts.app')
@section('title', __('ui.title_bracket').' · '.$tournament->name)
@section('container-class', 'container-wide')

@push('styles')
<style>
    .bracket-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .bracket-hint { display:flex; align-items:center; gap:7px; color:var(--muted); font-size:14px; }
    .bracket-hint svg { width:15px; height:15px; }
    .bracket-section { margin:0 0 30px; }
    .bracket-section-head { display:flex; align-items:center; gap:10px; margin:0 0 10px; }
    .bracket-section-head h2 { margin:0; font-size:17px; }
    .bracket-count { color:var(--muted); font-size:13px; }
    .bracket-viewport { position:relative; overflow:auto; min-height:190px; border:1px solid var(--line); border-radius:10px; background-color:#fff; background-image:radial-gradient(#e4e4e7 .7px, transparent .7px); background-size:18px 18px; box-shadow:inset 0 1px 0 rgb(255 255 255 / .6); scrollbar-color:#d4d4d8 transparent; }
    .bracket-canvas { position:relative; min-width:100%; }
    .bracket-connectors { position:absolute; inset:0; z-index:1; overflow:visible; pointer-events:none; }
    .bracket-connector { fill:none; stroke:#cbd5e1; stroke-width:2; stroke-linejoin:round; vector-effect:non-scaling-stroke; }
    .bracket-round-title { position:absolute; top:0; height:44px; display:flex; align-items:center; color:#71717a; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.055em; }
    .bracket-match-node { position:absolute; z-index:2; width:270px; min-height:150px; padding:12px; border:1px solid #dfe1e5; border-radius:8px; background:#fff; box-shadow:0 1px 3px rgb(15 23 42 / .08), 0 1px 1px rgb(15 23 42 / .03); transition:border-color .16s, box-shadow .16s, transform .16s; }
    .bracket-match-node:hover { z-index:4; border-color:#a1a1aa; box-shadow:0 8px 22px rgb(15 23 42 / .11); transform:translateY(-1px); }
    .bracket-match-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:25px; margin-bottom:7px; color:var(--muted); font-size:12px; }
    .bracket-match-number { font-weight:700; color:#52525b; }
    .bracket-team { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; align-items:center; min-height:39px; padding:8px 9px; background:#fafafa; border:1px solid transparent; }
    .bracket-team + .bracket-team { margin-top:3px; }
    .bracket-team.winner { background:#f0fdf4; border-color:#dcfce7; color:#166534; font-weight:650; }
    .bracket-team.waiting { color:#a1a1aa; font-size:14px; }
    .bracket-team-name { display:flex; align-items:center; gap:7px; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .bracket-seed { display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 5px; border-radius:4px; background:#e4e4e7; color:#52525b; font:700 12px ui-monospace,monospace; }
    .bracket-score { min-width:24px; text-align:center; font:700 15px ui-monospace,SFMono-Regular,monospace; }
    .bracket-destinations { display:flex; align-items:center; gap:9px; margin-top:7px; color:#71717a; font-size:12px; }
    .bracket-destinations span { white-space:nowrap; }
    .bracket-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:12px; padding:14px; }
    .bracket-grid .bracket-match-node { position:relative; width:auto; height:auto !important; min-height:118px; left:auto !important; top:auto !important; }
    .bracket-legend { display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:13px; color:var(--muted); }
    .bracket-legend span { display:inline-flex; align-items:center; gap:5px; }
    .legend-line { display:inline-block; width:22px; border-top:2px solid #cbd5e1; }
    .legend-win { display:inline-block; width:12px; height:12px; border-radius:3px; background:#f0fdf4; border:1px solid #dcfce7; }
    @media(max-width:680px){.bracket-viewport{margin-left:-14px;margin-right:-14px;border-radius:0;border-left:0;border-right:0}.bracket-toolbar{align-items:flex-start;flex-direction:column}.bracket-match-node{width:260px}.bracket-round-title{font-size:12px}}
</style>
@endpush

@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $matchesUrl = $isPublicView ? route('public.tournaments.matches', ['tournament' => $tournament->public_token]) : route('tournaments.matches', $tournament);
@endphp
<div class="page-head">
    <div>
        <div class="actions" style="margin-bottom:5px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div>
        <div class="muted">{{ $tournament->competition }} · {{ $tournament->division }} · {{ __('ui.format_labels.'.$tournament->format->value) }}</div>
    </div>
    <a class="btn secondary" href="{{ $matchesUrl }}">{{ __('ui.match_list') }}</a>
</div>
@include('tournaments._tabs')
@includeWhen($isPublicView, 'tournaments._live_refresh')

<div class="bracket-toolbar">
    <div class="bracket-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-legend"><span><i class="legend-line"></i>{{ __('ui.advances_to') }}</span><span><i class="legend-win"></i>{{ __('ui.winner') }}</span><span>{{ __('ui.scroll_rounds') }}</span></div>
</div>

@forelse($matches as $type => $group)
@php
    $isGrid = in_array($type, ['ROUND_ROBIN', 'RANKING'], true);
@endphp
<section class="bracket-section">
    <div class="bracket-section-head"><h2>{{ __('ui.bracket_labels.'.$type) }}</h2><span class="bracket-count">{{ trans_choice('ui.match_count', $group->count(), ['count' => $group->count()]) }}</span></div>
    <div class="bracket-viewport {{ $isGrid ? 'bracket-grid' : '' }}" data-bracket-section data-bracket-type="{{ $type }}">
        @if(!$isGrid)<div class="bracket-canvas" data-bracket-canvas></div>@endif
        @foreach($group as $match)
        @php
            $nameA = $match->participantA?->team_name ?? $match->participantALabel();
            $nameB = $match->participantB?->team_name ?? $match->participantBLabel();
            $readyForScore = !$isPublicView && (auth()->user()?->isAdmin() ?? false)
                && $tournament->status === App\Enums\TournamentStatus::LIVE
                && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true)
                && !$match->is_bye && $match->participant_a_id && $match->participant_b_id;
        @endphp
        <article class="bracket-match-node"
            data-match-id="{{ $match->id }}" data-round="{{ $match->round_number }}" data-number="{{ $match->match_number }}"
            data-winner-next="{{ $match->winner_next_match_id }}" data-loser-next="{{ $match->loser_next_match_id }}">
            <div class="bracket-match-meta"><span class="bracket-match-number">{{ $type === 'GRAND_FINAL' ? __('ui.grand_final_match_number', ['number' => $loop->iteration]) : __('ui.match').' #'.$match->match_number }}</span><span class="badge {{ $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span></div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_a_id ? 'winner' : '' }} {{ !$match->participant_a_id ? 'waiting' : '' }}">
                <span class="bracket-team-name">@if($match->participantA?->seed_number)<i class="bracket-seed">{{ $match->participantA->seed_number }}</i>@endif<span title="{{ $nameA }}">{{ $nameA }}</span></span><span class="bracket-score">{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</span>
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_b_id ? 'winner' : '' }} {{ !$match->participant_b_id ? 'waiting' : '' }}">
                <span class="bracket-team-name">@if($match->participantB?->seed_number)<i class="bracket-seed">{{ $match->participantB->seed_number }}</i>@endif<span title="{{ $nameB }}">{{ $nameB }}</span></span><span class="bracket-score">{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</span>
            </div>
            @if($readyForScore)
            <form class="easy-score-form" method="post" action="{{ route('matches.results.store', [$tournament, $match]) }}">@csrf<div class="score-pair"><label class="score-team-control"><span title="{{ $nameA }}">{{ $nameA }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $nameA]) }}" type="number" min="0" step="any" name="score_a" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label><label class="score-team-control"><span title="{{ $nameB }}">{{ $nameB }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $nameB]) }}" type="number" min="0" step="any" name="score_b" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label></div><button class="btn small score-submit">{{ __('ui.confirm_score') }}</button></form>
            @endif
            @if($match->winnerNextMatch || $match->loserNextMatch)<div class="bracket-destinations">@if($match->winnerNextMatch)<span>{{ __('ui.winner_to_match', ['number' => $match->winnerNextMatch->match_number]) }}</span>@endif @if($match->loserNextMatch)<span>{{ __('ui.loser_to_match', ['number' => $match->loserNextMatch->match_number]) }}</span>@endif</div>@endif
        </article>
        @endforeach
    </div>
</section>
@empty
<div class="card empty">{{ __('ui.bracket_empty') }}</div>
@endforelse
@endsection

@push('scripts')
<script>
(() => {
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const HEADER = 44;
    const GAP_X = 86;
    const GAP_Y = 32;
    const ROUND_LABEL = @json(__('ui.round'));
    const FINAL_LABEL = @json(__('ui.final'));

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
        const width = Math.max(viewport.clientWidth, rounds.length * columnWidth - GAP_X + 28);
        const height = maxY + cardHeight + HEADER + 24;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        rounds.forEach((round, index) => {
            const title = document.createElement('div');
            title.className = 'bracket-round-title';
            title.style.left = `${index * columnWidth + 14}px`;
            title.style.width = `${cardWidth}px`;
            title.textContent = viewport.dataset.bracketType === 'GRAND_FINAL'
                ? `${FINAL_LABEL} ${index + 1}`
                : (rounds.length === 1 ? FINAL_LABEL : (index === rounds.length - 1 ? FINAL_LABEL : `${ROUND_LABEL} ${index + 1}`));
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
    });
})();
</script>
@endpush
