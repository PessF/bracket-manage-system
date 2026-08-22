@extends('layouts.app')
@section('title', __('ui.tournaments').' · EasyKids')
@section('content')
@php($isAdmin = auth()->user()?->isAdmin() ?? false)
<div class="page-head"><div><h1>{{ $isAdmin ? __('ui.admin_dashboard') : __('ui.tournaments') }}</h1><div class="muted">{{ $isAdmin ? __('ui.admin_dashboard_help') : __('ui.tournaments_help') }}</div></div>@if($isAdmin)<a class="btn" href="{{ route('tournaments.create') }}">+ {{ __('ui.new_tournament') }}</a>@endif</div>
@if($isAdmin)
<section class="card admin-welcome"><div><span class="badge LIVE">{{ __('ui.admin_mode_active') }}</span><h2>{{ __('ui.admin_quick_start') }}</h2><p class="muted">{{ __('ui.admin_quick_start_help') }}</p></div><div class="actions"><a class="btn" href="{{ route('tournaments.create') }}">{{ __('ui.create_tournament') }}</a><a class="btn secondary" href="{{ route('admin.users.index') }}">{{ __('ui.manage_users') }}</a><a class="btn secondary" href="{{ route('admin.api-token.show') }}">{{ __('ui.api_access') }}</a></div></section>
@else<div class="alert view-only-banner"><strong>{{ auth()->check() ? __('ui.viewer_account_active') : __('ui.viewer_mode') }}</strong><div>{{ __('ui.share_only_notice') }}</div></div>@endif
@if(auth()->user()?->isAdmin())<form class="card inline-form" method="get"><div class="field"><label for="status">{{ __('ui.status') }}</label><select id="status" name="status"><option value="">{{ __('ui.all_statuses') }}</option>@foreach(App\Enums\TournamentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('ui.tournament_status_labels.'.$status->value) }}</option>@endforeach</select></div><button class="btn secondary">{{ __('ui.filter') }}</button></form>@endif
<div class="grid">
@forelse($tournaments as $tournament)
<a class="card tournament-card" href="{{ route('tournaments.show', $tournament) }}"><div class="actions" style="justify-content:space-between"><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span><span class="muted">{{ __('ui.format_labels.'.$tournament->format->value) }}</span></div><h2>{{ $tournament->name }}</h2><p>{{ $tournament->competition }} · {{ $tournament->division }}</p><div class="stats"><div class="stat"><strong>{{ $tournament->participants_count }}</strong><span class="muted">{{ __('ui.teams') }}</span></div><div class="stat"><strong>{{ $tournament->matches_count }}</strong><span class="muted">{{ __('ui.matches') }}</span></div></div><span class="card-link-label">{{ $isAdmin ? __('ui.manage_competition') : __('ui.open_competition') }} →</span></a>
@empty<div class="card empty">{{ $isAdmin ? __('ui.no_tournaments') : __('ui.share_link_required') }}</div>@endforelse
</div>
<div>{{ $tournaments->links() }}</div>
@endsection
