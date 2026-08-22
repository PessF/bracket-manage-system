@extends('layouts.app')
@section('title', __('ui.admin_setup').' · EasyKids')
@section('content')
<div class="auth-shell">
    <section class="card auth-card auth-card-wide">
        <h1>{{ __('ui.admin_setup') }}</h1>
        <p class="muted">{{ __('ui.admin_setup_help') }}</p>
        <form method="post" action="{{ route('admin.setup.store') }}">
            @csrf
            <div class="field"><label for="setup_token">{{ __('ui.setup_token') }}</label><input id="setup_token" type="password" name="setup_token" required autocomplete="off"></div>
            <div class="form-grid">
                <div class="field"><label for="name">{{ __('ui.name') }}</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
                <div class="field"><label for="email">{{ __('ui.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></div>
                <div class="field"><label for="password">{{ __('ui.password') }}</label><input id="password" type="password" name="password" required autocomplete="new-password"></div>
                <div class="field"><label for="password_confirmation">{{ __('ui.confirm_password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
            </div>
            <button class="btn auth-submit">{{ __('ui.create_admin') }}</button>
        </form>
    </section>
</div>
@endsection
