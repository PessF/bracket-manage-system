@extends('layouts.app')
@section('title', __('ui.viewer_landing_title').' · EasyKids')
@section('content')
<section class="viewer-landing card">
    <div class="viewer-landing-mark">EK</div>
    <div>
        <p class="viewer-kicker">{{ __('ui.viewer_mode') }}</p>
        <h1>{{ __('ui.viewer_landing_title') }}</h1>
        <p>{{ __('ui.viewer_landing_help') }}</p>
        <div class="actions">
            <a class="btn" href="{{ route('tournaments.index') }}">{{ __('ui.back_to_tournaments') }}</a>
            <a class="btn secondary" href="{{ url('/login') }}">{{ __('ui.admin_login_short') }}</a>
        </div>
    </div>
</section>
@endsection
