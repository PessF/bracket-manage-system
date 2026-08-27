<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.app_name'))</title>
    <link rel="icon" href="{{ asset('assets/logos/favicon.png') }}?v=3" type="image/png" sizes="40x40">
    <link rel="shortcut icon" href="{{ asset('assets/logos/favicon.png') }}?v=3" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <meta name="theme-color" content="#0d131a">
    <style>
        @font-face { font-family: "LINE Seed Sans TH"; src: url("/assets/fonts/LINESeedSansTH_W_Rg.woff2") format("woff2"); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: "LINE Seed Sans TH"; src: url("/assets/fonts/LINESeedSansTH_W_Bd.woff2") format("woff2"); font-weight: 700; font-style: normal; font-display: swap; }
        :root {
            --ink: #18181b; --muted: #71717a; --line: #e4e4e7; --line-strong: #cbd5e1;
            --card: #fff; --bg: #fafafa; --soft: #f4f4f5; --brand: #18181b;
            --good: #15803d; --warn: #b45309; --bad: #b91c1c; --blue: #1d4ed8;
            --top-height: 69px;
        }
        * { box-sizing: border-box; }
        html { color-scheme: light; scroll-padding-top: calc(var(--top-height) + 58px); }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color: inherit; text-decoration: none; }
        a:hover { opacity: .78; }
        a:focus-visible, button:focus-visible, summary:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .top { position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.94); backdrop-filter: blur(10px); }
        .top .inner { width:100%; max-width: 1152px; min-width:0; margin: auto; padding: 12px 32px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; flex:0 1 auto; min-width: 0; align-items: center; gap: 9px; color: var(--ink); font-size: 16px; font-weight: 700; letter-spacing: -.01em; }
        .brand > span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .brand-short { display:none; }
        .brand-mark { width: 21px; height: 21px; }
        .top nav { display: flex; flex: 0 0 auto; min-width:0; align-items: center; gap: 7px; }
        .top nav a { min-height:44px; padding: 10px 12px; border-radius: 7px; color: var(--muted); font-size:14px; font-weight: 600; }
        .top nav a:hover { background: var(--soft); color: var(--ink); opacity: 1; }
        .nav-form { margin: 0; }
        .nav-button { min-height:44px; padding: 10px 12px; border: 0; border-radius: 7px; background: transparent; color: var(--muted); font: inherit; font-size:14px; font-weight: 600; cursor: pointer; }
        .nav-button:hover { background: var(--soft); color: var(--ink); }
        .account-label { max-width: 120px; overflow: hidden; padding: 4px 7px; color: var(--muted); font-size: 12px; white-space: nowrap; text-overflow: ellipsis; }
        .mobile-menu { position: relative; display: none; }
        .mobile-menu summary { display: flex; align-items: center; min-height: 44px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-weight: 650; cursor: pointer; list-style: none; }
        .mobile-menu summary::-webkit-details-marker { display: none; }
        .mobile-popover { position: absolute; top: calc(100% + 7px); right: 0; z-index: 121; display: flex; width: min(260px,calc(100vw - 28px)); flex-direction: column; gap: 2px; padding: 7px; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 14px 35px rgb(24 24 27 / .14); }
        .mobile-popover a, .mobile-popover .nav-button { display: block; width: 100%; min-height:48px; padding: 12px; text-align: left; }
        .mobile-user { padding: 10px 12px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 13px; }
        .language-menu { position: relative; margin-left: 5px; }
        .language-menu summary { display: flex; align-items: center; gap: 7px; min-width: 104px; min-height:44px; padding: 9px 10px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: #3f3f46; font-size: 14px; font-weight: 650; cursor: pointer; list-style: none; transition: border-color .15s, box-shadow .15s, background .15s; }
        .language-menu summary::-webkit-details-marker { display: none; }
        .language-menu summary:hover { border-color: #c4c4ca; background: #fafafa; }
        .language-menu summary:focus-visible { outline: none; border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .18); }
        .language-menu[open] summary { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .12); }
        .language-icon { width: 15px; height: 15px; color: var(--muted); }
        .language-chevron { width: 13px; height: 13px; margin-left: auto; color: #a1a1aa; transition: transform .16s; }
        .language-menu[open] .language-chevron { transform: rotate(180deg); }
        .language-popover { position: absolute; top: calc(100% + 7px); right: 0; z-index: 120; width: 210px; padding: 6px; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 14px 35px rgb(24 24 27 / .14), 0 3px 8px rgb(24 24 27 / .07); animation: dropdown-in .13s ease-out; }
        .language-popover::before { content: ""; position: absolute; top: -5px; right: 18px; width: 8px; height: 8px; border-top: 1px solid var(--line); border-left: 1px solid var(--line); background: #fff; transform: rotate(45deg); }
        .language-option { display: grid; grid-template-columns: 32px minmax(0,1fr) 18px; gap: 9px; align-items: center; width: 100%; min-height:48px; padding: 9px; border: 0; border-radius: 7px; background: transparent; color: var(--ink); text-align: left; font: inherit; cursor: pointer; }
        .language-option:hover { background: var(--soft); }
        .language-option.active { background: #f4f4f5; }
        .language-code { display: inline-flex; align-items: center; justify-content: center; height: 28px; border: 1px solid var(--line); border-radius: 6px; color: #52525b; font-size: 12px; font-weight: 800; }
        .language-name { display: flex; flex-direction: column; line-height: 1.3; font-size: 14px; font-weight: 650; }
        .language-name small { color: var(--muted); font-size: 12px; font-weight: 500; }
        .language-check { width: 15px; height: 15px; color: var(--ink); opacity: 0; }
        .language-option.active .language-check { opacity: 1; }
        @keyframes dropdown-in { from { opacity: 0; transform: translateY(-4px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .container { width: 100%; max-width: 1152px; min-width:0; margin: 0 auto; padding: 28px 32px 56px; }
        .container-wide { max-width: 1560px; }
        .page-head { display: flex; flex-wrap:wrap; justify-content: space-between; gap: 14px 20px; align-items: flex-start; margin-bottom: 18px; }
        .page-head > :first-child { flex:1 1 420px; min-width:0; }
        .page-head h1 { max-width:100%; margin: 0 0 3px; overflow-wrap:anywhere; font-size: 26px; line-height: 1.25; font-weight: 650; letter-spacing: -.025em; }
        h2 { font-size: 18px; margin: 0 0 14px; letter-spacing: -.01em; }
        .muted { color: var(--muted); }
        .card { min-width:0; background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 21px; box-shadow: 0 1px 2px rgb(0 0 0 / .025); margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(min(100%,260px),1fr)); gap: 16px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(130px,1fr)); gap: 12px; }
        .stat { min-width:0; background: var(--soft); border-radius: 8px; padding: 13px; }
        .stat strong { display: block; overflow-wrap:anywhere; font-size: 21px; line-height: 1.3; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 44px; border: 1px solid transparent; border-radius: 8px; background: var(--brand); color: #fff; padding: 10px 16px; font: inherit; font-weight: 650; cursor: pointer; touch-action:manipulation; }
        .btn:hover { opacity: .86; }
        .btn:disabled, .btn[aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
        .btn.is-submitting::before { content:""; width:14px; height:14px; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; animation:button-spin .7s linear infinite; }
        .btn.secondary { background: #fff; border-color: var(--line); color: var(--ink); }
        .btn.danger { background: var(--bad); }
        .btn.small { min-height: 38px; padding: 7px 12px; font-size: 14px; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .actions > * { min-width:0; }
        .actions form { margin: 0; }
        .badge { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 999px; padding: 3px 9px; font-size: 11px; line-height: 19px; font-weight: 700; background: var(--soft); color: #52525b; letter-spacing: .025em; }
        .badge.LIVE, .badge.READY { background: #eff6ff; border-color: #bfdbfe; color: var(--blue); }
        .badge.COMPLETED, .badge.FINISHED { background: #f4f4f5; color: #3f3f46; }
        .badge.DRAFT, .badge.PENDING { background: #f8fafc; color: #64748b; }
        .badge.ARCHIVED { background: #e5e7eb; color: #4b5563; }
        .alert { padding: 11px 14px; border: 1px solid; border-radius: 8px; margin-bottom: 16px; overflow-wrap:anywhere; }
        .alert ul { margin:6px 0 0; padding-left:20px; }
        .alert.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .alert.warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .alert.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .alert.neutral { background:#f8fafc; border-color:#dbe3ee; color:#475569; }
        .danger-card { border-color:#fecaca; }
        .danger-card h2 { color:#991b1b; }
        .match-side { display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; min-width:42px; height:22px; padding:0 7px; border:1px solid; border-radius:999px; font-size:10px; font-style:normal; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .match-side.red { border-color:#fecaca; background:#fff1f2; color:#be123c; }
        .match-side.blue { border-color:#bfdbfe; background:#eff6ff; color:#1d4ed8; }
        .match-progress-form { margin-top:8px; }
        .progress-button { width:100%; background:#ea580c; box-shadow:0 3px 8px rgb(234 88 12 / .2); }
        .current-match-indicator { display:flex; align-items:center; justify-content:center; gap:7px; margin-top:8px; padding:7px 10px; border:1px solid #fdba74; border-radius:7px; background:#fff7ed; color:#9a3412; font-size:12px; font-weight:800; text-transform:uppercase; }
        .current-match-indicator span { width:8px; height:8px; border-radius:50%; background:#f97316; box-shadow:0 0 0 4px rgb(249 115 22 / .15); }
        .view-only-banner { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
        .share-link-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:8px; }
        .short-link-form { margin-top:16px; padding-top:16px; border-top:1px solid var(--line); }
        .short-link-form label { margin-bottom:6px; }
        .short-link-form small { display:block; margin-top:6px; }
        .short-link-control-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; }
        .short-link-input { display:flex; align-items:center; overflow:hidden; border:1px solid #d4d4d8; border-radius:8px; background:#fff; }
        .short-link-input:focus-within { border-color:#a1a1aa; box-shadow:0 0 0 3px rgb(161 161 170 / .15); }
        .short-link-input span { flex:0 0 auto; padding-left:11px; color:var(--muted); white-space:nowrap; }
        .short-link-input input { flex:1; width:0; min-width:90px; border:0; box-shadow:none; }
        .live-refresh { display:flex; align-items:center; gap:8px; min-height:43px; margin:-7px 0 18px; padding:8px 10px; border:1px solid #bbf7d0; border-radius:9px; background:#f0fdf4; color:#166534; font-size:12px; }
        .live-refresh .btn { margin-left:auto; }
        .live-dot { width:9px; height:9px; border-radius:50%; background:#16a34a; box-shadow:0 0 0 0 rgb(22 163 74 / .45); animation:live-pulse 1.8s infinite; }
        .detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .detail-item { min-width:0; padding:12px; border-radius:8px; background:var(--soft); }
        .detail-item small { display:block; margin-bottom:3px; color:var(--muted); font-weight:650; }
        .detail-item strong, .detail-item span { overflow-wrap:anywhere; }
        .detail-item.full { grid-column:1/-1; }
        .tournament-card { display:block; transition:transform .15s,border-color .15s,box-shadow .15s; }
        .tournament-card:hover { border-color:#cbd5e1; box-shadow:0 7px 20px rgb(15 23 42 / .07); opacity:1; transform:translateY(-2px); }
        .card-link-label { display:flex; align-items:center; justify-content:flex-end; gap:5px; margin-top:12px; color:var(--blue); font-size:12px; font-weight:650; }
        .admin-welcome { display:flex; align-items:center; justify-content:space-between; gap:22px; border-color:#bfdbfe; background:linear-gradient(135deg,#eff6ff,#fff 70%); }
        .admin-welcome h2 { margin:9px 0 2px; }
        .admin-welcome p { margin:0; }
        @keyframes live-pulse { 70% { box-shadow:0 0 0 7px rgb(22 163 74 / 0); } 100% { box-shadow:0 0 0 0 rgb(22 163 74 / 0); } }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        input, select, textarea { width: 100%; min-height:44px; padding: 9px 11px; border: 1px solid #d4d4d8; border-radius: 8px; background: #fff; color: var(--ink); font: inherit; outline: none; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        input[type="checkbox"], input[type="radio"] { width:20px; height:20px; min-height:20px; padding:0; accent-color:var(--brand); }
        select { padding-right: 34px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 10 5 5 5-5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 9px center; background-size: 15px; appearance: none; cursor: pointer; }
        select:hover:not(:disabled) { border-color: #a1a1aa; background-color: #fafafa; }
        select:disabled { background-color: #f4f4f5; color: #71717a; cursor: not-allowed; opacity: 1; }
        input:focus, select:focus, textarea:focus { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .15); }
        input[readonly] { background:#fafafa; color:#52525b; }
        input:user-invalid, select:user-invalid, textarea:user-invalid { border-color:#ef4444; }
        select.native-select-enhanced { position: absolute !important; width: 1px !important; height: 1px !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important; opacity: 0 !important; pointer-events: none !important; }
        .smart-select { position: relative; width: 100%; }
        .smart-select-trigger { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; min-height: 44px; padding: 9px 11px; border: 1px solid #d4d4d8; border-radius: 8px; background: #fff; color: var(--ink); font: inherit; text-align: left; cursor: pointer; outline: none; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .smart-select-trigger:hover:not(:disabled) { border-color: #a1a1aa; background: #fafafa; }
        .smart-select-trigger:focus-visible, .smart-select-trigger[aria-expanded="true"] { border-color: #a1a1aa; box-shadow: 0 0 0 3px rgb(161 161 170 / .15); }
        .smart-select-trigger:disabled { background: #f4f4f5; color: #71717a; cursor: not-allowed; }
        .smart-select-value { min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .smart-select-chevron { flex: 0 0 auto; width: 15px; height: 15px; color: #71717a; transition: transform .16s; }
        .smart-select-trigger[aria-expanded="true"] .smart-select-chevron { transform: rotate(180deg); }
        .smart-select-popover { position: fixed; z-index: 1000; display: none; overflow-x: hidden; overflow-y: auto; padding: 5px; border: 1px solid var(--line); border-radius: 9px; background: #fff; box-shadow: 0 16px 38px rgb(24 24 27 / .16), 0 4px 10px rgb(24 24 27 / .07); scrollbar-width: thin; }
        .smart-select-popover.open { display: block; animation: dropdown-in .13s ease-out; }
        .smart-select-option { display: grid; grid-template-columns: minmax(0,1fr) 18px; gap: 9px; align-items: center; width: 100%; min-height:44px; padding: 9px 11px; border: 0; border-radius: 6px; background: transparent; color: var(--ink); font: inherit; text-align: left; cursor: pointer; outline: none; }
        .smart-select-option:hover, .smart-select-option.focused, .smart-select-option:focus-visible { background: var(--soft); }
        .smart-select-option.selected { background: #f4f4f5; font-weight: 650; }
        .smart-select-option:disabled { color: #a1a1aa; cursor: not-allowed; }
        .smart-select-option-check { width: 15px; height: 15px; color: var(--ink); opacity: 0; }
        .smart-select-option.selected .smart-select-option-check { opacity: 1; }
        textarea { min-height: 90px; }
        .field { margin-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 0 18px; }
        .full { grid-column: 1/-1; }
        .table-wrap { max-width:100%; overflow-x:auto; overscroll-behavior-inline:contain; -webkit-overflow-scrolling:touch; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 9px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .045em; color: var(--muted); }
        .tabs { display: flex; gap: 2px; max-width:100%; overflow-x: auto; overscroll-behavior-inline:contain; margin: 0 0 22px; border-bottom: 1px solid var(--line); scrollbar-width: thin; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; }
        .tabs a { position: relative; flex: 0 0 auto; min-height:46px; padding: 11px 15px; color: var(--muted); font-size:14px; font-weight: 600; white-space: nowrap; }
        .tabs a { scroll-snap-align:start; }
        .tabs a:hover, .tabs a.active { color: var(--ink); opacity: 1; }
        .tabs a.active::after { content: ""; position: absolute; height: 2px; left: 12px; right: 12px; bottom: -1px; background: var(--ink); border-radius: 2px 2px 0 0; }
        .match-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(min(100%,270px),1fr)); gap: 14px; }
        .match { border: 1px solid var(--line); border-radius: 9px; padding: 12px; background: #fff; }
        .match .team { display: flex; justify-content: space-between; padding: 6px 0; }
        .match .meta { font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; }
        .score-stepper { display: grid; grid-template-columns: 42px minmax(48px,1fr) 42px; overflow: hidden; border: 1px solid #d4d4d8; border-radius: 8px; background: #fff; }
        .score-stepper button { height:44px; min-height:44px; border: 0; background: var(--soft); color: #52525b; font-size: 22px; line-height:1; cursor: pointer; touch-action:manipulation; }
        .score-stepper button:hover { background: #e4e4e7; color: var(--ink); }
        .score-stepper input { width: 100%; min-width: 0; height:44px; min-height:44px; padding: 2px; border: 0; border-left: 1px solid var(--line); border-right: 1px solid var(--line); border-radius: 0; box-shadow: none; text-align: center; font-weight: 700; -moz-appearance: textfield; }
        .score-stepper input::-webkit-inner-spin-button, .score-stepper input::-webkit-outer-spin-button { margin: 0; -webkit-appearance: none; }
        .viewer-only-nav { display:flex; flex-wrap:wrap; gap:8px; margin:-5px 0 16px; }
        .viewer-only-nav a { display:inline-flex; min-height:44px; align-items:center; font-weight:700; }
        .podium-more { display:flex; justify-content:center; margin-top:12px; }
        .podium-more .btn { width:min(100%, 360px); justify-content:center; }
        .viewer-shell .container { max-width:none; padding-top:18px; }
        .viewer-shell .top .inner { max-width:none; }
        .viewer-shell .live-refresh { margin-top:0; }
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
        @keyframes button-spin { to { transform:rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior:auto !important; animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; }
        }
        @media (max-width: 1152px) {
            .top .inner { padding-right:24px; padding-left:24px; }
            .desktop-only { display: none !important; }
            .mobile-menu { display: block; }
            .admin-welcome { align-items:stretch; flex-direction:column; }
            .admin-welcome .actions { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); }
        }
        @media (max-width: 820px) {
            .container { padding-right:24px; padding-left:24px; }
            .share-link-row { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .share-link-row input { grid-column:1/-1; }
            .share-link-row .btn { width:100%; }
            .detail-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media (max-width: 680px) {
            .container { padding: 18px 14px 42px; }
            .top .inner { padding: 11px 14px; }
            .brand { max-width: calc(100vw - 176px); }
            .brand-mark { flex: 0 0 auto; }
            .language-menu { margin-left: 0; }
            .mobile-menu summary, .language-menu summary { min-width: 0; min-height: 46px; }
            .language-menu summary > span { display: none; }
            .mobile-popover { position:fixed; top:var(--top-height); right:14px; }
            .language-popover { position: fixed; top: var(--top-height); right: 14px; width: min(260px,calc(100vw - 28px)); }
            .form-grid { grid-template-columns: 1fr; }
            .page-head { display: block; }
            .page-head > .actions, .page-head > .btn, .page-head > .badge { margin-top: 12px; }
            .page-head > .actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .page-head > .actions form, .page-head > .actions .btn { width:100%; }
            .card { padding: 14px; }
            .page-head h1 { font-size:23px; }
            .btn { min-height: 46px; padding:10px 15px; }
            .btn.small { min-height:44px; }
            input, select, textarea { min-height: 46px; font-size: 16px; }
            input[type="checkbox"], input[type="radio"] { width:22px; height:22px; min-height:22px; }
            .score-stepper button, .score-stepper input { height:46px; min-height:46px; }
            .table-wrap { margin-right: -14px; margin-left: -14px; padding: 0 14px 6px; }
            .inline-form { align-items: stretch; flex-direction: column; }
            .inline-form .field { width: 100%; min-width: 0 !important; }
            .inline-form > .btn { width: 100%; }
            .stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .auth-shell { min-height: 0; place-items: start stretch; }
            .auth-card { padding: 18px; }
            .share-link-row { grid-template-columns:1fr; }
            .short-link-control-row { grid-template-columns:1fr; }
            .short-link-control-row .btn { width:100%; }
            .short-link-input { display:grid; grid-template-columns:1fr; align-items:stretch; }
            .short-link-input span { max-width:none; overflow-x:auto; padding:7px 11px; border-bottom:1px solid var(--line); background:var(--soft); font-size:12px; scrollbar-width:none; }
            .short-link-input span::-webkit-scrollbar { display:none; }
            .short-link-input input { width:100%; min-width:0; }
            .tabs { position:sticky; top:var(--top-height); z-index:70; margin-right:-14px; margin-left:-14px; padding:0 7px; background:rgb(250 250 250 / .96); backdrop-filter:blur(9px); scrollbar-width:none; }
            .tabs::-webkit-scrollbar { display:none; }
            .tabs a { min-height:44px; padding:12px 11px; }
            .live-refresh { position:relative; flex-wrap:wrap; margin-top:-8px; }
            .live-refresh .btn { width:100%; margin-left:0; }
            .detail-grid { grid-template-columns:1fr; }
            .detail-item.full { grid-column:auto; }
            .table-wrap th, .table-wrap td { white-space:nowrap; }
            .tournament-card { margin-bottom:0; }
            .admin-welcome .actions { display:grid; grid-template-columns:1fr; }
            .viewer-shell .container { padding:12px 10px 32px; }
            .viewer-shell .top .inner { padding:8px 10px; }
            .viewer-shell .live-refresh { flex-wrap:nowrap; gap:6px; margin-bottom:12px; }
            .viewer-shell .live-refresh .muted { display:none; }
            .viewer-shell .live-refresh .btn { width:auto; min-height:38px; margin-left:auto; padding:6px 10px; font-size:13px; }
        }
        @media (max-width: 360px) {
            .brand-name { display:none; }
            .brand-short { display:block; }
            .brand { max-width:none; }
            .top nav { gap:5px; }
            .mobile-menu summary { padding-right:10px; padding-left:10px; }
        }

        /* EasyKids operational theme: high-contrast controls for event staff. */
        :root {
            --ink: #13243a; --muted: #5d7088; --line: #d7e1eb; --line-strong: #b9c8d8;
            --card: #ffffff; --bg: #eef4f8; --soft: #f3f7fa; --brand: #1769aa;
            --good: #087a52; --warn: #9b5b00; --bad: #b4233f; --blue: #1769aa;
            --top-height: 76px;
        }
        html { color-scheme: light; }
        body { font-family: "LINE Seed Sans TH", "Segoe UI", Tahoma, sans-serif; font-size: 16px; background: linear-gradient(180deg, #eaf3fb 0, #f5f8fb 240px, var(--bg) 100%); }
        .top { border-bottom-color: #c5dbe9; background: rgba(255, 255, 255, .97); box-shadow: 0 4px 18px rgb(10 35 59 / .08); }
        .top .inner { max-width: 1360px; min-height: var(--top-height); padding-top: 10px; padding-bottom: 10px; }
        .brand { gap: 12px; color: #123756; font-size: 17px; letter-spacing: 0; }
        .brand-logos { display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto; }
        .brand-logo-slot { display: grid; width: 108px; height: 46px; place-items: center; }
        .brand-logo { grid-area: 1 / 1; width: 100%; height: 100%; object-fit: contain; }
        .brand-logo-divider { width: 1px; height: 30px; background: #b9d3e5; }
        .brand-logo--dark { display: none; }
        .brand-mark { display: none; }
        .top nav a, .nav-button, .account-label { color: #315675; }
        .top nav a:hover, .nav-button:hover { background: #e7f3fa; color: #0d4f7e; }
        .language-menu summary, .mobile-menu summary { border-color: #b9d3e5; background: #f4f9fc; color: #164a70; }
        .language-icon, .language-chevron { color: #27628b; }
        .container { max-width: 1360px; padding-top: 32px; }
        .page-head h1 { color: #0d3558; font-size: 29px; font-weight: 700; letter-spacing: 0; }
        .card { border-color: var(--line); border-radius: 8px; box-shadow: 0 8px 24px rgb(25 63 95 / .07); }
        .card::before { content: ""; display: block; width: 48px; height: 3px; margin: -21px 0 18px; border-radius: 3px; background: linear-gradient(90deg, #2ba8d8, #1769aa); }
        .danger-card::before { background:linear-gradient(90deg,#ef4444,#b4233f); }
        .stat { border: 1px solid #dde7ef; background: #f6f9fc; }
        .btn { border-radius: 7px; background: #1769aa; box-shadow: 0 3px 8px rgb(23 105 170 / .2); }
        .btn.secondary { border-color: #c6d5e2; background: #ffffff; color: #173c61; box-shadow: none; }
        .btn.danger { background: #b4233f; }
        .badge { border-color: #c9dce9; background: #eff7fc; color: #245375; }
        .badge.LIVE, .badge.READY { border-color: #a8d8c2; background: #eaf9f1; color: #087a52; }
        .tabs a.active::after { background: #1aa4d7; }
        .tabs a:hover, .tabs a.active { color: #0d5e99; }
        input, select, textarea, .smart-select-trigger { border-color: #bdcfdd; background: #ffffff; }
        input:focus, select:focus, textarea:focus, .smart-select-trigger:focus-visible, .smart-select-trigger[aria-expanded="true"] { border-color: #249ad0; box-shadow: 0 0 0 3px rgb(36 154 208 / .16); }
        .score-stepper, .match, .participant-item { border-color: var(--line); background: var(--card); }
        .score-stepper button { background: #e8f3fa; color: #0e5e91; }
        .score-stepper button:hover { background: #d5eaf6; }

        body[data-theme="dark"] {
            --ink: #edf5ff; --muted: #b4c6d9; --line: #2b4964; --line-strong: #3e6282;
            --card: #102237; --bg: #071524; --soft: #162d45; --brand: #45b5e8;
            --good: #5bd69b; --warn: #ffd27a; --bad: #ff8299; --blue: #6fc7ef;
            color-scheme: dark;
        }
        body[data-theme="dark"] { background: radial-gradient(circle at 4% 0%, rgb(53 181 227 / .15), transparent 25%), radial-gradient(circle at 98% 8%, rgb(93 106 255 / .13), transparent 28%), linear-gradient(180deg, #071524, #091827 48%, #06121e); }
        body[data-theme="dark"] .top { border-bottom-color: #183d5d; background: rgba(7, 25, 43, .97); }
        body[data-theme="dark"] .brand { color: #ffffff; }
        body[data-theme="dark"] .brand-logo-divider { background: rgb(158 213 248 / .34); }
        body[data-theme="dark"] .brand-logo--light { display: none; }
        body[data-theme="dark"] .brand-logo--dark { display: block; }
        body[data-theme="dark"] .top nav a, body[data-theme="dark"] .nav-button, body[data-theme="dark"] .account-label { color: #d5e6f5; }
        body[data-theme="dark"] .top nav a:hover, body[data-theme="dark"] .nav-button:hover { background: rgb(103 182 235 / .18); color: #ffffff; }
        body[data-theme="dark"] .language-menu summary, body[data-theme="dark"] .mobile-menu summary { border-color: rgb(158 213 248 / .3); background: rgb(103 182 235 / .15); color: #ffffff; }
        body[data-theme="dark"] .language-icon, body[data-theme="dark"] .language-chevron { color: #ccecff; }
        body[data-theme="dark"] .card, body[data-theme="dark"] .match, body[data-theme="dark"] .participant-item { box-shadow: 0 11px 28px rgb(0 0 0 / .22); }
        body[data-theme="dark"] .page-head h1 { color: #f2f8ff; }
        body[data-theme="dark"] .stat, body[data-theme="dark"] .detail-item, body[data-theme="dark"] .bracket-team, body[data-theme="dark"] .match-team-row { border-color: #294862; background: #132b43; }
        body[data-theme="dark"] .btn.secondary, body[data-theme="dark"] input, body[data-theme="dark"] select, body[data-theme="dark"] textarea, body[data-theme="dark"] .smart-select-trigger, body[data-theme="dark"] .smart-select-popover { border-color: #395b78; background: #0b1b2b; color: var(--ink); }
        body[data-theme="dark"] .badge { border-color: #365c78; background: #142f49; color: #c5e8fc; }
        body[data-theme="dark"] .badge.LIVE, body[data-theme="dark"] .badge.READY { border-color: #24785b; background: #103b31; color: #8aefbc; }
        body[data-theme="dark"] .tabs { border-color: #2d4c66; }
        body[data-theme="dark"] .tabs a.active::after { background: #45b5e8; }
        body[data-theme="dark"] .tabs a:hover, body[data-theme="dark"] .tabs a.active { color: #77d1f5; }
        body[data-theme="dark"] .score-stepper button { background: #18354e; color: #91daf8; }
        body[data-theme="dark"] .score-stepper input { background: #0b1b2b; color: var(--ink); }
        body[data-theme="dark"] .mobile-popover, body[data-theme="dark"] .language-popover { border-color: #365b77; background: #10243a; }
        body[data-theme="dark"] .alert.success, body[data-theme="dark"] .live-refresh { border-color:#28775c; background:#103b31; color:#9af0c4; }
        body[data-theme="dark"] .alert.error { border-color:#8d3d54; background:#3b1723; color:#ffb7c4; }
        body[data-theme="dark"] .alert.warning { border-color:#9a6b25; background:#38290f; color:#ffe09a; }
        body[data-theme="dark"] .alert.neutral { border-color:#365b77; background:#10243a; color:#c5d8e8; }
        body[data-theme="dark"] .danger-card { border-color:#8d3d54; }
        body[data-theme="dark"] .danger-card h2 { color:#ffb7c4; }
        body[data-theme="dark"] .match-side.red { border-color:#8d3d54; background:#3b1723; color:#ffb7c4; }
        body[data-theme="dark"] .match-side.blue { border-color:#315f84; background:#102d47; color:#b9e5fb; }
        body[data-theme="dark"] .current-match-indicator { border-color:#9a6b25; background:#38290f; color:#ffe09a; }
        body[data-theme="dark"] .view-only-banner { border-color:#315f84; background:#102d47; color:#b9e5fb; }
        body[data-theme="dark"] .participant-item, body[data-theme="dark"] .match-team-row, body[data-theme="dark"] .choice-card { border-color:#365a76; background:#10263b; }
        body[data-theme="dark"] .participant-item details > .participant-summary:hover { background:#152e47; }
        body[data-theme="dark"] .participant-edit, body[data-theme="dark"] .user-card-body, body[data-theme="dark"] .format-config-panel { border-color:#2c4b66; background:#0b1b2b; }
        body[data-theme="dark"] .format-config-icon, body[data-theme="dark"] .short-link-input { border-color:#395b78; background:#10263b; color:#c7e5f7; }
        body[data-theme="dark"] .choice-card:has(input:checked) { border-color:#45b5e8; background:#102f49; box-shadow:0 0 0 2px rgb(69 181 232 / .18); }
        body[data-theme="dark"] .match-team-row.winner { background:#103b31; color:#94efc0; }
        body[data-theme="dark"] .match:target { border-color:#57c6f2; box-shadow:0 0 0 3px rgb(87 198 242 / .18); }
        body[data-theme="dark"] .token-output code { background:#0b1b2b; color:#9af0c4; }
        body[data-theme="dark"] .api-content p { color:#c4d6e6; }
        body[data-theme="dark"] .api-note { border-color:#d69b36; background:#3b2b11; color:#ffe09a; }
        @media (max-width:680px) { body[data-theme="dark"] .standings-table th:first-child, body[data-theme="dark"] .standings-table td:first-child, body[data-theme="dark"] .standings-table th:nth-child(2), body[data-theme="dark"] .standings-table td:nth-child(2) { background:#102237; } }
        @media (max-width: 680px) {
            .brand-logo-slot { width:76px; height:38px; }
            .brand-logo-slot:last-child, .brand-logo-divider { display:none; }
            .brand-name, .brand-short { display:none; }
            .card::before { margin:-14px 0 12px; }
        }

        /* Minimal dark-only interface. */
        :root {
            --ink:#e7edf4; --muted:#8c99a8; --line:#27313c; --line-strong:#3a4653;
            --card:#111820; --bg:#0a0f15; --soft:#171f29; --brand:#3b82b6;
            --good:#59c995; --warn:#e5b45d; --bad:#e76d83; --blue:#69b7e8;
            --top-height:62px;
        }
        html { color-scheme:dark; }
        body, body[data-theme="dark"] { background:#0a0f15; color:var(--ink); font-size:15px; }
        .top, body[data-theme="dark"] .top { border-bottom:1px solid var(--line); background:#0d131a; box-shadow:none; backdrop-filter:none; }
        .top .inner { max-width:1240px; min-height:var(--top-height); padding:8px 22px; }
        .brand { gap:9px; color:var(--ink); font-size:15px; }
        .brand-logo-slot { width:86px; height:34px; }
        .brand-logo--dark { display:block; }
        .brand-name { color:#cdd7e2; }
        .top nav { gap:3px; }
        .top nav a, .nav-button, body[data-theme="dark"] .top nav a, body[data-theme="dark"] .nav-button { min-height:38px; padding:8px 10px; border-radius:6px; color:var(--muted); font-size:13px; }
        .top nav a:hover, .nav-button:hover, body[data-theme="dark"] .top nav a:hover, body[data-theme="dark"] .nav-button:hover { background:var(--soft); color:var(--ink); }
        .top nav a.active { background:var(--soft); color:var(--ink); }
        .account-label { max-width:150px; color:var(--muted); }
        .mobile-menu summary, .language-menu summary, body[data-theme="dark"] .mobile-menu summary, body[data-theme="dark"] .language-menu summary { min-height:38px; border-color:var(--line); border-radius:6px; background:#111820; color:var(--ink); box-shadow:none; }
        .language-menu summary { min-width:92px; }
        .mobile-popover, .language-popover, body[data-theme="dark"] .mobile-popover, body[data-theme="dark"] .language-popover { border-color:var(--line); border-radius:7px; background:#111820; box-shadow:0 12px 28px rgb(0 0 0 / .35); }
        .language-popover::before { border-color:var(--line); background:#111820; }
        .language-option:hover, .language-option.active { background:var(--soft); }
        .language-code { border-color:var(--line); color:#b8c4d0; }
        .container, .viewer-shell .container { max-width:1240px; padding:24px 22px 48px; }
        .container-wide, .viewer-shell .container-wide { max-width:none; }
        .page-head { margin-bottom:16px; }
        .page-head h1, body[data-theme="dark"] .page-head h1 { color:var(--ink); font-size:24px; letter-spacing:-.015em; }
        h2 { font-size:16px; }
        .card, body[data-theme="dark"] .card { padding:17px; border:1px solid var(--line); border-radius:7px; background:var(--card); box-shadow:none; }
        .card::before { display:none; }
        .grid { gap:12px; }
        .stats { gap:8px; }
        .stat, body[data-theme="dark"] .stat { padding:11px; border:1px solid var(--line); border-radius:6px; background:var(--soft); }
        .stat strong { font-size:18px; }
        .btn, body[data-theme="dark"] .btn { min-height:40px; padding:8px 13px; border-radius:6px; background:var(--brand); box-shadow:none; font-size:14px; }
        .btn:hover { opacity:1; filter:none; }
        .btn.secondary, body[data-theme="dark"] .btn.secondary { border-color:var(--line-strong); background:#141c25; color:var(--ink); }
        .btn.danger { background:#9f3f52; }
        .btn.small { min-height:36px; padding:6px 10px; font-size:13px; }
        .badge, body[data-theme="dark"] .badge { border-color:var(--line-strong); background:#18212b; color:#aab7c4; letter-spacing:0; }
        .badge.LIVE, .badge.READY, body[data-theme="dark"] .badge.LIVE, body[data-theme="dark"] .badge.READY { border-color:#275b48; background:#10271f; color:#78d6aa; }
        .badge.COMPLETED, .badge.FINISHED, body[data-theme="dark"] .badge.COMPLETED, body[data-theme="dark"] .badge.FINISHED { background:#1a222c; color:#aeb9c5; }
        .badge.DRAFT, .badge.PENDING, .badge.ARCHIVED, body[data-theme="dark"] .badge.DRAFT, body[data-theme="dark"] .badge.PENDING, body[data-theme="dark"] .badge.ARCHIVED { background:#141b23; color:#8694a3; }
        .alert { border-radius:6px; }
        .alert.success, body[data-theme="dark"] .alert.success { border-color:#275b48; background:#10271f; color:#8bddb5; }
        .alert.warning, body[data-theme="dark"] .alert.warning { border-color:#6d5529; background:#2a2111; color:#edc779; }
        .alert.error, body[data-theme="dark"] .alert.error { border-color:#713544; background:#2b171d; color:#f09aab; }
        .alert.neutral, body[data-theme="dark"] .alert.neutral { border-color:var(--line); background:var(--soft); color:#b4c0cc; }
        .admin-welcome { align-items:flex-start; border-color:var(--line); background:var(--card); }
        .admin-welcome h2 { margin-top:7px; }
        .tournament-card:hover { border-color:var(--line); box-shadow:none; transform:none; }
        .filter-bar { margin-bottom:14px; padding:10px; border:1px solid var(--line); border-radius:7px; background:#0d131a; }
        .filter-bar .field { min-width:190px; }
        input, select, textarea, .smart-select-trigger, body[data-theme="dark"] input, body[data-theme="dark"] select, body[data-theme="dark"] textarea, body[data-theme="dark"] .smart-select-trigger { min-height:42px; border-color:var(--line-strong); border-radius:6px; background:#0c1219; color:var(--ink); }
        input:focus, select:focus, textarea:focus, .smart-select-trigger:focus-visible, .smart-select-trigger[aria-expanded="true"] { border-color:#4d8db8; box-shadow:0 0 0 2px rgb(77 141 184 / .2); }
        input[readonly], select:disabled, .smart-select-trigger:disabled { background:#121820; color:#7f8b98; }
        @media(hover:hover) and (pointer:fine) {
            body[data-theme="easykids"] input:hover:not([type="checkbox"]):not([type="radio"]):not(:disabled):not([readonly]),
            body[data-theme="easykids"] select:hover:not(:disabled),
            body[data-theme="easykids"] textarea:hover:not(:disabled):not([readonly]) {
                border-color:#4d8db8 !important;
                background-color:#0c1219 !important;
                color:#e7edf4 !important;
                -webkit-text-fill-color:#e7edf4;
            }
        }
        body[data-theme="easykids"] input:-webkit-autofill,
        body[data-theme="easykids"] input:-webkit-autofill:hover,
        body[data-theme="easykids"] input:-webkit-autofill:focus {
            -webkit-box-shadow:0 0 0 1000px #0c1219 inset;
            -webkit-text-fill-color:#e7edf4;
            caret-color:#e7edf4;
        }
        body[data-theme="easykids"] .smart-select-trigger,
        body[data-theme="easykids"] .smart-select-trigger:hover:not(:disabled),
        body[data-theme="easykids"] .smart-select-trigger:focus-visible,
        body[data-theme="easykids"] .smart-select-trigger[aria-expanded="true"] {
            border-color:#3a4a59;
            background:#0c1219;
            color:#e7edf4;
            box-shadow:none;
        }
        body[data-theme="easykids"] .smart-select-trigger[aria-expanded="true"] { border-color:#5f91b2; }
        body[data-theme="easykids"] .smart-select-popover {
            padding:4px;
            border-color:#344351;
            background:#111820;
            box-shadow:none;
        }
        body[data-theme="easykids"] .smart-select-option { min-height:38px; padding:7px 9px; color:#dce5ee; }
        body[data-theme="easykids"] .smart-select-option:hover,
        body[data-theme="easykids"] .smart-select-option.focused { background:#1a2530; }
        body[data-theme="easykids"] .smart-select-option.selected { background:#223342; color:#f4f8fc; }
        .smart-select-popover, body[data-theme="dark"] .smart-select-popover { border-color:var(--line); border-radius:7px; background:#111820; box-shadow:0 14px 28px rgb(0 0 0 / .38); }
        .smart-select-option:hover, .smart-select-option.focused, .smart-select-option.selected { background:var(--soft); }
        .tabs { margin-bottom:18px; border-color:var(--line); }
        .tabs a { min-height:42px; padding:9px 12px; color:var(--muted); font-size:13px; }
        .tabs a:hover, .tabs a.active, body[data-theme="dark"] .tabs a:hover, body[data-theme="dark"] .tabs a.active { color:var(--ink); }
        .tabs a.active::after { height:2px; background:#69b7e8; }
        .match, body[data-theme="dark"] .match, .participant-item, body[data-theme="dark"] .participant-item { border-color:var(--line); border-radius:7px; background:var(--card); box-shadow:none; }
        .detail-item, body[data-theme="dark"] .detail-item, .match-team-row, body[data-theme="dark"] .match-team-row { border-color:var(--line); border-radius:6px; background:var(--soft); }
        table { font-size:14px; }
        th, td { border-color:var(--line); }
        th { color:var(--muted); }
        .live-refresh, body[data-theme="dark"] .live-refresh { border-color:#275b48; border-radius:6px; background:#10271f; color:#8bddb5; }
        .progress-button { background:#a45522; box-shadow:none; }
        .current-match-indicator, body[data-theme="dark"] .current-match-indicator { border-color:#6d5529; border-radius:6px; background:#2a2111; color:#edc779; }
        .auth-card { max-width:430px; }
        .auth-card-wide { max-width:620px; }
        .danger-card { border-color:#713544; }
        .danger-card h2 { color:#f09aab; }
        a:hover { opacity:1; }
        .btn, .top nav a, .nav-button, summary, .tournament-card { transition:border-color .14s, background-color .14s, color .14s, transform .1s; }
        input[type="file"] { padding:6px; cursor:pointer; }
        input[type="file"]::file-selector-button, input[type="file"]::-webkit-file-upload-button { min-height:34px; margin-right:10px; padding:6px 14px; border:1px solid #d4af37; border-radius:6px; background:#d4af37; color:#171a20; font:inherit; font-weight:800; cursor:pointer; }
        .btn:active:not(:disabled), .nav-button:active, .top nav a:active { transform:translateY(1px); }
        @media(hover:hover) and (pointer:fine) {
            .btn:hover:not(:disabled):not([aria-disabled="true"]) { filter:none; background:#4a91c2; }
            .btn.secondary:hover:not(:disabled):not([aria-disabled="true"]) { border-color:#526172; background:#1b2631; }
            .btn.danger:hover:not(:disabled) { background:#b64b60; }
            .tournament-card:hover { border-color:#526172; background:#141c25; }
            .participant-item details>.participant-summary:hover { background:#1b2530; }
        }
        @media(max-width:680px) {
            .top .inner { min-height:56px; padding:7px 12px; }
            .brand-logo-slot { width:72px; height:32px; }
            .brand-name, .brand-short { display:none; }
            .container, .viewer-shell .container { padding:14px 12px 36px; }
            .card { padding:13px; }
            .page-head { margin-bottom:13px; }
            .page-head h1 { font-size:21px; }
            .page-head > .actions { grid-template-columns:1fr 1fr; }
            .btn { min-height:44px; }
            .tabs { top:56px; margin-right:-12px; margin-left:-12px; padding:0 5px; background:#0a0f15; backdrop-filter:none; }
            .tabs a { min-height:42px; padding:9px 10px; }
            .form-grid { gap:0 12px; }
            .mobile-popover, .language-popover { top:56px; right:12px; }
            .admin-welcome { gap:14px; }
        }

        /* Responsive hardening for narrow phones, tablets, and landscape screens. */
        html, body { width:100%; max-width:100%; }
        body { overflow-x:hidden; overflow-x:clip; }
        main, section, article, form, fieldset { min-width:0; }
        img, video { max-width:100%; height:auto; }
        .btn { max-width:100%; text-align:center; white-space:normal; }
        .split-actions > :first-child { min-width:0; }
        .mobile-popover, .language-popover { max-height:calc(100dvh - var(--top-height) - 12px); overflow-y:auto; overscroll-behavior:contain; }

        @media(max-width:900px) {
            .brand-name { display:none; }
        }

        @media(max-width:680px) {
            :root { --top-height:56px; }
            html { scroll-padding-top:104px; }
            .top .inner { gap:8px; padding-right:max(12px,env(safe-area-inset-right)); padding-left:max(12px,env(safe-area-inset-left)); }
            .brand { flex:1 1 auto; max-width:none; }
            .top nav { flex:0 0 auto; gap:5px; }
            .mobile-menu summary, .language-menu summary { min-height:42px; }
            .mobile-popover, .language-popover { right:max(12px,env(safe-area-inset-right)); left:max(12px,env(safe-area-inset-left)); width:auto; }
            .container, .viewer-shell .container { padding-right:max(12px,env(safe-area-inset-right)); padding-bottom:max(36px,env(safe-area-inset-bottom)); padding-left:max(12px,env(safe-area-inset-left)); }
            .page-head > :first-child { width:100%; }
            .page-head .muted, .split-actions > :first-child { overflow-wrap:anywhere; }
            .split-actions { align-items:stretch !important; flex-direction:column; }
            .split-actions > .btn, .split-actions > form, .split-actions > form .btn { width:100%; }
            .split-actions > .badge { align-self:flex-start; }
            .split-actions > .actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); width:100%; }
            .split-actions > .actions > form, .split-actions > .actions .btn { width:100%; }
            .table-wrap { scrollbar-gutter:stable; }
            input[type="file"] { max-width:100%; padding:7px; }
        }

        @media(max-width:480px) {
            .page-head > .actions { display:grid; grid-template-columns:1fr; }
            .page-head > .actions > *, .page-head > .btn { width:100%; }
            .page-head > .actions form .btn { width:100%; }
            form > .actions:not(.match-card-actions) { display:grid; grid-template-columns:1fr; align-items:stretch; }
            form > .actions:not(.match-card-actions) > *, form > .actions:not(.match-card-actions) .btn { width:100%; }
            .participant-summary { grid-template-columns:32px minmax(0,1fr) auto; }
            .participant-summary > .badge { grid-column:2; justify-self:start; }
            .participant-summary > :last-child { grid-column:3; grid-row:1 / span 2; }
            .split-actions > .actions { grid-template-columns:1fr; }
        }

        @media(max-width:360px) {
            .top .inner { padding-right:8px; padding-left:8px; }
            .brand-logo-slot { width:62px; }
            .mobile-menu summary { padding-right:9px; padding-left:9px; }
            .language-menu summary { padding-right:8px; padding-left:8px; }
            .stats { grid-template-columns:1fr; }
        }

        @media(max-height:480px) and (orientation:landscape) {
            .mobile-popover, .language-popover { max-height:calc(100dvh - 64px); }
        }
    </style>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="{{ asset('assets/js/smart-select-fallback.js') }}" defer></script>
    @endif
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
                        <button class="language-option {{ app()->isLocale('th') ? 'active' : '' }}" type="submit"><span class="language-code">TH</span><span class="language-name">{{ __('ui.language_thai') }}<small>{{ __('ui.language_thai_native') }}</small></span><svg class="language-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg></button>
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
