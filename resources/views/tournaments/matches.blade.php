@extends('layouts.app')
@section('title', __('ui.title_matches').' · '.$tournament->name)
@push('styles')
<style>.match-card-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:9px}.match-team-row{display:flex;justify-content:space-between;gap:12px;padding:8px 9px;background:#fafafa;border-radius:6px}.match-team-row+.match-team-row{margin-top:4px}.match-team-row.winner{background:#f0fdf4;color:#166534;font-weight:650}.match-winner{margin-top:9px;padding-top:8px;border-top:1px solid var(--line);font-size:12px}</style>
@endpush
@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $bracketUrl = $isPublicView ? route('public.tournaments.bracket', ['tournament' => $tournament->public_token]) : route('tournaments.bracket', $tournament);
@endphp
<div class="page-head"><div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div><div class="muted">{{ $isPublicView ? __('ui.live_match_results') : __('ui.match_scoring') }}</div></div><a class="btn secondary" href="{{ $bracketUrl }}">{{ __('ui.open_bracket') }}</a></div>
@include('tournaments._tabs')
@includeWhen($isPublicView, 'tournaments._live_refresh')
<div class="match-grid">
@forelse($matches as $match)
@php($canScore = !$isPublicView && (auth()->user()?->isAdmin() ?? false) && $tournament->status === App\Enums\TournamentStatus::LIVE && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true) && !$match->is_bye && $match->participant_a_id && $match->participant_b_id)
<article class="match">
    <div class="match-card-head"><span class="muted">{{ __('ui.match') }} #{{ $match->match_number }} · {{ __('ui.bracket_labels.'.$match->bracket_type->value) }} · {{ __('ui.round') }} {{ $match->round_number }}</span><span class="badge {{ $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_a_id ? 'winner' : '' }}"><span>{{ $match->participantA?->team_name ?? $match->participantALabel() }}</span><strong>{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</strong></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_b_id ? 'winner' : '' }}"><span>{{ $match->participantB?->team_name ?? $match->participantBLabel() }}</span><strong>{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</strong></div>
    @if($canScore)
    <form class="easy-score-form" method="post" action="{{ route('matches.results.store', [$tournament, $match]) }}">@csrf
        <div class="score-pair">
            <label class="score-team-control"><span>{{ $match->participantA->team_name }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $match->participantA->team_name]) }}" type="number" min="0" step="any" name="score_a" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
            <label class="score-team-control"><span>{{ $match->participantB->team_name }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input aria-label="{{ __('ui.score_for_team', ['team' => $match->participantB->team_name]) }}" type="number" min="0" step="any" name="score_b" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
        </div>
        <button class="btn small score-submit">{{ __('ui.confirm_score') }}</button>
    </form>
    @elseif($match->winner)<div class="match-winner muted">{{ __('ui.winner') }}: <strong>{{ $match->winner->team_name }}</strong></div>@endif
</article>
@empty<div class="card empty">{{ __('ui.matches_empty') }}</div>@endforelse
</div>
@endsection
