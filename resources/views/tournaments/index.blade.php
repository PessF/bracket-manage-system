@extends('layouts.app')
@section('title', ($event?->name ?? __('ui.tournaments')).' · EasyKids')
@section('content')
@php
    $isAdmin = auth()->user()?->isAdmin() ?? false;
    $canBrowseTournaments = $canBrowseTournaments ?? true;
    $canReorder = $isAdmin && $tournaments->count() > 1 && !request()->filled('q') && !request()->filled('status');
@endphp

<section class="dashboard-hero" aria-labelledby="dashboard-title">
    <div class="page-head">
        <div>
            <span class="dashboard-eyebrow">EasyKids Robotics</span>
            @if($event)<a href="{{ route('events.index') }}">{{ __('events.title') }}</a>@endif
            <h1 id="dashboard-title">{{ $event?->name ?? __('ui.tournaments') }}</h1>
            <div class="muted">{{ $event?->description ?? __('ui.tournaments_help') }}</div>
        </div>
        @if($isAdmin)
            <div class="actions">
                @if($event)
                    <a class="btn secondary" href="{{ route('events.edit', $event) }}">{{ __('events.edit') }}</a>
                    <form method="post" action="{{ route('events.destroy', $event) }}" data-confirm="{{ __('events.delete_confirm') }}">@csrf @method('delete')<button class="btn danger">{{ __('ui.delete_button') }}</button></form>
                @endif
                <a class="btn dashboard-create" href="{{ route('tournaments.create', $event ? ['event_id' => $event->id] : []) }}"><span aria-hidden="true">+</span> {{ __('ui.new_tournament') }}</a>
            </div>
        @endif
    </div>
    <div class="dashboard-stats" aria-label="{{ __('ui.dashboard_total') }}">
        <div class="dashboard-stat"><span class="dashboard-stat-icon total" aria-hidden="true"></span><span><strong>{{ $dashboardCounts['total'] }}</strong><small>{{ __('ui.dashboard_total') }}</small></span></div>
        <div class="dashboard-stat"><span class="dashboard-stat-icon live" aria-hidden="true"></span><span><strong>{{ $dashboardCounts['live'] }}</strong><small>{{ __('ui.tournament_status_labels.LIVE') }}</small></span></div>
        <div class="dashboard-stat"><span class="dashboard-stat-icon ready" aria-hidden="true"></span><span><strong>{{ $dashboardCounts['ready'] }}</strong><small>{{ __('ui.tournament_status_labels.READY') }}</small></span></div>
        <div class="dashboard-stat"><span class="dashboard-stat-icon complete" aria-hidden="true"></span><span><strong>{{ $dashboardCounts['completed'] }}</strong><small>{{ __('ui.tournament_status_labels.COMPLETED') }}</small></span></div>
    </div>
</section>

@if($canBrowseTournaments)
    <form class="filter-bar dashboard-filter" method="get" role="search">
        <div class="field search-field">
            <label for="q">{{ __('ui.search_competitions') }}</label>
            <div class="search-control">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('ui.search_competitions_placeholder') }}" maxlength="100" autocomplete="off" data-competition-search>
            </div>
        </div>
        <div class="field status-field">
            <label for="status">{{ __('ui.status') }}</label>
            <select id="status" name="status" data-native-select>
                <option value="">{{ __('ui.all_statuses') }}</option>
                @foreach(App\Enums\TournamentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('ui.tournament_status_labels.'.$status->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn">{{ __('ui.filter') }}</button>
            @if(request()->filled('q') || request()->filled('status'))
                <a class="btn secondary" href="{{ $event ? route('events.show', $event) : route('tournaments.index') }}">{{ __('ui.clear_filters') }}</a>
            @endif
        </div>
    </form>
@endif

<div class="dashboard-results-head">
    <strong>{{ __('ui.showing_competitions', ['count' => $tournaments->total()]) }}</strong>
    @if($canReorder)
        <span class="dashboard-order-hint">{{ __('ui.dashboard_order_hint') }}</span>
    @endif
</div>
<div class="grid tournament-grid" @if($canReorder) data-tournament-sort data-order-url="{{ route('tournaments.display-order.update') }}" @endif>
    @forelse($tournaments as $tournament)
        @php
            $isAdvancedTournament = $tournament->structure === App\Enums\TournamentStructure::ADVANCED;
            $advancedConfig = $tournament->advanced_config ?? [];
            $groupFormat = $advancedConfig['group_format'] ?? null;
            $playoffFormat = $advancedConfig['playoff_format'] ?? null;
            $tournamentUrl = $isAdmin
                ? route('tournaments.show', $tournament)
                : route($tournament->format === App\Enums\TournamentFormat::RANKING ? 'tournaments.results' : 'tournaments.bracket', $tournament);
            $progress = $tournament->competitionProgressPercentage();
        @endphp
        @if($canReorder)
        <article class="tournament-card-shell" draggable="true" data-tournament-card data-tournament-id="{{ $tournament->id }}">
        @endif
        <a class="card tournament-card" href="{{ $tournamentUrl }}">
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
            <div class="competition-progress" aria-label="{{ __('ui.competition_progress') }}">
                <div class="competition-progress-head"><span>{{ __('ui.competition_progress') }}</span><strong>{{ __('ui.progress_percent', ['percent' => $progress]) }}</strong></div>
                <div class="competition-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width:{{ $progress }}%"></span></div>
            </div>
            <span class="card-link-label">{{ $isAdmin ? __('ui.manage_competition') : __('ui.open_competition') }} →</span>
        </a>
        @if($canReorder)
            <div class="card-order-controls" aria-label="{{ $tournament->name }}">
                <span class="drag-handle" aria-hidden="true" title="{{ __('ui.dashboard_order_hint') }}"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                <button type="button" data-order-move="-1" aria-label="{{ __('ui.move_up') }}: {{ $tournament->name }}" title="{{ __('ui.move_up') }}" @disabled($loop->first)><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 14 5-5 5 5"/></svg></button>
                <button type="button" data-order-move="1" aria-label="{{ __('ui.move_down') }}: {{ $tournament->name }}" title="{{ __('ui.move_down') }}" @disabled($loop->last)><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></button>
            </div>
        </article>
        @endif
    @empty
        <div class="card empty dashboard-empty">
            <span class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg></span>
            @if(request()->filled('q') || request()->filled('status'))
                <strong>{{ __('ui.filtered_empty_title') }}</strong>
                <span>{{ __('ui.filtered_empty_help') }}</span>
                <a class="btn secondary" href="{{ route('tournaments.index') }}">{{ __('ui.clear_filters') }}</a>
            @else
                <strong>{{ $canBrowseTournaments ? __('ui.no_tournaments') : __('ui.share_link_required') }}</strong>
                @if($isAdmin)<a class="btn" href="{{ route('tournaments.create') }}">{{ __('ui.new_tournament') }}</a>@endif
            @endif
        </div>
    @endforelse
</div>
<div class="sr-only" aria-live="polite" data-order-status data-success="{{ __('ui.order_saved') }}" data-error="{{ __('ui.order_failed') }}"></div>

<div>{{ $tournaments->links() }}</div>
@endsection
