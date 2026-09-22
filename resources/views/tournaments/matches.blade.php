@extends('layouts.app')
@section('title', __('ui.title_matches').' · '.$tournament->name)
@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $bracketUrl = $isPublicView ? route('public.tournaments.bracket', ['tournament' => $tournament->public_token]) : route('tournaments.bracket', $tournament);
@endphp
<div class="page-head"><div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div><div class="muted">{{ $isPublicView ? __('ui.live_match_results') : __('ui.match_scoring') }}</div></div><a class="btn secondary" href="{{ $bracketUrl }}">{{ __('ui.open_bracket') }}</a></div>
@include('tournaments._tabs')
@includeWhen($tournament->status === App\Enums\TournamentStatus::LIVE, 'tournaments._live_refresh', [
    'interval' => 1,
    'refreshTarget' => '[data-live-matches]',
])
<div data-live-matches><div class="match-grid">
@forelse($matches as $match)
@php
@endphp
<article class="match" id="match-{{ $match->id }}">
    <div class="match-card-head"><span class="muted">{{ __('ui.match') }} #{{ $match->match_number }} · @if($match->bracket_type === App\Enums\BracketType::GRAND_FINAL){{ __('ui.grand_final_match_number', ['number' => $grandFinalRounds[$match->id]]) }}@else{{ __('ui.bracket_labels.'.$match->bracket_type->value) }} · {{ __('ui.round') }} {{ $match->round_number }}@endif</span><span class="badge {{ $match->is_bye ? 'BYE' : $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_a_id ? 'winner' : '' }}"><span class="actions"><i class="match-side red">{{ __('ui.red_side') }}</i>{{ $match->participantA?->team_name ?? $match->participantALabel() }}</span><strong>{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</strong></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_b_id ? 'winner' : '' }}"><span class="actions"><i class="match-side blue">{{ __('ui.blue_side') }}</i>{{ $match->participantB?->team_name ?? $match->participantBLabel() }}</span><strong>{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</strong></div>
    @if($match->winner)<div class="match-winner muted">{{ __('ui.winner') }}: <strong>{{ $match->winner->team_name }}</strong></div>@endif
</article>
@empty<div class="card empty">{{ __('ui.matches_empty') }}</div>@endforelse
</div></div>
@endsection
