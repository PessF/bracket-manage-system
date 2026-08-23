@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $routePrefix = $isPublicView ? 'public.tournaments.' : 'tournaments.';
    $routeParameter = $isPublicView ? ['tournament' => $tournament->public_token] : $tournament;
@endphp
@if($isPublicView)
<nav class="viewer-only-nav" aria-label="{{ __('ui.tournament_navigation') }}">
    <a href="{{ route('public.tournaments.bracket', $routeParameter) }}">← {{ __('ui.back_to_live_bracket') }}</a>
</nav>
@else
<nav class="tabs" aria-label="{{ __('ui.tournament_navigation') }}">
    <a class="{{ request()->routeIs($routePrefix.'show') ? 'active' : '' }}" href="{{ route($routePrefix.'show', $routeParameter) }}">{{ __('ui.overview_participants') }}</a>
    <a class="{{ request()->routeIs($routePrefix.'bracket') ? 'active' : '' }}" href="{{ route($routePrefix.'bracket', $routeParameter) }}">{{ __('ui.bracket_competition') }}</a>
    <a class="{{ request()->routeIs($routePrefix.'results') ? 'active' : '' }}" href="{{ route($routePrefix.'results', $routeParameter) }}">{{ __('ui.results') }}</a>
    @if(!$isPublicView && auth()->user()?->isAdmin())<a class="{{ request()->routeIs('tournaments.settings', 'tournaments.edit') ? 'active' : '' }}" href="{{ route('tournaments.settings', $tournament) }}">{{ __('ui.settings') }}</a>@endif
</nav>
@endif
