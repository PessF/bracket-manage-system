@extends('layouts.app')
@section('title', __('ui.forbidden_title').' · EasyKids')
@section('content')
<div class="auth-shell"><section class="card auth-card"><span class="badge">403</span><h1 style="margin-top:12px">{{ __('ui.forbidden_title') }}</h1><p class="muted">{{ auth()->check() ? __('ui.forbidden_logged_in_help', ['role' => __('ui.role_labels.'.auth()->user()->role->value)]) : __('ui.forbidden_guest_help') }}</p><div class="actions"><a class="btn" href="{{ route('tournaments.index') }}">{{ __('ui.back_to_tournaments') }}</a>@guest<a class="btn secondary" href="{{ route('login') }}">{{ __('ui.login') }}</a>@endguest</div></section></div>
@endsection
