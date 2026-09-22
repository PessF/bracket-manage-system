@extends('layouts.app')
@section('title', __('events.'.($event->exists ? 'edit' : 'new')))
@section('content')
<div class="page-head"><h1>{{ __('events.'.($event->exists ? 'edit' : 'new')) }}</h1><a href="{{ route('events.index') }}">{{ __('events.title') }}</a></div>
<form class="card" method="post" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}">
    @csrf
    @if($event->exists) @method('put') @endif
    <div class="form-grid">
        <div class="field full"><label for="name">{{ __('events.name') }}</label><input id="name" name="name" required maxlength="200" value="{{ old('name', $event->name) }}"></div>
        <div class="field full"><label for="description">{{ __('events.description') }}</label><textarea id="description" name="description" maxlength="10000">{{ old('description', $event->description) }}</textarea></div>
        <div class="field full"><label for="venue">{{ __('ui.venue') }}</label><input id="venue" name="venue" maxlength="255" value="{{ old('venue', $event->venue) }}"></div>
        <div class="field"><label for="starts_on">{{ __('events.starts_on') }}</label><input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $event->starts_on?->format('Y-m-d')) }}"></div>
        <div class="field"><label for="ends_on">{{ __('events.ends_on') }}</label><input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', $event->ends_on?->format('Y-m-d')) }}"></div>
    </div>
    <button class="btn">{{ __('ui.save') }}</button>
</form>
@endsection
