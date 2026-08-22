@extends('layouts.app')
@section('title', 'Matches · '.$tournament->name)
@push('styles')
<style>.match-card-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:9px}.match-team-row{display:flex;justify-content:space-between;gap:12px;padding:8px 9px;background:#fafafa;border-radius:6px}.match-team-row+.match-team-row{margin-top:4px}.match-team-row.winner{background:#f0fdf4;color:#166534;font-weight:650}.match-winner{margin-top:9px;padding-top:8px;border-top:1px solid var(--line);font-size:12px}</style>
@endpush
@section('content')
<div class="page-head"><div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ $tournament->status->value }}</span></div><div class="muted">{{ __('ui.match_scoring') }}</div></div><a class="btn secondary" href="{{ route('tournaments.bracket', $tournament) }}">{{ __('ui.open_bracket') }}</a></div>
@include('tournaments._tabs')
<div class="match-grid">
@forelse($matches as $match)
@php($canScore = (auth()->user()?->isAdmin() ?? false) && $tournament->status === App\Enums\TournamentStatus::LIVE && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true) && !$match->is_bye && $match->participant_a_id && $match->participant_b_id)
<article class="match">
    <div class="match-card-head"><span class="muted">{{ __('ui.match') }} #{{ $match->match_number }} · {{ str_replace('_',' ', $match->bracket_type->value) }} · {{ __('ui.round') }} {{ $match->round_number }}</span><span class="badge {{ $match->status->value }}">{{ $match->is_bye ? 'BYE' : $match->status->value }}</span></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_a_id ? 'winner' : '' }}"><span>{{ $match->participantA?->team_name ?? $match->participant_a_label }}</span><strong>{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</strong></div>
    <div class="match-team-row {{ $match->winner_id === $match->participant_b_id ? 'winner' : '' }}"><span>{{ $match->participantB?->team_name ?? $match->participant_b_label }}</span><strong>{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</strong></div>
    @if($canScore)
    <form class="easy-score-form" method="post" action="{{ route('matches.results.store', [$tournament, $match]) }}">@csrf
        <div class="score-pair">
            <label class="score-team-control"><span>{{ $match->participantA->team_name }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="Subtract one point">−</button><input aria-label="Score for {{ $match->participantA->team_name }}" type="number" min="0" step="any" name="score_a" value="0" required><button type="button" data-score-step="1" aria-label="Add one point">+</button></span></label>
            <label class="score-team-control"><span>{{ $match->participantB->team_name }}</span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="Subtract one point">−</button><input aria-label="Score for {{ $match->participantB->team_name }}" type="number" min="0" step="any" name="score_b" value="0" required><button type="button" data-score-step="1" aria-label="Add one point">+</button></span></label>
        </div>
        <button class="btn small score-submit">{{ __('ui.confirm_score') }}</button>
    </form>
    @elseif($match->winner)<div class="match-winner muted">{{ __('ui.winner') }}: <strong>{{ $match->winner->team_name }}</strong></div>@endif
</article>
@empty<div class="card empty">Start the tournament to generate matches.</div>@endforelse
</div>
@endsection
