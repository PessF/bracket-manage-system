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
            <div class="field"><label for="password">{{ __('ui.password') }}</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
            <label class="checkbox-row"><input type="checkbox" name="remember" value="1"><span>{{ __('ui.remember_me') }}</span></label>
            <button class="btn auth-submit">{{ __('ui.sign_in') }}</button>
        </form>
    </section>
</div>
@endsection
