@extends('layouts.app')
@section('title', __('ui.tournaments').' · EasyKids')
@section('content')
<div class="page-head"><div><h1>{{ __('ui.tournaments') }}</h1><div class="muted">{{ __('ui.tournaments_help') }}</div></div>@if(auth()->user()?->isAdmin())<a class="btn" href="{{ route('tournaments.create') }}">{{ __('ui.new_tournament') }}</a>@endif</div>
@if(!(auth()->user()?->isAdmin() ?? false))<div class="alert" style="background:#f8fafc;border-color:#e2e8f0;color:#475569">{{ __('ui.read_only_notice') }}</div>@endif
<form class="card inline-form" method="get"><div class="field"><label for="status">{{ __('ui.status') }}</label><select id="status" name="status"><option value="">{{ __('ui.all_statuses') }}</option>@foreach(App\Enums\TournamentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>@endforeach</select></div><button class="btn secondary">{{ __('ui.filter') }}</button></form>
<div class="grid">
@forelse($tournaments as $tournament)
<article class="card"><div class="actions" style="justify-content:space-between"><span class="badge {{ $tournament->status->value }}">{{ $tournament->status->value }}</span><span class="muted">{{ str_replace('_',' ', $tournament->format->value) }}</span></div><h2><a href="{{ route('tournaments.show', $tournament) }}">{{ $tournament->name }}</a></h2><p>{{ $tournament->competition }} · {{ $tournament->division }}</p><div class="stats"><div class="stat"><strong>{{ $tournament->participants_count }}</strong><span class="muted">{{ __('ui.teams') }}</span></div><div class="stat"><strong>{{ $tournament->matches_count }}</strong><span class="muted">{{ __('ui.matches') }}</span></div></div></article>
@empty<div class="card empty">{{ __('ui.no_tournaments') }}</div>@endforelse
</div>
<div>{{ $tournaments->links() }}</div>
@endsection
