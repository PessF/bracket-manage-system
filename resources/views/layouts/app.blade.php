<!doctype html>
<html lang="{{ app()->getLocale() }}">
@php
    $isAdmin = auth()->user()?->isAdmin() ?? false;
    $isPublicViewer = request()->routeIs('public.tournaments.*');
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.app_name'))</title>
    @php($shareImage = asset('assets/logos/favicon.png').'?v=4')
    @php($shareDescription = isset($tournament) ? $tournament->name : __('ui.app_name'))
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('ui.app_name') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', __('ui.app_name'))">
    <meta property="og:description" content="{{ $shareDescription }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:secure_url" content="{{ $shareImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="40">
    <meta property="og:image:height" content="40">
    <meta property="og:image:alt" content="EasyKids Robotics">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', __('ui.app_name'))">
    <meta name="twitter:description" content="{{ $shareDescription }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <link rel="icon" href="{{ asset('assets/logos/favicon.png') }}?v=3" type="image/png" sizes="40x40">
    <link rel="shortcut icon" href="{{ asset('assets/logos/favicon.png') }}?v=3" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <meta name="theme-color" content="#0d131a">
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- The fallback shares the exact same styles as the Vite build. --}}
        <style>{!! file_get_contents(resource_path('css/ui.css')).file_get_contents(resource_path('css/responsive.css')) !!}</style>
        <script src="{{ asset('assets/js/smart-select-fallback.js') }}" defer></script>
    @endif
    @stack('styles')
</head>
<body class="{{ $isPublicViewer ? 'viewer-shell' : '' }}" data-theme="easykids" data-processing-label="{{ __('ui.processing') }}">
<a class="skip-link" href="#main-content">{{ __('ui.skip_to_content') }}</a>
<header class="top">
    <div class="inner">
        <a class="brand" href="{{ route('events.index') }}">
            <span class="brand-logo-slot"><img class="brand-logo brand-logo--dark" src="{{ asset('assets/logos/EasyKidsLogoW.png') }}" alt="EasyKids Robotics"></span>
            <svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v3a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4v1a4 4 0 0 0 4 4M17 6h3v1a4 4 0 0 1-4 4"/></svg>
            <span class="brand-name">{{ __('ui.app_name') }}</span><span class="brand-short">EasyKids</span>
        </a>
        <nav>
            @unless($isPublicViewer)
            <a class="desktop-only nav-all-tournaments {{ request()->routeIs('events.*', 'tournaments.*') ? 'active' : '' }}" href="{{ route('events.index') }}">{{ __('events.title') }}</a>
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
                    <a href="{{ route('events.index') }}">{{ __('events.title') }}</a>
                    @if($isAdmin)
                    <a href="{{ route('tournaments.create') }}">{{ __('ui.create') }}</a>
                    <a href="{{ route('admin.users.index') }}">{{ __('ui.users') }}</a>
                    <a href="{{ route('admin.api-token.show') }}">{{ __('ui.api_access') }}</a>
                    @endif
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
                        <button class="language-option {{ app()->isLocale('th') ? 'active' : '' }}" type="submit"><span class="language-code">TH</span><span class="language-name">{{ __('ui.language_thai') }}<small>{{ __('ui.language_thai_native') }}</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
                    </form>
                </div>
            </details>
        </nav>
    </div>
</header>
<main id="main-content" class="container @yield('container-class')" tabindex="-1">
    @if(session('success'))<div class="alert success dismissible" role="status"><span>{{ session('success') }}</span><button type="button" data-dismiss-alert aria-label="{{ __('ui.dismiss') }}">×</button></div>@endif
    @if(isset($errors) && $errors->any())<div class="alert error dismissible" role="alert"><div><strong>{{ __('ui.please_fix') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div><button type="button" data-dismiss-alert aria-label="{{ __('ui.dismiss') }}">×</button></div>@endif
    @if(session('import_errors'))<div class="alert warning"><strong>{{ __('ui.csv_skipped_title') }}</strong><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<div class="toast-region" aria-live="polite" aria-atomic="true" data-toast-region></div>
@stack('scripts')
@unless(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    <script>{!! file_get_contents(resource_path('js/responsive.js')) !!}</script>
@endunless
</body>
</html>
