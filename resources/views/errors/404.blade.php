@extends('layouts.app')
@section('title', __('ui.not_found_title').' · EasyKids')
@section('content')
<div class="auth-shell"><section class="card auth-card"><span class="badge">404</span><h1 style="margin-top:12px">{{ __('ui.not_found_title') }}</h1><p class="muted">{{ __('ui.not_found_help') }}</p><div class="actions"><a class="btn" href="{{ route('tournaments.index') }}">{{ __('ui.back_to_tournaments') }}</a>@guest<a class="btn secondary" href="{{ route('login') }}">{{ __('ui.admin_login_short') }}</a>@endguest</div></section></div>
@endsection
