@extends('layouts.app')
@section('title', __('ui.manage_users').' · EasyKids')
@section('content')
<div class="page-head"><div><h1>{{ __('ui.manage_users') }}</h1><div class="muted">{{ __('ui.manage_users_help') }}</div></div></div>
<section class="card">
    <h2>{{ __('ui.add_user') }}</h2>
    <form method="post" action="{{ route('admin.users.store') }}">@csrf
        <div class="form-grid">
            <div class="field"><label>{{ __('ui.name') }}</label><input name="name" required></div>
            <div class="field"><label>{{ __('ui.email') }}</label><input type="email" name="email" required></div>
            <div class="field"><label>{{ __('ui.role') }}</label><select name="role"><option value="VIEWER">{{ __('ui.role_labels.VIEWER') }}</option><option value="ADMIN">{{ __('ui.role_labels.ADMIN') }}</option></select></div>
            <div></div>
            <div class="field"><label>{{ __('ui.password') }}</label><input type="password" name="password" required autocomplete="new-password"></div>
            <div class="field"><label>{{ __('ui.confirm_password') }}</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
        </div>
        <button class="btn">{{ __('ui.add_user') }}</button>
    </form>
</section>
<div class="user-list">
@foreach($users as $managedUser)
<details class="card user-card">
    <summary><span><strong>{{ $managedUser->name }}</strong><small>{{ $managedUser->email }}</small></span><span class="badge">{{ __('ui.role_labels.'.$managedUser->role->value) }}</span></summary>
    <div class="user-card-body">
        <form method="post" action="{{ route('admin.users.update', $managedUser) }}">@csrf @method('PUT')
            <div class="form-grid">
                <div class="field"><label>{{ __('ui.name') }}</label><input name="name" value="{{ $managedUser->name }}" required></div>
                <div class="field"><label>{{ __('ui.email') }}</label><input type="email" name="email" value="{{ $managedUser->email }}" required></div>
                <div class="field"><label>{{ __('ui.role') }}</label><select name="role"><option value="VIEWER" @selected($managedUser->role === App\Enums\UserRole::VIEWER)>{{ __('ui.role_labels.VIEWER') }}</option><option value="ADMIN" @selected($managedUser->role === App\Enums\UserRole::ADMIN)>{{ __('ui.role_labels.ADMIN') }}</option></select></div>
                <div></div>
                <div class="field"><label>{{ __('ui.new_password_optional') }}</label><input type="password" name="password" autocomplete="new-password"></div>
                <div class="field"><label>{{ __('ui.confirm_password') }}</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
            </div>
            <button class="btn small">{{ __('ui.save') }}</button>
        </form>
        @unless(auth()->user()->is($managedUser))
        <form class="user-delete" method="post" action="{{ route('admin.users.destroy', $managedUser) }}" data-confirm="{{ __('ui.delete_user_confirm') }}">@csrf @method('DELETE')<button class="btn danger small">{{ __('ui.delete_user') }}</button></form>
        @endunless
    </div>
</details>
@endforeach
</div>
@endsection
@push('styles')
<style>
.user-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:12px}.user-card{padding:0}.user-card summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;cursor:pointer;list-style:none}.user-card summary::-webkit-details-marker{display:none}.user-card summary span:first-child{display:flex;flex-direction:column;min-width:0}.user-card summary small{overflow:hidden;color:var(--muted);text-overflow:ellipsis;white-space:nowrap}.user-card-body{padding:16px;border-top:1px solid var(--line);background:var(--soft)}.user-delete{margin-top:9px}@media(max-width:680px){.user-list{grid-template-columns:1fr}.user-card summary,.user-card-body{padding:13px}}
</style>
@endpush
