@extends('layouts.app')
@section('title', __('ui.api_token').' · EasyKids')
@section('content')
<div class="page-head"><div><h1>{{ __('ui.api_token') }}</h1><div class="muted">{{ __('ui.api_token_help') }}</div></div><a class="btn secondary" href="{{ url('/api/docs') }}">{{ __('ui.api_docs') }}</a></div>
@if($plainTextToken)
<section class="alert success"><strong>{{ __('ui.token_created_once') }}</strong><div class="token-output"><code id="api-token-value">{{ $plainTextToken }}</code><button class="btn small secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('api-token-value').textContent)">Copy</button></div></section>
@endif
<section class="card">
    <div class="actions" style="justify-content:space-between">
        <div><strong>{{ $user->api_token_hash ? __('ui.token_active') : __('ui.token_inactive') }}</strong><div class="muted">{{ __('ui.last_used') }}: {{ $user->api_token_last_used_at?->diffForHumans() ?? __('ui.never') }}</div></div>
        <div class="actions"><form method="post" action="{{ route('admin.api-token.store') }}">@csrf<button class="btn">{{ __('ui.generate_token') }}</button></form>@if($user->api_token_hash)<form method="post" action="{{ route('admin.api-token.destroy') }}">@csrf @method('DELETE')<button class="btn danger">{{ __('ui.revoke_token') }}</button></form>@endif</div>
    </div>
</section>
@endsection
@push('styles')<style>.token-output{display:flex;align-items:center;gap:10px;margin-top:10px}.token-output code{display:block;max-width:100%;overflow:auto;padding:10px;border-radius:7px;background:#fff;color:#166534;white-space:nowrap}@media(max-width:680px){.token-output{align-items:stretch;flex-direction:column}.token-output .btn{width:100%}}</style>@endpush
