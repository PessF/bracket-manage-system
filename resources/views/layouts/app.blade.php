<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('ui.app_name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
@php
    $isAdmin = auth()->user()?->isAdmin() ?? false;
    $isPublicViewer = request()->routeIs('public.tournaments.*');
@endphp
<body class="{{ $isPublicViewer ? 'viewer-shell' : '' }}" data-theme="easykids" data-processing-label="{{ __('ui.processing') }}">
<header class="top">
    <div class="inner">
        <a class="brand" href="{{ $isPublicViewer ? url()->current() : route('tournaments.index') }}">
            <span class="brand-logo-slot"><img class="brand-logo brand-logo--dark" src="{{ asset('assets/logos/EasyKidsLogoW.png') }}" alt="EasyKids Robotics"></span>
            <svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v3a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4v1a4 4 0 0 0 4 4M17 6h3v1a4 4 0 0 1-4 4"/></svg>
            <span class="brand-name">{{ __('ui.app_name') }}</span><span class="brand-short">EasyKids</span>
        </a>
        <nav>
            @unless($isPublicViewer)
            <a class="desktop-only nav-all-tournaments {{ request()->routeIs('tournaments.index', 'tournaments.show', 'tournaments.bracket', 'tournaments.matches', 'tournaments.results', 'tournaments.settings', 'tournaments.edit') ? 'active' : '' }}" href="{{ route('tournaments.index') }}">{{ __('ui.all_tournaments') }}</a>
            @if($isAdmin)
            <a class="desktop-only {{ request()->routeIs('tournaments.create') ? 'active' : '' }}" href="{{ route('tournaments.create') }}">{{ __('ui.create') }}</a>
            <a class="desktop-only {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">{{ __('ui.users') }}</a>
            <a class="desktop-only {{ request()->routeIs('admin.api-token.*') ? 'active' : '' }}" href="{{ route('admin.api-token.show') }}">{{ __('ui.api_access') }}</a>
            @endif
            @auth
            <span class="account-label desktop-only" title="{{ auth()->user()->name }} · {{ auth()->user()->email }}">{{ __('ui.role_labels.'.auth()->user()->role->value) }}</span>
            <form class="nav-form desktop-only" method="post" action="{{ route('logout') }}">@csrf<button class="nav-button">{{ __('ui.logout') }}</button></form>
            @else
            <a class="desktop-only" href="{{ route('login') }}">{{ __('ui.login') }}</a>
            @endauth
            <details class="mobile-menu">
                <summary>{{ __('ui.menu') }}</summary>
                <div class="mobile-popover">
                    @auth<div class="mobile-user">{{ auth()->user()->name }} · {{ __('ui.role_labels.'.auth()->user()->role->value) }}</div>@endauth
                    <a href="{{ route('tournaments.index') }}">{{ __('ui.all_tournaments') }}</a>
                    @if($isAdmin)
                    <a href="{{ route('tournaments.create') }}">{{ __('ui.create') }}</a>
                    <a href="{{ route('admin.users.index') }}">{{ __('ui.users') }}</a>
                    <a href="{{ route('admin.api-token.show') }}">{{ __('ui.api_access') }}</a>
                    @endif
                    <a href="{{ url('/api/docs') }}">{{ __('ui.api_docs') }}</a>
                    @auth<form class="nav-form" method="post" action="{{ route('logout') }}">@csrf<button class="nav-button">{{ __('ui.logout') }}</button></form>@else<a href="{{ route('login') }}">{{ __('ui.login') }}</a>@endauth
                </div>
            </details>
            @endunless
            <details class="language-menu">
                <summary aria-label="{{ __('ui.select_language') }}">
                    <svg class="language-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    <span>{{ app()->isLocale('th') ? 'ไทย' : 'English' }}</span>
                    <svg class="language-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </summary>
                <div class="language-popover">
                    <form method="post" action="{{ route('locale.update', 'en') }}">@csrf
                        <button class="language-option {{ app()->isLocale('en') ? 'active' : '' }}" type="submit"><span class="language-code">EN</span><span class="language-name">{{ __('ui.language_english') }}<small>English</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
                    </form>
                    <form method="post" action="{{ route('locale.update', 'th') }}">@csrf
                        <button class="language-option {{ app()->isLocale('th') ? 'active' : '' }}" type="submit"><span class="language-code">TH</span><span class="language-name">{{ __('ui.language_thai') }}<small>ภาษาไทย</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
                    </form>
                </div>
            </details>
        </nav>
    </div>
</header>
<main class="container @yield('container-class')">
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="alert error"><strong>{{ __('ui.please_fix') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('import_errors'))<div class="alert warning"><strong>{{ __('ui.csv_skipped_title') }}</strong><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
