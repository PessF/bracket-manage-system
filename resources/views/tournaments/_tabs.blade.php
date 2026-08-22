<nav class="tabs">
    <a class="{{ request()->routeIs('tournaments.show') ? 'active' : '' }}" href="{{ route('tournaments.show', $tournament) }}">{{ __('ui.overview_participants') }}</a>
    <a class="{{ request()->routeIs('tournaments.bracket') ? 'active' : '' }}" href="{{ route('tournaments.bracket', $tournament) }}">{{ __('ui.bracket_competition') }}</a>
    <a class="{{ request()->routeIs('tournaments.matches') ? 'active' : '' }}" href="{{ route('tournaments.matches', $tournament) }}">{{ __('ui.matches') }}</a>
    <a class="{{ request()->routeIs('tournaments.results') ? 'active' : '' }}" href="{{ route('tournaments.results', $tournament) }}">{{ __('ui.results') }}</a>
    <a class="{{ request()->routeIs('tournaments.settings', 'tournaments.edit') ? 'active' : '' }}" href="{{ route('tournaments.settings', $tournament) }}">{{ __('ui.settings') }}</a>
</nav>
