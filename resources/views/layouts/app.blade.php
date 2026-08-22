<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EasyKids Tournament')</title>
    <style>
        :root {
            --ink: #18181b; --muted: #71717a; --line: #e4e4e7; --line-strong: #cbd5e1;
            --card: #fff; --bg: #fafafa; --soft: #f4f4f5; --brand: #18181b;
            --good: #15803d; --warn: #b45309; --bad: #b91c1c; --blue: #1d4ed8;
        }
        * { box-sizing: border-box; }
        html { color-scheme: light; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color: inherit; text-decoration: none; }
        a:hover { opacity: .78; }
        .top { position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.94); backdrop-filter: blur(10px); }
        .top .inner { max-width: 1152px; margin: auto; padding: 12px 32px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; min-width: 0; align-items: center; gap: 9px; color: var(--ink); font-size: 15px; font-weight: 700; letter-spacing: -.01em; }
        .brand > span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .brand-mark { width: 21px; height: 21px; }
        .top nav { display: flex; flex: 0 0 auto; align-items: center; gap: 7px; }
        .top nav a { padding: 6px 10px; border-radius: 7px; color: var(--muted); font-weight: 600; }
        .top nav a:hover { background: var(--soft); color: var(--ink); opacity: 1; }
        .nav-form { margin: 0; }
        .nav-button { padding: 6px 10px; border: 0; border-radius: 7px; background: transparent; color: var(--muted); font: inherit; font-weight: 600; cursor: pointer; }
        .nav-button:hover { background: var(--soft); color: var(--ink); }
        .account-label { max-width: 120px; overflow: hidden; padding: 4px 7px; color: var(--muted); font-size: 11px; white-space: nowrap; text-overflow: ellipsis; }
        .mobile-menu { position: relative; display: none; }
        .mobile-menu summary { display: flex; align-items: center; min-height: 35px; padding: 6px 10px; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-weight: 650; cursor: pointer; list-style: none; }
        .mobile-menu summary::-webkit-details-marker { display: none; }
        .mobile-popover { position: absolute; top: calc(100% + 7px); right: 0; z-index: 121; display: flex; width: min(260px,calc(100vw - 28px)); flex-direction: column; gap: 2px; padding: 7px; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 14px 35px rgb(24 24 27 / .14); }
        .mobile-popover a, .mobile-popover .nav-button { display: block; width: 100%; padding: 10px; text-align: left; }
        .mobile-user { padding: 8px 10px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 11px; }
        .language-menu { position: relative; margin-left: 5px; }
        .language-menu summary { display: flex; align-items: center; gap: 7px; min-width: 104px; padding: 7px 9px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: #3f3f46; font-size: 12px; font-weight: 650; cursor: pointer; list-style: none; transition: border-color .15s, box-shadow .15s, background .15s; }
        .language-menu summary::-webkit-details-marker { display: none; }
        .language-menu summary:hover { border-color: #c4c4ca; background: #fafafa; }
        .language-menu summary:focus-visible { outline: none; border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .18); }
        .language-menu[open] summary { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .12); }
        .language-icon { width: 15px; height: 15px; color: var(--muted); }
        .language-chevron { width: 13px; height: 13px; margin-left: auto; color: #a1a1aa; transition: transform .16s; }
        .language-menu[open] .language-chevron { transform: rotate(180deg); }
        .language-popover { position: absolute; top: calc(100% + 7px); right: 0; z-index: 120; width: 210px; padding: 6px; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 14px 35px rgb(24 24 27 / .14), 0 3px 8px rgb(24 24 27 / .07); animation: dropdown-in .13s ease-out; }
        .language-popover::before { content: ""; position: absolute; top: -5px; right: 18px; width: 8px; height: 8px; border-top: 1px solid var(--line); border-left: 1px solid var(--line); background: #fff; transform: rotate(45deg); }
        .language-option { display: grid; grid-template-columns: 29px minmax(0,1fr) 18px; gap: 9px; align-items: center; width: 100%; padding: 8px; border: 0; border-radius: 7px; background: transparent; color: var(--ink); text-align: left; font: inherit; cursor: pointer; }
        .language-option:hover { background: var(--soft); }
        .language-option.active { background: #f4f4f5; }
        .language-code { display: inline-flex; align-items: center; justify-content: center; height: 25px; border: 1px solid var(--line); border-radius: 6px; color: #52525b; font-size: 10px; font-weight: 800; }
        .language-name { display: flex; flex-direction: column; line-height: 1.25; font-size: 12px; font-weight: 650; }
        .language-name small { color: var(--muted); font-size: 10px; font-weight: 500; }
        .language-check { width: 15px; height: 15px; color: var(--ink); opacity: 0; }
        .language-option.active .language-check { opacity: 1; }
        @keyframes dropdown-in { from { opacity: 0; transform: translateY(-4px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .container { width: 100%; max-width: 1152px; margin: 0 auto; padding: 28px 32px 56px; }
        .container-wide { max-width: 1560px; }
        .page-head { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; margin-bottom: 18px; }
        .page-head h1 { margin: 0 0 3px; font-size: 24px; line-height: 1.25; font-weight: 650; letter-spacing: -.025em; }
        h2 { font-size: 16px; margin: 0 0 14px; letter-spacing: -.01em; }
        .muted { color: var(--muted); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 18px; box-shadow: 0 1px 2px rgb(0 0 0 / .025); margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap: 16px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(130px,1fr)); gap: 12px; }
        .stat { background: var(--soft); border-radius: 8px; padding: 13px; }
        .stat strong { display: block; font-size: 21px; line-height: 1.3; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 34px; border: 1px solid transparent; border-radius: 7px; background: var(--brand); color: #fff; padding: 7px 12px; font: inherit; font-weight: 650; cursor: pointer; }
        .btn:hover { opacity: .86; }
        .btn.secondary { background: #fff; border-color: var(--line); color: var(--ink); }
        .btn.danger { background: var(--bad); }
        .btn.small { min-height: 28px; padding: 4px 9px; font-size: 12px; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .actions form { margin: 0; }
        .badge { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 999px; padding: 2px 8px; font-size: 10px; line-height: 18px; font-weight: 700; background: var(--soft); color: #52525b; letter-spacing: .025em; }
        .badge.LIVE, .badge.READY { background: #eff6ff; border-color: #bfdbfe; color: var(--blue); }
        .badge.COMPLETED, .badge.FINISHED { background: #f4f4f5; color: #3f3f46; }
        .badge.DRAFT, .badge.PENDING { background: #f8fafc; color: #64748b; }
        .badge.ARCHIVED { background: #e5e7eb; color: #4b5563; }
        .alert { padding: 11px 14px; border: 1px solid; border-radius: 8px; margin-bottom: 16px; }
        .alert.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .alert.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px 9px; border: 1px solid #d4d4d8; border-radius: 7px; background: #fff; color: var(--ink); font: inherit; outline: none; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        select { padding-right: 34px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 10 5 5 5-5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 9px center; background-size: 15px; appearance: none; cursor: pointer; }
        select:hover:not(:disabled) { border-color: #a1a1aa; background-color: #fafafa; }
        select:disabled { background-color: #f4f4f5; color: #71717a; cursor: not-allowed; opacity: 1; }
        input:focus, select:focus, textarea:focus { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .15); }
        select.native-select-enhanced { position: absolute !important; width: 1px !important; height: 1px !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important; opacity: 0 !important; pointer-events: none !important; }
        .smart-select { position: relative; width: 100%; }
        .smart-select-trigger { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; min-height: 37px; padding: 7px 9px 7px 10px; border: 1px solid #d4d4d8; border-radius: 7px; background: #fff; color: var(--ink); font: inherit; text-align: left; cursor: pointer; outline: none; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .smart-select-trigger:hover:not(:disabled) { border-color: #a1a1aa; background: #fafafa; }
        .smart-select-trigger:focus-visible, .smart-select-trigger[aria-expanded="true"] { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .15); }
        .smart-select-trigger:disabled { background: #f4f4f5; color: #71717a; cursor: not-allowed; }
        .smart-select-value { min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .smart-select-chevron { flex: 0 0 auto; width: 15px; height: 15px; color: #71717a; transition: transform .16s; }
        .smart-select-trigger[aria-expanded="true"] .smart-select-chevron { transform: rotate(180deg); }
        .smart-select-popover { position: fixed; z-index: 1000; display: none; overflow-x: hidden; overflow-y: auto; padding: 5px; border: 1px solid var(--line); border-radius: 9px; background: #fff; box-shadow: 0 16px 38px rgb(24 24 27 / .16), 0 4px 10px rgb(24 24 27 / .07); scrollbar-width: thin; }
        .smart-select-popover.open { display: block; animation: dropdown-in .13s ease-out; }
        .smart-select-option { display: grid; grid-template-columns: minmax(0,1fr) 18px; gap: 9px; align-items: center; width: 100%; padding: 8px 9px; border: 0; border-radius: 6px; background: transparent; color: var(--ink); font: inherit; text-align: left; cursor: pointer; outline: none; }
        .smart-select-option:hover, .smart-select-option.focused, .smart-select-option:focus-visible { background: var(--soft); }
        .smart-select-option.selected { background: #f4f4f5; font-weight: 650; }
        .smart-select-option:disabled { color: #a1a1aa; cursor: not-allowed; }
        .smart-select-option-check { width: 15px; height: 15px; color: var(--ink); opacity: 0; }
        .smart-select-option.selected .smart-select-option-check { opacity: 1; }
        textarea { min-height: 90px; }
        .field { margin-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 0 18px; }
        .full { grid-column: 1/-1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 9px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .055em; color: var(--muted); }
        .tabs { display: flex; gap: 2px; overflow-x: auto; margin: 0 0 22px; border-bottom: 1px solid var(--line); scrollbar-width: thin; }
        .tabs a { position: relative; flex: 0 0 auto; padding: 10px 14px; color: var(--muted); font-weight: 600; white-space: nowrap; }
        .tabs a:hover, .tabs a.active { color: var(--ink); opacity: 1; }
        .tabs a.active::after { content: ""; position: absolute; height: 2px; left: 12px; right: 12px; bottom: -1px; background: var(--ink); border-radius: 2px 2px 0 0; }
        .match-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(270px,1fr)); gap: 14px; }
        .match { border: 1px solid var(--line); border-radius: 9px; padding: 12px; background: #fff; }
        .match .team { display: flex; justify-content: space-between; padding: 6px 0; }
        .match .meta { font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; }
        .score-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 7px; margin-top: 9px; }
        .easy-score-form { margin-top: 9px; padding-top: 9px; border-top: 1px solid var(--line); }
        .score-pair { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 7px; }
        .score-team-control > span:first-child { display: block; margin-bottom: 3px; overflow: hidden; color: var(--muted); font-size: 10px; white-space: nowrap; text-overflow: ellipsis; }
        .score-stepper { display: grid; grid-template-columns: 27px minmax(38px,1fr) 27px; overflow: hidden; border: 1px solid #d4d4d8; border-radius: 7px; background: #fff; }
        .score-stepper button { border: 0; background: var(--soft); color: #52525b; font-size: 17px; line-height: 27px; cursor: pointer; }
        .score-stepper button:hover { background: #e4e4e7; color: var(--ink); }
        .score-stepper input { width: 100%; min-width: 0; height: 29px; padding: 2px; border: 0; border-left: 1px solid var(--line); border-right: 1px solid var(--line); border-radius: 0; box-shadow: none; text-align: center; font-weight: 700; -moz-appearance: textfield; }
        .score-stepper input::-webkit-inner-spin-button, .score-stepper input::-webkit-outer-spin-button { margin: 0; -webkit-appearance: none; }
        .score-submit { width: 100%; margin-top: 7px; }
        .inline-form { display: flex; gap: 7px; align-items: end; flex-wrap: wrap; }
        .inline-form .field { margin: 0; min-width: 110px; }
        .empty { text-align: center; padding: 34px; color: var(--muted); }
        .auth-shell { display: grid; min-height: calc(100vh - 180px); place-items: center; }
        .auth-card { width: min(100%, 430px); padding: 26px; }
        .auth-card-wide { width: min(100%, 650px); }
        .auth-card h1 { margin: 0 0 5px; font-size: 23px; }
        .auth-card > p { margin: 0 0 22px; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin: 0 0 17px; font-weight: 500; }
        .checkbox-row input { width: auto; }
        .auth-submit { width: 100%; }
        @media (max-width: 680px) {
            .container { padding: 18px 14px 42px; }
            .top .inner { padding: 11px 14px; }
            .brand { max-width: calc(100vw - 176px); }
            .brand-mark { flex: 0 0 auto; }
            .desktop-only { display: none !important; }
            .mobile-menu { display: block; }
            .language-menu { margin-left: 0; }
            .language-menu summary { min-width: 0; min-height: 35px; }
            .language-menu summary > span { display: none; }
            .language-popover { position: fixed; top: 58px; right: 14px; width: min(260px,calc(100vw - 28px)); }
            .form-grid { grid-template-columns: 1fr; }
            .page-head { display: block; }
            .page-head .actions { margin-top: 12px; }
            .card { padding: 14px; }
            .btn { min-height: 40px; }
            input, select, textarea { min-height: 42px; font-size: 16px; }
            .table-wrap { margin-right: -14px; margin-left: -14px; overflow: auto; padding: 0 14px; }
            .inline-form { align-items: stretch; flex-direction: column; }
            .inline-form .field { width: 100%; min-width: 0 !important; }
            .inline-form > .btn { width: 100%; }
            .stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .auth-shell { min-height: 0; place-items: start stretch; }
            .auth-card { padding: 18px; }
        }
    </style>
    @stack('styles')
</head>
<body>
@php($isAdmin = auth()->user()?->isAdmin() ?? false)
<header class="top">
    <div class="inner">
        <a class="brand" href="{{ route('tournaments.index') }}">
            <svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v3a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4v1a4 4 0 0 0 4 4M17 6h3v1a4 4 0 0 1-4 4"/></svg>
            <span>{{ __('ui.app_name') }}</span>
        </a>
        <nav>
            <a class="desktop-only" href="{{ route('tournaments.index') }}">{{ __('ui.all_tournaments') }}</a>
            @if($isAdmin)
            <a class="desktop-only" href="{{ route('tournaments.create') }}">{{ __('ui.create') }}</a>
            <a class="desktop-only" href="{{ route('admin.users.index') }}">{{ __('ui.users') }}</a>
            <a class="desktop-only" href="{{ route('admin.api-token.show') }}">API</a>
            @endif
            @auth
            <span class="account-label desktop-only" title="{{ auth()->user()->email }}">{{ auth()->user()->name }} · {{ auth()->user()->role->value }}</span>
            <form class="nav-form desktop-only" method="post" action="{{ route('logout') }}">@csrf<button class="nav-button">{{ __('ui.logout') }}</button></form>
            @else
            <a class="desktop-only" href="{{ route('login') }}">{{ __('ui.login') }}</a>
            @endauth
            <details class="mobile-menu">
                <summary>{{ __('ui.menu') }}</summary>
                <div class="mobile-popover">
                    @auth<div class="mobile-user">{{ auth()->user()->name }} · {{ auth()->user()->role->value }}</div>@endauth
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
            <details class="language-menu">
                <summary aria-label="Select language">
                    <svg class="language-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    <span>{{ app()->isLocale('th') ? 'ไทย' : 'English' }}</span>
                    <svg class="language-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </summary>
                <div class="language-popover">
                    <form method="post" action="{{ route('locale.update', 'en') }}">@csrf
                        <button class="language-option {{ app()->isLocale('en') ? 'active' : '' }}" type="submit"><span class="language-code">EN</span><span class="language-name">English<small>English</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
                    </form>
                    <form method="post" action="{{ route('locale.update', 'th') }}">@csrf
                        <button class="language-option {{ app()->isLocale('th') ? 'active' : '' }}" type="submit"><span class="language-code">TH</span><span class="language-name">ภาษาไทย<small>Thai</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
                    </form>
                </div>
            </details>
        </nav>
    </div>
</header>
<main class="container @yield('container-class')">
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="alert error"><strong>{{ __('ui.please_fix') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('import_errors'))<div class="alert" style="background:#fffbeb;border-color:#fde68a;color:#92400e"><strong>{{ __('ui.csv_skipped_title') }}</strong><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-score-step]');
    if (!button) return;
    const input = button.closest('.score-stepper')?.querySelector('input[type="number"]');
    if (!input) return;
    const direction = Number(button.dataset.scoreStep);
    const current = Number(input.value || 0);
    const minimum = input.min === '' ? 0 : Number(input.min);
    input.value = String(Math.max(minimum, current + direction));
    input.dispatchEvent(new Event('input', { bubbles: true }));
});
document.addEventListener('click', (event) => {
    document.querySelectorAll('.language-menu[open], .mobile-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('.language-menu[open], .mobile-menu[open]').forEach((menu) => menu.removeAttribute('open'));
});

(() => {
    let activeSelect = null;
    let typeahead = '';
    let typeaheadTimer = null;

    const chevron = '<svg class="smart-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>';
    const check = '<svg class="smart-select-option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';

    const closeSelect = (instance, restoreFocus = false) => {
        if (!instance) return;
        instance.menu.classList.remove('open');
        instance.trigger.setAttribute('aria-expanded', 'false');
        instance.options.forEach((option) => option.classList.remove('focused'));
        if (activeSelect === instance) activeSelect = null;
        if (restoreFocus) instance.trigger.focus();
    };

    const positionSelect = (instance) => {
        const rect = instance.trigger.getBoundingClientRect();
        const gutter = 8;
        const width = Math.min(Math.max(rect.width, 180), window.innerWidth - (gutter * 2));
        const left = Math.max(gutter, Math.min(rect.left, window.innerWidth - width - gutter));

        instance.menu.style.width = `${width}px`;
        instance.menu.style.left = `${left}px`;
        instance.menu.style.top = `${rect.bottom + 5}px`;
        instance.menu.style.bottom = 'auto';
        instance.menu.style.maxHeight = '280px';

        const wantedHeight = Math.min(instance.menu.scrollHeight, 280);
        const below = window.innerHeight - rect.bottom - 13;
        const above = rect.top - 13;

        if (below >= Math.min(wantedHeight, 180) || below >= above) {
            instance.menu.style.maxHeight = `${Math.max(80, below)}px`;
        } else {
            instance.menu.style.top = 'auto';
            instance.menu.style.bottom = `${window.innerHeight - rect.top + 5}px`;
            instance.menu.style.maxHeight = `${Math.max(80, above)}px`;
        }
    };

    const syncSelect = (instance) => {
        const selectedIndex = instance.select.selectedIndex;
        const selected = instance.select.options[selectedIndex];
        instance.value.textContent = selected?.textContent.trim() || '';
        instance.trigger.title = selected?.textContent.trim() || '';
        instance.trigger.disabled = instance.select.disabled;
        instance.options.forEach((option, index) => {
            const isSelected = index === selectedIndex;
            option.classList.toggle('selected', isSelected);
            option.setAttribute('aria-selected', String(isSelected));
        });
    };

    const focusOption = (instance, index) => {
        const available = instance.options.filter((option) => !option.disabled);
        if (!available.length) return;
        const bounded = (index + available.length) % available.length;
        instance.options.forEach((option) => option.classList.remove('focused'));
        available[bounded].classList.add('focused');
        available[bounded].focus({ preventScroll: true });
        available[bounded].scrollIntoView({ block: 'nearest' });
    };

    const openSelect = (instance, direction = 0) => {
        if (instance.trigger.disabled) return;
        if (activeSelect && activeSelect !== instance) closeSelect(activeSelect);
        activeSelect = instance;
        instance.menu.classList.add('open');
        instance.trigger.setAttribute('aria-expanded', 'true');
        positionSelect(instance);

        const available = instance.options.filter((option) => !option.disabled);
        const selected = available.findIndex((option) => option.classList.contains('selected'));
        const destination = direction < 0 ? available.length - 1 : Math.max(0, selected);
        requestAnimationFrame(() => focusOption(instance, destination));
    };

    const chooseOption = (instance, index) => {
        const sourceOption = instance.select.options[index];
        if (!sourceOption || sourceOption.disabled) return;
        instance.select.selectedIndex = index;
        instance.select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSelect(instance);
        closeSelect(instance, true);
    };

    document.querySelectorAll('select:not([multiple]):not([data-native-select])').forEach((select, selectIndex) => {
        if (select.dataset.smartSelect === 'true') return;
        select.dataset.smartSelect = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'smart-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('native-select-enhanced');

        const menuId = `smart-select-menu-${selectIndex}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'smart-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', menuId);
        trigger.innerHTML = `<span class="smart-select-value"></span>${chevron}`;
        wrapper.appendChild(trigger);

        const menu = document.createElement('div');
        menu.id = menuId;
        menu.className = 'smart-select-popover';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Options');
        document.body.appendChild(menu);

        const instance = {
            select,
            wrapper,
            trigger,
            value: trigger.querySelector('.smart-select-value'),
            menu,
            options: [],
        };

        Array.from(select.options).forEach((sourceOption, optionIndex) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'smart-select-option';
            option.setAttribute('role', 'option');
            option.disabled = sourceOption.disabled;
            option.innerHTML = `<span></span>${check}`;
            option.querySelector('span').textContent = sourceOption.textContent.trim();
            option.addEventListener('click', () => chooseOption(instance, optionIndex));
            option.addEventListener('keydown', (event) => {
                const available = instance.options.filter((item) => !item.disabled);
                const current = available.indexOf(option);
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    focusOption(instance, current + (event.key === 'ArrowDown' ? 1 : -1));
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    focusOption(instance, event.key === 'Home' ? 0 : available.length - 1);
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    option.click();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSelect(instance, true);
                } else if (event.key === 'Tab') {
                    closeSelect(instance);
                }
            });
            menu.appendChild(option);
            instance.options.push(option);
        });

        trigger.addEventListener('click', () => {
            if (activeSelect === instance) closeSelect(instance);
            else openSelect(instance);
        });
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                openSelect(instance, event.key === 'ArrowUp' ? -1 : 1);
                return;
            }

            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                clearTimeout(typeaheadTimer);
                typeahead += event.key.toLocaleLowerCase();
                const options = Array.from(select.options);
                const match = options.findIndex((option) => !option.disabled && option.textContent.trim().toLocaleLowerCase().startsWith(typeahead));
                if (match >= 0) {
                    select.selectedIndex = match;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                typeaheadTimer = setTimeout(() => { typeahead = ''; }, 650);
            }
        });

        select.addEventListener('change', () => syncSelect(instance));
        select.form?.addEventListener('reset', () => setTimeout(() => syncSelect(instance)));
        if (select.id) {
            document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((label) => {
                label.addEventListener('click', (event) => {
                    event.preventDefault();
                    trigger.focus();
                });
            });
        }

        syncSelect(instance);
    });

    document.addEventListener('click', (event) => {
        if (activeSelect && !activeSelect.wrapper.contains(event.target) && !activeSelect.menu.contains(event.target)) closeSelect(activeSelect);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeSelect) closeSelect(activeSelect, true);
    });
    window.addEventListener('resize', () => activeSelect && closeSelect(activeSelect));
    document.addEventListener('scroll', (event) => {
        if (activeSelect && !activeSelect.menu.contains(event.target)) closeSelect(activeSelect);
    }, true);
})();
</script>
@stack('scripts')
</body>
</html>
