@extends('layouts.app')
@section('title', __('ui.login').' · EasyKids')
@section('content')
<div class="auth-shell">
    <section class="card auth-card">
        <h1>{{ __('ui.login') }}</h1>
        <p class="muted">{{ __('ui.login_help') }}</p>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="field"><label for="email">{{ __('ui.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
            <div class="field"><label for="password">{{ __('ui.password') }}</label><div class="password-control"><input id="password" type="password" name="password" required autocomplete="current-password"><button type="button" data-password-toggle data-show-label="{{ __('ui.show_password') }}" data-hide-label="{{ __('ui.hide_password') }}" aria-label="{{ __('ui.show_password') }}"><svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.7 6.2A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.6 6.6C4 8.3 2.5 12 2.5 12s3.5 6 9.5 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div></div>
            <label class="checkbox-row"><input type="checkbox" name="remember" value="1"><span>{{ __('ui.remember_me') }}</span></label>
            <button class="btn auth-submit">{{ __('ui.sign_in') }}</button>
        </form>
    </section>
</div>
@endsection
