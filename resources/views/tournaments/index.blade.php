@extends('layouts.app')
@section('title', __('ui.tournaments').' · EasyKids')
@section('content')
@php
    $isAdmin = auth()->user()?->isAdmin() ?? false;
    $canBrowseTournaments = $canBrowseTournaments ?? true;
@endphp

<div class="page-head">
    <div>
        <h1>{{ $isAdmin ? __('ui.admin_dashboard') : __('ui.tournaments') }}</h1>
        <div class="muted">{{ $isAdmin ? __('ui.admin_dashboard_help') : __('ui.tournaments_help') }}</div>
    </div>
    @if($isAdmin)
        <a class="btn" href="{{ route('tournaments.create') }}">+ {{ __('ui.new_tournament') }}</a>
    @endif
</div>

@if($canBrowseTournaments)
    <form class="filter-bar inline-form" method="get">
        <div class="field">
            <label for="status">{{ __('ui.status') }}</label>
            <select id="status" name="status">
                <option value="">{{ __('ui.all_statuses') }}</option>
                @foreach(App\Enums\TournamentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('ui.tournament_status_labels.'.$status->value) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn secondary">{{ __('ui.filter') }}</button>
    </form>
@endif

@if($isAdmin && $tournaments->count() > 1)
    <div class="dashboard-order-hint">{{ __('ui.dashboard_order_hint') }}</div>
@endif
<div class="grid tournament-grid" @if($isAdmin && $tournaments->count() > 1) data-tournament-sort data-order-url="{{ route('tournaments.display-order.update') }}" @endif>
    @forelse($tournaments as $tournament)
        @php
            $isAdvancedTournament = $tournament->structure === App\Enums\TournamentStructure::ADVANCED;
            $advancedConfig = $tournament->advanced_config ?? [];
            $groupFormat = $advancedConfig['group_format'] ?? null;
            $playoffFormat = $advancedConfig['playoff_format'] ?? null;
            $tournamentUrl = $isAdmin
                ? route('tournaments.show', $tournament)
                : route('tournaments.bracket', $tournament);
        @endphp
        <a class="card tournament-card" href="{{ $tournamentUrl }}" @if($isAdmin && $tournaments->count() > 1) draggable="true" data-tournament-card data-tournament-id="{{ $tournament->id }}" @endif>
            <div class="actions tournament-card-badges">
                <span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span>
                <span class="badge structure-badge {{ $tournament->structure->value }}">{{ __('ui.structure_labels.'.$tournament->structure->value) }}</span>
                @if($isAdvancedTournament)
                    @if($groupFormat)
                    <span class="badge stage-format-badge group {{ $groupFormat }}">{{ __('ui.group_stage_badge', ['format' => __('ui.format_labels.'.$groupFormat)]) }}</span>
                    @endif
                    @if($playoffFormat)
                    <span class="badge stage-format-badge final {{ $playoffFormat }}">{{ __('ui.grand_final_badge', ['format' => __('ui.format_labels.'.$playoffFormat)]) }}</span>
                    @endif
                @else
                    <span class="badge format-badge {{ $tournament->format->value }}">{{ __('ui.format_labels.'.$tournament->format->value) }}</span>
                @endif
            </div>
            <h2>{{ $tournament->name }}</h2>
            <p>{{ $tournament->competition }} · {{ $tournament->division }}</p>
            @if($tournament->competition_date || $tournament->bracket_schedule_start_time)
            <div class="tournament-time-badge" style="display:inline-flex; align-items:center; gap:6px; margin:2px 0 10px; padding:4px 9px; border-radius:5px; background:var(--soft); color:var(--muted); font-size:12px; font-weight:600;">
                <svg style="width:13px; height:13px; flex:0 0 auto; stroke:currentColor; fill:none; stroke-width:2;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>
                    @if($tournament->competition_date)
                        {{ $tournament->competition_date->translatedFormat('j M Y · H:i') }} {{ __('ui.time_suffix') }}
                    @endif
                    @if($tournament->bracket_schedule_start_time)
                        @if($tournament->competition_date) · @endif
                        {{ __('ui.start_time') }}: {{ substr((string) $tournament->bracket_schedule_start_time, 0, 5) }} {{ __('ui.time_suffix') }}
                    @endif
                </span>
            </div>
            @endif
            <div class="stats">
                <div class="stat"><strong>{{ $tournament->participants_count }}</strong><span class="muted">{{ __('ui.teams') }}</span></div>
                <div class="stat"><strong>{{ $tournament->matches_count }}</strong><span class="muted">{{ __('ui.matches') }}</span></div>
            </div>
            <span class="card-link-label">{{ $isAdmin ? __('ui.manage_competition') : __('ui.open_competition') }} →</span>
        </a>
    @empty
        <div class="card empty">{{ $canBrowseTournaments ? __('ui.no_tournaments') : __('ui.share_link_required') }}</div>
    @endforelse
</div>

<div>{{ $tournaments->links() }}</div>
@endsection
