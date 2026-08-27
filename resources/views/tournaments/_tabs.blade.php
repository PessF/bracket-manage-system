@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $isAdmin = ! $isPublicView && (auth()->user()?->isAdmin() ?? false);
    $usePublicRoutes = $isPublicView && !$isAdmin;
    $routePrefix = $usePublicRoutes ? 'public.tournaments.' : 'tournaments.';
    $routeParameter = $usePublicRoutes ? ['tournament' => $tournament->public_token] : $tournament;
@endphp

@if($isPublicView && !$isAdmin)
<nav class="viewer-only-nav" aria-label="{{ __('ui.tournament_navigation') }}">
    @if(request()->routeIs('public.tournaments.results'))
        <a class="btn record-result-button" href="{{ route('public.tournaments.show', $routeParameter) }}">{{ __('ui.bracket_competition') }}</a>
    @endif
</nav>
@else
<nav class="tabs admin-control-tabs" aria-label="{{ __('ui.tournament_navigation') }}">
    <a class="all-tournaments-tab" href="{{ route('tournaments.index') }}">{{ __('ui.all_tournaments') }}</a>
    <a class="{{ request()->routeIs($routePrefix.'show') ? 'active' : '' }}" href="{{ route($routePrefix.'show', $routeParameter) }}">{{ __('ui.overview_participants') }}</a>
    <a class="{{ request()->routeIs($routePrefix.'bracket', 'public.tournaments.bracket', 'public.tournaments.show') ? 'active' : '' }}" href="{{ route($routePrefix.'bracket', $routeParameter) }}">{{ __('ui.bracket_competition') }}</a>
    <a class="{{ request()->routeIs($routePrefix.'results') ? 'active' : '' }}" href="{{ route($routePrefix.'results', $routeParameter) }}">{{ __('ui.results') }}</a>
    @if($isAdmin)
    @if($tournament->structure === App\Enums\TournamentStructure::ADVANCED)
    <a class="{{ request()->routeIs('tournaments.groups.*') ? 'active' : '' }}" href="{{ route('tournaments.groups.edit', $tournament) }}">{{ __('ui.group_assignments') }}</a>
    @endif
    <a class="{{ request()->routeIs('tournaments.settings', 'tournaments.edit') ? 'active' : '' }}" href="{{ route('tournaments.settings', $tournament) }}">{{ __('ui.settings') }}</a>
    @if($tournament->public_token)
    <a href="{{ route('public.tournaments.bracket', ['tournament' => $tournament->public_token]) }}" target="_blank" rel="noopener">{{ __('ui.open_view_page') }}</a>
    @endif
    @endif
</nav>
@endif
