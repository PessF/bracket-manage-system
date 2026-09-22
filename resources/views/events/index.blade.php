@extends('layouts.app')
@section('title', __('events.title').' · EasyKids')
@section('content')
<div class="page-head">
    <div><h1>{{ __('events.title') }}</h1><p class="muted">{{ __('events.help') }}</p></div>
    @if(auth()->user()?->isAdmin())
        <a class="btn" href="{{ route('events.create') }}">{{ __('events.new') }}</a>
    @endif
</div>
<form class="filter-bar" method="get" role="search">
    <div class="field"><label for="q">{{ __('events.name') }}</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <button class="btn secondary">{{ __('ui.filter') }}</button>
</form>
<div class="grid tournament-grid">
    @forelse($events as $event)
        <a class="card tournament-card" href="{{ route('events.show', $event) }}">
            <h2>{{ $event->name }}</h2>
            @if($event->venue)<p class="muted">{{ $event->venue }}</p>@endif
            @if($event->starts_on)<p class="muted">{{ $event->starts_on->format('d/m/Y') }}@if($event->ends_on) – {{ $event->ends_on->format('d/m/Y') }}@endif</p>@endif
            @if($event->description)<p>{{ Illuminate\Support\Str::limit($event->description, 160) }}</p>@endif
            <span class="badge">{{ __('events.competitions', ['count' => $event->competitions_count]) }}</span>
        </a>
    @empty
        <p class="muted">{{ __('events.empty') }}</p>
    @endforelse
</div>
{{ $events->links() }}
@endsection
