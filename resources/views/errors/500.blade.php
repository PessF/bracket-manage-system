@extends('layouts.app')
@section('title', __('ui.server_error_title').' · EasyKids')
@section('content')
<div class="auth-shell"><section class="card auth-card"><span class="badge">500</span><h1 style="margin-top:12px">{{ __('ui.server_error_title') }}</h1><p class="muted">{{ __('ui.server_error_help') }}</p><div class="actions"><a class="btn" href="{{ route('tournaments.index') }}">{{ __('ui.back_to_tournaments') }}</a></div></section></div>
@endsection
