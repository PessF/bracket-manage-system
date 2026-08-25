@extends('layouts.app')
@section('title', __('ui.title_bracket').' · '.$tournament->name)
@section('container-class', 'container-wide')

@push('styles')
<style>
    .bracket-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .bracket-hint { display:flex; align-items:center; gap:7px; color:var(--muted); font-size:13px; }
    .bracket-hint svg { width:15px; height:15px; }
    .bracket-section { margin:0 0 30px; }
    .bracket-section-head { display:flex; align-items:center; gap:10px; margin:0 0 10px; }
    .bracket-section-head h2 { margin:0; font-size:16px; }
    .bracket-count { color:var(--muted); font-size:12px; }
    .bracket-admin-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .bracket-admin-actions form { margin:0; }
    .bracket-viewport { position:relative; overflow:auto; overscroll-behavior-inline:contain; min-height:190px; border:1px solid var(--line); border-radius:7px; background:#0c1219; scrollbar-color:#3a4653 transparent; -webkit-overflow-scrolling:touch; }
    .bracket-canvas { position:relative; min-width:100%; }
    .bracket-connectors { position:absolute; inset:0; z-index:1; overflow:visible; pointer-events:none; }
    .bracket-connector { fill:none; stroke:#cbd5e1; stroke-width:2; stroke-linejoin:round; vector-effect:non-scaling-stroke; }
    .bracket-round-title { position:absolute; top:0; height:44px; display:flex; align-items:center; color:#71717a; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.055em; }
    .bracket-match-node { position:absolute; z-index:2; width:272px; min-height:126px; padding:10px; border:1px solid var(--line); border-radius:7px; background:var(--card); box-shadow:none; transition:border-color .14s; }
    .bracket-match-node:hover { z-index:4; border-color:var(--line-strong); box-shadow:none; transform:none; }
    .bracket-match-meta { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:6px; min-height:24px; margin-bottom:6px; color:var(--muted); font-size:11px; }
    .bracket-match-number { display:inline-flex; align-items:baseline; gap:4px; font-weight:700; color:#52525b; }
    .bracket-match-number strong { font-size:16px; font-weight:900; }
    .bracket-match-number span { font-size:10px; }
    .bracket-award-badge { display:inline-flex; align-items:center; min-height:22px; padding:2px 8px; border:1px solid var(--line); border-radius:999px; font-size:10px; font-weight:900; white-space:nowrap; }
    .bracket-award-badge.champion { border-color:#f6c663; background:#fff7df; color:#8a5200; }
    .bracket-award-badge.third { border-color:#d49b72; background:#fff0e5; color:#854d2d; }
    .bracket-team { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:7px; align-items:center; min-height:35px; margin:0; padding:6px 7px; background:var(--soft); border:1px solid transparent; border-radius:5px; font-weight:inherit; }
    .bracket-team + .bracket-team { margin-top:3px; }
    .bracket-team.winner { background:#f0fdf4; border-color:#dcfce7; color:#166534; font-weight:650; }
    .bracket-team.waiting { color:#a1a1aa; font-size:13px; }
    .bracket-team-name { display:flex; align-items:center; gap:7px; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .bracket-seed { display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; min-width:21px; height:21px; padding:0 5px; border-radius:4px; background:#e4e4e7; color:#52525b; font:700 11px ui-monospace,monospace; }
    .bracket-score { min-width:24px; text-align:center; font:700 14px ui-monospace,SFMono-Regular,monospace; }
    .bracket-card-actions { display:flex; align-items:center; justify-content:flex-end; gap:5px; margin-top:5px; }
    .bracket-card-actions .bracket-icon-button { display:grid; place-items:center; width:36px; min-width:36px; min-height:36px; height:36px; margin:0; padding:0; border-radius:6px; }
    .bracket-icon-button svg { display:block; width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .bracket-current { display:inline-flex; align-items:center; gap:6px; min-height:36px; margin-right:auto; color:var(--warn); font-size:10px; font-weight:750; }
    .bracket-current span { width:7px; height:7px; border-radius:50%; background:#f97316; box-shadow:0 0 0 3px rgb(249 115 22 / .14); }
    .score-modal { width:min(440px,calc(100vw - 24px)); max-height:calc(100dvh - 24px); margin:auto; padding:0; overflow:auto; border:1px solid var(--line-strong); border-radius:8px; background:var(--card); color:var(--ink); box-shadow:0 24px 70px rgb(0 0 0 / .55); }
    .score-modal::backdrop { background:rgb(2 7 12 / .76); backdrop-filter:blur(2px); }
    .score-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 15px; border-bottom:1px solid var(--line); }
    .score-modal-head h2 { margin:0; font-size:16px; }
    .score-modal-close { width:32px; min-width:32px; min-height:32px; padding:4px; border:0; border-radius:5px; background:transparent; color:var(--muted); font-size:22px; line-height:1; cursor:pointer; }
    .score-modal-close:hover { background:var(--soft); color:var(--ink); }
    .score-modal-form { padding:15px; }
    .score-modal-teams { display:grid; gap:9px; }
    .score-modal-team { display:grid; grid-template-columns:minmax(0,1fr) 150px; align-items:center; gap:12px; margin:0; padding:10px; border:1px solid var(--line); border-radius:6px; background:var(--soft); }
    .score-modal-team-name { display:flex; align-items:center; gap:7px; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    .score-modal-team .score-stepper { min-width:0; }
    .score-modal-actions { display:flex; justify-content:flex-end; gap:7px; margin-top:14px; }
    .bracket-destinations { display:flex; align-items:center; gap:5px; margin-top:7px; color:#71717a; font-size:12px; }
    .bracket-destination { display:inline-flex; align-items:center; gap:4px; min-width:0; height:20px; padding:0 6px; border:1px solid var(--line); border-radius:999px; background:var(--soft); white-space:nowrap; }
    .bracket-destination strong { font-weight:800; }
    .bracket-destination.win { border-color:#9bd9bf; background:#e8f8f0; color:#116f4f; }
    .bracket-destination.loss { border-color:#c9d7e2; background:#f2f7fb; color:#5b6e7e; }
    .bracket-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:12px; padding:14px; }
    .bracket-grid .bracket-match-node { position:relative; width:auto; height:auto !important; min-height:118px; left:auto !important; top:auto !important; }
    .bracket-legend { display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:12px; color:var(--muted); }
    .bracket-legend span { display:inline-flex; align-items:center; gap:5px; }
    .bracket-view-switcher { display:flex; gap:7px; margin:0 0 12px; overflow:auto; padding-bottom:2px; scrollbar-width:thin; }
    .bracket-view-switcher a { flex:0 0 auto; min-height:34px; padding:7px 11px; border:1px solid var(--line); border-radius:999px; background:var(--soft); color:var(--muted); font-size:12px; font-weight:850; text-decoration:none; }
    .bracket-view-switcher a.active { border-color:rgb(102 215 237 / .42); background:rgb(31 43 70 / .92); color:#8be9ff; box-shadow:0 0 0 2px rgb(102 215 237 / .08); }
    .bracket-view-select { display:none; margin:0 0 12px; }
    .bracket-view-select label { color:var(--muted); font-size:12px; font-weight:800; }
    .bracket-view-select select { min-height:48px; border-radius:10px; font-weight:850; }
    .legend-line { display:inline-block; width:22px; border-top:2px solid #cbd5e1; }
    .legend-win { display:inline-block; width:12px; height:12px; border-radius:3px; background:#f0fdf4; border:1px solid #dcfce7; }
    body[data-theme="dark"] .bracket-viewport { background:#0c1219; box-shadow:none; scrollbar-color:#3a4653 transparent; }
    body[data-theme="dark"] .bracket-connector, body[data-theme="dark"] .legend-line { stroke:#4e7797; border-color:#4e7797; }
    body[data-theme="dark"] .bracket-round-title, body[data-theme="dark"] .bracket-destinations { color:#afc8dd; }
    body[data-theme="dark"] .bracket-match-node { border-color:var(--line); background:var(--card); box-shadow:none; }
    body[data-theme="dark"] .bracket-match-node:hover { border-color:var(--line-strong); box-shadow:none; }
    body[data-theme="dark"] .bracket-match-number { color:#d7e9f7; }
    body[data-theme="dark"] .bracket-team { background:#132e47; }
    body[data-theme="dark"] .bracket-team.winner, body[data-theme="dark"] .legend-win { border-color:#28775c; background:#103b31; color:#94efc0; }
    body[data-theme="dark"] .bracket-team.waiting { color:#8ea9bf; }
    body[data-theme="dark"] .bracket-seed { background:#26465f; color:#d8ebf8; }
    .viewer-event-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:10px; }
    .viewer-event-head h1 { margin:0; color:var(--ink); font-size:22px; line-height:1.25; }
    .viewer-event-head p { margin:2px 0 0; color:var(--muted); font-size:13px; }
    .viewer-bracket-help { margin:0 0 10px; color:var(--muted); font-size:12px; }
    body[data-theme="dark"] .viewer-event-head h1 { color:#f2f8ff; }
    body[data-theme="easykids"] .bracket-toolbar { padding:10px 12px; border:1px solid rgb(116 147 202 / .14); border-radius:8px; background:rgb(16 22 34 / .92); }
    body[data-theme="easykids"] .bracket-hint,
    body[data-theme="easykids"] .bracket-legend,
    body[data-theme="easykids"] .bracket-count { color:#667789; }
    body[data-theme="easykids"] .bracket-section-head h2 { color:#f3f7ff; }
    body[data-theme="easykids"] .bracket-viewport { padding-top:12px; border-color:rgb(116 147 202 / .16); background:radial-gradient(circle at top left, rgb(102 215 237 / .08), transparent 26%), radial-gradient(circle at top right, rgb(140 136 255 / .10), transparent 32%), linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px), #080d16; background-size:auto, auto, 28px 28px, 28px 28px, auto; box-shadow:inset 0 1px 0 rgb(255 255 255 / .04), 0 18px 44px rgb(6 9 17 / .42); scrollbar-color:#36516d transparent; }
    body[data-theme="easykids"] .bracket-canvas { padding-top:22px; }
    body[data-theme="easykids"] .bracket-connector,
    body[data-theme="easykids"] .legend-line { stroke:rgb(180 205 255 / .68); border-color:rgb(180 205 255 / .68); }
    body[data-theme="easykids"] .bracket-round-title,
    body[data-theme="easykids"] .bracket-destinations { color:#718395; }
    body[data-theme="easykids"] .bracket-round-title { justify-content:flex-start; height:32px; padding:0 12px; border:1px solid rgb(116 147 202 / .16); border-top:3px solid #66d7ed; border-radius:6px; background:linear-gradient(180deg, rgb(24 31 47 / .98), rgb(12 16 25 / .98)); color:#dff5ff; font-size:12px; letter-spacing:0; text-transform:none; box-shadow:0 0 0 1px rgb(102 215 237 / .04), 0 10px 24px rgb(0 0 0 / .24); }
    body[data-theme="easykids"] .bracket-round-title[data-round-tone="red"] { border-top-color:#ff7591; }
    body[data-theme="easykids"] .bracket-round-title[data-round-tone="green"] { border-top-color:#8c88ff; }
    body[data-theme="easykids"] .bracket-round-title[data-round-tone="orange"] { border-top-color:#f0be72; }
    body[data-theme="easykids"] .bracket-match-node { width:236px; min-height:92px; padding:7px; border-color:rgb(116 147 202 / .16); background:linear-gradient(180deg, rgb(18 24 38 / .96), rgb(10 14 23 / .98)); box-shadow:0 0 0 1px rgb(113 150 218 / .06), 0 12px 28px rgb(0 0 0 / .24); }
    body[data-theme="easykids"] .bracket-match-node.is-finished { border-color:rgb(116 147 202 / .20); background:linear-gradient(180deg, rgb(20 26 39 / .96), rgb(11 16 26 / .98)); box-shadow:0 0 0 1px rgb(113 150 218 / .06), 0 10px 22px rgb(0 0 0 / .22); }
    body[data-theme="easykids"] .bracket-match-node.is-finished::after { content:""; position:absolute; inset:0 auto 0 0; width:4px; border-radius:7px 0 0 7px; background:#8290aa; }
    body[data-theme="easykids"] .bracket-match-node.is-finished .badge.FINISHED { font-weight:850; }
    body[data-theme="easykids"] .bracket-match-node.is-ready { border-color:rgb(102 215 237 / .24); }
    body[data-theme="easykids"] .bracket-match-node.is-unscored { border-color:rgb(240 190 114 / .58); box-shadow:0 0 0 1px rgb(240 190 114 / .14), 0 0 24px rgb(240 190 114 / .08), 0 12px 28px rgb(0 0 0 / .24); }
    body[data-theme="easykids"] .bracket-match-node.is-unscored .bracket-score { color:#f0be72; }
    body[data-theme="easykids"] .bracket-match-node.in-progress { border-color:rgb(255 117 145 / .62); box-shadow:0 0 0 2px rgb(255 117 145 / .13), 0 0 28px rgb(255 117 145 / .12), 0 12px 28px rgb(0 0 0 / .26); }
    body[data-theme="easykids"] .bracket-match-node:hover { border-color:rgb(102 215 237 / .34); box-shadow:0 0 0 1px rgb(102 215 237 / .08), 0 16px 34px rgb(0 0 0 / .30); }
    body[data-theme="easykids"] .bracket-match-meta { min-height:20px; margin-bottom:4px; font-size:10px; }
    body[data-theme="easykids"] .bracket-match-number { color:#dff5ff; }
    body[data-theme="easykids"] .bracket-award-badge.champion { border-color:rgb(240 190 114 / .56); background:linear-gradient(135deg, rgb(84 58 20 / .92), rgb(240 190 114 / .20)); color:#ffe7aa; box-shadow:0 0 18px rgb(240 190 114 / .14); }
    body[data-theme="easykids"] .bracket-award-badge.third { border-color:rgb(202 132 88 / .56); background:linear-gradient(135deg, rgb(89 52 32 / .92), rgb(202 132 88 / .18)); color:#ffd2b8; box-shadow:0 0 18px rgb(202 132 88 / .12); }
    body[data-theme="easykids"] .bracket-team { min-height:28px; padding:4px 5px; gap:5px; border-color:rgb(116 147 202 / .12); background:rgb(20 26 39 / .88); color:#eef3ff; }
    body[data-theme="easykids"] .bracket-team.winner,
    body[data-theme="easykids"] .legend-win { border-color:rgb(73 207 155 / .42); background:rgb(22 82 59 / .84); color:#e8fff5; box-shadow:inset 3px 0 0 #49cf9b, 0 0 24px rgb(73 207 155 / .12); }
    body[data-theme="easykids"] .bracket-team.winner .bracket-team-name { font-weight:800; }
    body[data-theme="easykids"] .bracket-team.winner .bracket-score { color:#dffef2; font-weight:900; }
    body[data-theme="easykids"] .bracket-team.waiting { color:#8290aa; background:rgb(12 17 27 / .64); }
    body[data-theme="easykids"] .bracket-team-name { gap:5px; font-size:13px; }
    body[data-theme="easykids"] .bracket-team-name span { min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    body[data-theme="easykids"] .bracket-seed { min-width:19px; height:19px; padding:0 4px; background:rgb(31 43 70 / .92); color:#d8e8ff; font-size:10px; }
    body[data-theme="easykids"] .bracket-score { min-width:20px; color:#8be9ff; font-size:13px; }
    body[data-theme="easykids"] .match-side { min-width:38px; height:20px; padding:0 7px; border-color:transparent; color:#fff; font-size:10px; font-weight:900; box-shadow:inset 0 -1px 0 rgb(0 0 0 / .12); }
    body[data-theme="easykids"] .match-side.red { background:#5b1d2f; color:#fff1f5; border-color:rgb(255 117 145 / .46); }
    body[data-theme="easykids"] .match-side.blue { background:#122f5f; color:#dff7ff; border-color:rgb(102 215 237 / .42); }
    body[data-theme="easykids"] .bracket-card-actions { position:absolute; right:-39px; top:50%; z-index:5; flex-direction:column; transform:translateY(-50%); margin-top:0; }
    body[data-theme="easykids"] .bracket-card-actions::before { content:""; position:absolute; top:50%; right:100%; width:9px; border-top:2px solid #b3cada; transform:translateY(-50%); }
    body[data-theme="easykids"] .bracket-card-actions form { margin:0; }
    body[data-theme="easykids"] .bracket-card-actions .bracket-icon-button { width:32px; min-width:32px; height:32px; min-height:32px; border-radius:7px; box-shadow:0 4px 10px rgb(31 143 207 / .18); }
    body[data-theme="easykids"] .record-result-button { border:1px solid rgb(102 215 237 / .34); background:linear-gradient(135deg,#5b89ff,#7b7fff); color:#fff; box-shadow:0 12px 24px rgb(73 117 255 / .22); }
    body[data-theme="easykids"] .edit-result-button { border:1px solid rgb(102 215 237 / .20); background:rgb(26 34 51 / .92); color:#dff5ff; box-shadow:0 8px 18px rgb(0 0 0 / .22); }
    body[data-theme="easykids"] .edit-result-button:hover { background:rgb(31 43 70 / .92); color:#8be9ff; }
    body[data-theme="easykids"] .progress-button { border-color:rgb(240 190 114 / .38); background:rgb(84 58 20 / .82); color:#fff0d6; box-shadow:0 8px 18px rgb(0 0 0 / .22); }
    body[data-theme="easykids"] .bracket-icon-button svg { width:17px; height:17px; }
    body[data-theme="easykids"] .bracket-match-node.in-progress .badge.LIVE { font-weight:900; }
    body[data-theme="easykids"] .bracket-current { display:none; }
    body[data-theme="easykids"] .bracket-match-node.in-progress::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; border-radius:7px 0 0 7px; background:#ff7591; }
    body[data-theme="easykids"] .bracket-destinations { min-height:18px; margin-top:4px; padding-right:0; gap:4px; font-size:10px; }
    body[data-theme="easykids"] .bracket-destination { height:18px; padding:0 5px; border-color:rgb(116 147 202 / .14); background:rgb(17 23 35 / .88); }
    body[data-theme="easykids"] .bracket-destination.win { border-color:rgb(102 215 237 / .32); background:rgb(31 43 70 / .72); color:#8be9ff; }
    body[data-theme="easykids"] .bracket-destination.loss { border-color:rgb(116 147 202 / .14); background:rgb(17 23 35 / .88); color:#b7c2d8; }
    body[data-theme="easykids"] .bracket-view-select label { color:#b7c2d8; }
    body[data-theme="easykids"] .bracket-view-select select,
    body[data-theme="easykids"] .bracket-view-select select:hover,
    body[data-theme="easykids"] .bracket-view-select select:focus { border-color:rgb(102 215 237 / .30); background-color:#0c1219; color:#f3f7ff; box-shadow:0 0 0 1px rgb(102 215 237 / .08); }
    body[data-theme="easykids"] .score-modal { border-color:rgb(116 147 202 / .18); background:linear-gradient(180deg, rgb(24 31 47 / .98), rgb(12 16 25 / .98)); color:#eef3ff; }
    body[data-theme="easykids"] .score-modal::backdrop { background:rgb(4 7 13 / .78); }
    body[data-theme="easykids"] .score-modal-teams { gap:11px; }
    body[data-theme="easykids"] .score-modal-team { grid-template-columns:1fr; align-items:stretch; gap:10px; padding:12px; border-color:rgb(116 147 202 / .14); background:rgb(20 26 39 / .88); }
    body[data-theme="easykids"] .score-modal-team.leading { border-color:rgb(73 207 155 / .42); background:rgb(22 82 59 / .84); box-shadow:inset 4px 0 0 #49cf9b, 0 0 24px rgb(73 207 155 / .12); }
    body[data-theme="easykids"] .score-modal-team-name { min-width:0; align-items:flex-start; overflow:visible; white-space:normal; line-height:1.35; }
    body[data-theme="easykids"] .score-modal-team-name span { min-width:0; overflow:visible; white-space:normal; word-break:break-word; }
    body[data-theme="easykids"] .score-modal-team .match-side { margin-top:2px; }
    body[data-theme="easykids"] .score-modal-team .score-stepper { width:100%; min-width:0; }
    body[data-theme="easykids"] .score-stepper { border-color:rgb(102 215 237 / .22); background:#080d16; }
    body[data-theme="easykids"] .score-stepper button { background:rgb(31 43 70 / .92); color:#8be9ff; font-weight:900; }
    body[data-theme="easykids"] .score-stepper button:hover { background:#122f5f; color:#dff7ff; }
    body[data-theme="easykids"] .score-stepper input { border-color:rgb(116 147 202 / .14); background:#0b111d; color:#eef3ff; }
    body[data-theme="easykids"] .score-leader-badge { display:none; width:max-content; margin-left:auto; padding:2px 8px; border:1px solid rgb(73 207 155 / .34); border-radius:999px; background:rgb(22 62 49 / .66); color:#dffef2; font-size:11px; font-weight:900; }
    body[data-theme="easykids"] .score-modal-team.leading .score-leader-badge { display:inline-flex; }
    body[data-theme="easykids"] .score-versus { display:grid; place-items:center; width:40px; height:40px; margin:-2px auto; border:1px solid rgb(102 215 237 / .32); border-radius:999px; background:rgb(31 43 70 / .92); color:#8be9ff; font-size:12px; font-weight:900; letter-spacing:.04em; }
    body[data-theme="easykids"] .viewer-event-head h1 { color:#f3f7ff; }
    body[data-theme="easykids"] .bracket-results-summary { margin:0 0 18px; }
    body[data-theme="easykids"] .podium-grid { display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:10px; margin-top:10px; }
    body[data-theme="easykids"] .podium-card { display:flex; align-items:center; gap:10px; min-width:0; padding:12px; border:1px solid rgb(116 147 202 / .16); border-radius:10px; background:rgb(17 23 35 / .88); }
    body[data-theme="easykids"] .podium-card.rank-1 { border-color:rgb(240 190 114 / .44); background:linear-gradient(135deg, rgb(84 58 20 / .82), rgb(17 23 35 / .92)); }
    body[data-theme="easykids"] .podium-rank { display:grid; place-items:center; width:36px; height:36px; flex:0 0 auto; border-radius:999px; background:rgb(31 43 70 / .92); color:#dff7ff; font-weight:900; }
    body[data-theme="easykids"] .podium-card.rank-1 .podium-rank { background:#f0be72; color:#221608; }
    body[data-theme="easykids"] .podium-team { min-width:0; overflow:hidden; color:#f3f7ff; font-weight:850; white-space:nowrap; text-overflow:ellipsis; }
    body[data-theme="easykids"] .podium-source { color:#b7c2d8; font-size:11px; }
    @media(max-width:820px){body[data-theme="easykids"] .podium-grid{grid-template-columns:1fr}}
    @media(max-width:680px){.bracket-viewport{min-height:150px;margin-left:-10px;margin-right:-10px;border-radius:0;border-left:0;border-right:0}.bracket-toolbar{align-items:flex-start;flex-direction:column;padding:8px 10px}.bracket-legend{gap:8px 12px}.bracket-legend span:nth-child(-n+2){display:none}.bracket-match-node,body[data-theme="easykids"] .bracket-match-node{width:220px;min-height:84px;padding:6px}.bracket-round-title{height:36px;font-size:10px}.bracket-section{margin-bottom:18px}.bracket-section-head{padding:0 2px}.viewer-event-head{align-items:flex-start}.viewer-event-head h1{font-size:19px}.viewer-event-head .badge{flex:0 0 auto}.match-side,body[data-theme="easykids"] .match-side{min-width:33px;height:19px;padding-inline:5px;font-size:9px}.bracket-team-name,body[data-theme="easykids"] .bracket-team-name{gap:4px;font-size:12px}.bracket-team,body[data-theme="easykids"] .bracket-team{min-height:26px;padding:3px 4px}.bracket-card-actions,body[data-theme="easykids"] .bracket-card-actions{right:-35px}.bracket-card-actions .bracket-icon-button,body[data-theme="easykids"] .bracket-card-actions .bracket-icon-button{width:28px;min-width:28px;height:28px;min-height:28px}.bracket-destinations{font-size:9px}.score-modal-team,body[data-theme="easykids"] .score-modal-team{grid-template-columns:1fr;gap:8px}.score-modal-team .score-stepper,body[data-theme="easykids"] .score-modal-team .score-stepper{min-width:0}.score-modal-actions{display:grid;grid-template-columns:1fr 1fr}.score-modal-actions .btn{width:100%}}
    @media(max-width:680px){
        .bracket-view-switcher { display:none; }
        .bracket-view-select { display:grid; gap:6px; }
        .bracket-toolbar { margin-bottom:10px; }
        .bracket-hint { font-size:12px; }
        .bracket-admin-actions { width:100%; justify-content:space-between; }
        .bracket-legend { font-size:11px; }
        .viewer-event-head { flex-direction:column; gap:8px; }
        .bracket-match-meta { grid-template-columns:minmax(0,1fr) auto; }
        .bracket-award-badge { max-width:112px; overflow:hidden; text-overflow:ellipsis; }
        .bracket-card-actions,
        body[data-theme="easykids"] .bracket-card-actions { position:static; flex-direction:row; justify-content:flex-end; transform:none; margin-top:6px; }
        .bracket-card-actions::before,
        body[data-theme="easykids"] .bracket-card-actions::before { display:none; }
        .bracket-card-actions .bracket-icon-button,
        body[data-theme="easykids"] .bracket-card-actions .bracket-icon-button { width:36px; min-width:36px; height:36px; min-height:36px; }
    }
</style>
@endpush

@section('content')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $isAdmin = auth()->user()?->isAdmin() ?? false;
@endphp
@if($isPublicView)
<div class="viewer-event-head">
    <div><h1>{{ $tournament->name }}</h1><p>{{ $tournament->competition }} · {{ $tournament->division }}</p></div>
    <span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span>
</div>
@includeWhen($tournament->status === App\Enums\TournamentStatus::LIVE, 'tournaments._live_refresh')
@if($isAdmin)
@include('tournaments._tabs')
@if(in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE], true))
<div class="bracket-toolbar">
    <div class="bracket-hint">{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-admin-actions">
        @if($tournament->status === App\Enums\TournamentStatus::READY)
        <form method="post" action="{{ route('tournaments.start', $tournament) }}">
            @csrf
            <button class="btn" type="submit">{{ __('ui.start_tournament') }}</button>
        </form>
        @endif
        @if($tournament->status === App\Enums\TournamentStatus::LIVE)
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
        @endif
    </div>
</div>
@endif
@endif
<div class="viewer-bracket-help">{{ __('ui.viewer_bracket_help') }}</div>
@else
<div class="page-head">
    <div>
        <div class="actions" style="margin-bottom:5px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div>
        <div class="muted">{{ $tournament->competition }} · {{ $tournament->division }} · {{ __('ui.format_labels.'.$tournament->format->value) }}</div>
    </div>
</div>
@include('tournaments._tabs')

<div class="bracket-toolbar">
    <div class="bracket-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>{{ __('ui.bracket_updates') }}</div>
    <div class="bracket-admin-actions">
        <div class="bracket-legend"><span><i class="legend-line"></i>{{ __('ui.advances_to') }}</span><span><i class="legend-win"></i>{{ __('ui.winner') }}</span><span>{{ __('ui.scroll_rounds') }}</span></div>
        @if($isAdmin && $tournament->status === App\Enums\TournamentStatus::READY)
        <form method="post" action="{{ route('tournaments.start', $tournament) }}">
            @csrf
            <button class="btn" type="submit">{{ __('ui.start_tournament') }}</button>
        </form>
        @endif
        @if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
        <form method="post" action="{{ route('tournaments.complete', $tournament) }}" data-confirm="{{ __('ui.complete_tournament_confirm') }}">
            @csrf
            <button class="btn danger" type="submit">{{ __('ui.complete') }}</button>
        </form>
        @endif
    </div>
</div>
@endif

@if(($bracketViewGroups ?? collect())->isNotEmpty())
@php
    $currentBracketView = $activeBracketView ?? 'all';
@endphp
<div class="bracket-view-select">
    <label for="bracket-view-select">{{ __('ui.bracket_view_mobile_label') }}</label>
    <select id="bracket-view-select" data-bracket-view-select data-native-select aria-label="{{ __('ui.bracket_view_filter') }}">
        <option value="{{ request()->fullUrlWithQuery(['view' => 'all']) }}" @selected($currentBracketView === 'all')>{{ __('ui.all_groups') }}</option>
        @foreach($bracketViewGroups as $viewGroup)
        <option value="{{ request()->fullUrlWithQuery(['view' => 'group:'.$viewGroup->id]) }}" @selected($currentBracketView === 'group:'.$viewGroup->id)>{{ $viewGroup->name }}</option>
        @endforeach
        <option value="{{ request()->fullUrlWithQuery(['view' => 'playoff']) }}" @selected($currentBracketView === 'playoff')>{{ __('ui.playoff_stage') }}</option>
    </select>
</div>
<nav class="bracket-view-switcher" aria-label="{{ __('ui.bracket_view_filter') }}">
    <a class="{{ $currentBracketView === 'all' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'all']) }}">{{ __('ui.all_groups') }}</a>
    @foreach($bracketViewGroups as $viewGroup)
    <a class="{{ $currentBracketView === 'group:'.$viewGroup->id ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'group:'.$viewGroup->id]) }}">{{ $viewGroup->name }}</a>
    @endforeach
    <a class="{{ $currentBracketView === 'playoff' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'playoff']) }}">{{ __('ui.playoff_stage') }}</a>
</nav>
@endif

@if($tournament->status === App\Enums\TournamentStatus::COMPLETED && $podium->isNotEmpty())
<section class="card bracket-results-summary">
    <h2>{{ __('ui.results') }}</h2>
    <div class="podium-grid">
        @foreach($podium as $row)
        <div class="podium-card rank-{{ $row['rank'] }}">
            <span class="podium-rank">#{{ $row['rank'] }}</span>
            <div>
                <div class="podium-team" title="{{ $row['participant']->team_name }}">{{ $row['participant']->team_name }}</div>
                @if($row['source'])
                <div class="podium-source">{{ __('ui.match') }} #{{ $row['source']->match_number }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

@php
    $displayMatchNumbersById = collect();
    $displayNumberGroups = collect();
    $isThirdPlaceMatch = fn ($match): bool => $match->participant_a_source_outcome === App\Enums\MatchOutcome::LOSER
        && $match->participant_b_source_outcome === App\Enums\MatchOutcome::LOSER;

    foreach ($matches as $type => $sectionMatches) {
        $typeKey = (string) $type;
        $displayGroupKey = $typeKey;

        if (str_starts_with($typeKey, 'GROUP:')) {
            $parts = explode(':', $typeKey);
            $displayGroupKey = 'GROUP:'.($parts[1] ?? '');
        } elseif (str_starts_with($typeKey, 'PLAYOFF:')) {
            $displayGroupKey = 'PLAYOFF';
        }

        $displayNumberGroups->put(
            $displayGroupKey,
            $displayNumberGroups->get($displayGroupKey, collect())->concat($sectionMatches)
        );
    }

    foreach ($displayNumberGroups as $sectionMatches) {
        $sectionMatches->sort(function ($a, $b) use ($isThirdPlaceMatch): int {
            return ((int) $a->round_number <=> (int) $b->round_number)
                ?: ((int) $isThirdPlaceMatch($b) <=> (int) $isThirdPlaceMatch($a))
                ?: ((int) $a->match_number <=> (int) $b->match_number);
        })->values()->each(function ($sectionMatch, int $index) use ($displayMatchNumbersById): void {
            $displayMatchNumbersById->put((string) $sectionMatch->id, $index + 1);
        });
    }

    $participantDisplayName = function ($match, string $side) use ($displayMatchNumbersById): string {
        $participant = $side === 'a' ? $match->participantA : $match->participantB;
        if ($participant) {
            return $participant->team_name;
        }

        $sourceId = $side === 'a' ? $match->participant_a_source_match_id : $match->participant_b_source_match_id;
        $sourceOutcome = $side === 'a' ? $match->participant_a_source_outcome : $match->participant_b_source_outcome;
        if ($sourceId !== null && $sourceOutcome !== null) {
            $number = $displayMatchNumbersById->get((string) $sourceId);
            if ($number !== null) {
                return $sourceOutcome === App\Enums\MatchOutcome::WINNER
                    ? __('ui.source_winner_label', ['number' => $number])
                    : __('ui.source_loser_label', ['number' => $number]);
            }
        }

        return $side === 'a' ? $match->participantALabel() : $match->participantBLabel();
    };
@endphp

@forelse($matches as $type => $group)
@php
    $isGrid = in_array($type, ['ROUND_ROBIN', 'RANKING'], true);
    $isGroupSection = str_starts_with((string) $type, 'GROUP:');
    $isPlayoffSection = str_starts_with((string) $type, 'PLAYOFF:');
    $sectionType = $isPlayoffSection ? substr((string) $type, 8) : $type;
    $groupSectionType = $isGroupSection ? substr((string) $type, strrpos((string) $type, ':') + 1) : null;
    $groupName = $group->first()?->stageGroup?->name ?? __('ui.group');
    $sectionLabel = $isGroupSection ? ($groupName.' · '.__('ui.bracket_labels.'.$groupSectionType)) : ($isPlayoffSection ? __('ui.playoff_stage').' · '.__('ui.bracket_labels.'.$sectionType) : __('ui.bracket_labels.'.$type));
    if ($isPlayoffSection && $sectionType === App\Enums\BracketType::WINNERS->value) {
        $sectionLabel = __('ui.playoff_stage');
    }
    $isGrid = $isGrid || ($isGroupSection && $group->every(fn ($match): bool => in_array($match->bracket_type, [App\Enums\BracketType::ROUND_ROBIN, App\Enums\BracketType::RANKING], true)));
    $hasGrandFinal = $group->contains(fn ($match): bool => $match->bracket_type === App\Enums\BracketType::GRAND_FINAL);
    $lastRoundNumber = (int) $group->max('round_number');
@endphp
<section class="bracket-section">
    <div class="bracket-section-head"><h2>{{ $sectionLabel }}</h2><span class="bracket-count">{{ trans_choice('ui.match_count', $group->count(), ['count' => $group->count()]) }}</span></div>
    <div class="bracket-viewport {{ $isGrid ? 'bracket-grid' : '' }}" data-bracket-section data-bracket-type="{{ $type }}" data-has-grand-final="{{ $hasGrandFinal ? 'true' : 'false' }}">
        @if(!$isGrid)
        <div class="bracket-canvas" data-bracket-canvas></div>
        @endif
        @foreach($group as $match)
        @php
            $nameA = $participantDisplayName($match, 'a');
            $nameB = $participantDisplayName($match, 'b');
            $canEnterScore = $isAdmin
                && $tournament->status === App\Enums\TournamentStatus::LIVE
                && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true)
                && !$match->is_bye && $match->participant_a_id && $match->participant_b_id;
            $canEditScore = $isAdmin
                && $tournament->status === App\Enums\TournamentStatus::LIVE
                && $match->status === App\Enums\MatchStatus::FINISHED && !$match->is_bye
                && $match->participant_a_id && $match->participant_b_id;
            $isUnscored = !$match->is_bye && $match->participant_a_id && $match->participant_b_id
                && in_array($match->status, [App\Enums\MatchStatus::READY, App\Enums\MatchStatus::LIVE], true)
                && ($match->score_a === null || $match->score_b === null);
            $displayMatchNumber = $displayMatchNumbersById->get((string) $match->id, $loop->iteration);
            $isAwardMatch = !$isGrid && in_array($match->bracket_type, [App\Enums\BracketType::WINNERS, App\Enums\BracketType::GRAND_FINAL], true);
            $isThirdPlace = $isAwardMatch && $isThirdPlaceMatch($match);
            $isChampionship = $isAwardMatch && !$isThirdPlace && (int) $match->round_number === $lastRoundNumber && $match->winner_next_match_id === null;
            $championshipBadgeLabel = $isGroupSection ? __('ui.group_championship_match_badge', ['group' => $groupName]) : __('ui.championship_match_badge');
            $layoutSortNumber = $isThirdPlace ? $match->match_number + 100000 : $match->match_number;
        @endphp
        <article class="bracket-match-node {{ $match->status === App\Enums\MatchStatus::LIVE ? 'in-progress' : '' }} {{ $match->status === App\Enums\MatchStatus::FINISHED ? 'is-finished' : '' }} {{ $match->status === App\Enums\MatchStatus::READY ? 'is-ready' : '' }} {{ $isUnscored ? 'is-unscored' : '' }}"
            data-match-id="{{ $match->id }}" data-round="{{ $match->round_number }}" data-number="{{ $layoutSortNumber }}"
            data-winner-next="{{ $match->winner_next_match_id }}" data-loser-next="{{ $match->loser_next_match_id }}" data-third-place="{{ $isThirdPlace ? 'true' : 'false' }}">
            <div class="bracket-match-meta">
                <span class="bracket-match-number"><span>{{ __('ui.display_match') }}</span><strong>#{{ $displayMatchNumber }}</strong></span>
                @if($isChampionship)
                <span class="bracket-award-badge champion">{{ $championshipBadgeLabel }}</span>
                @elseif($isThirdPlace)
                <span class="bracket-award-badge third">{{ __('ui.third_place_match_badge') }}</span>
                @else
                <span class="badge {{ $match->is_bye ? 'BYE' : $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span>
                @endif
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_a_id ? 'winner' : '' }} {{ !$match->participant_a_id ? 'waiting' : '' }}">
                <span class="bracket-team-name">
                    <i class="match-side red">{{ __('ui.red_side') }}</i>
                    @if($match->participantA?->seed_number)
                    <i class="bracket-seed">{{ $match->participantA->seed_number }}</i>
                    @endif
                    <span title="{{ $nameA }}">{{ $nameA }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</span>
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_b_id ? 'winner' : '' }} {{ !$match->participant_b_id ? 'waiting' : '' }}">
                <span class="bracket-team-name">
                    <i class="match-side blue">{{ __('ui.blue_side') }}</i>
                    @if($match->participantB?->seed_number)
                    <i class="bracket-seed">{{ $match->participantB->seed_number }}</i>
                    @endif
                    <span title="{{ $nameB }}">{{ $nameB }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</span>
            </div>
            @if($canEnterScore || $canEditScore || $match->status === App\Enums\MatchStatus::LIVE)
            @include('tournaments._bracket-match-actions')
            @endif
        </article>
        @endforeach
    </div>
</section>
@empty
<div class="card empty">{{ __('ui.bracket_empty') }}</div>
@endforelse

@if($isAdmin && $tournament->status === App\Enums\TournamentStatus::LIVE)
<dialog class="score-modal" data-score-modal aria-labelledby="score-modal-title" data-reopen-match="{{ old('score_modal_match') }}" data-old-score-a="{{ old('score_a') }}" data-old-score-b="{{ old('score_b') }}">
    <div class="score-modal-head"><h2 id="score-modal-title" data-score-modal-title>{{ __('ui.enter_score') }}</h2><button class="score-modal-close" type="button" data-score-modal-close aria-label="{{ __('ui.cancel') }}">×</button></div>
    <form class="score-modal-form" method="post" data-score-modal-form>
        @csrf
        <input type="hidden" name="score_modal_match" data-score-modal-match>
        <div class="score-modal-teams">
            <label class="score-modal-team" data-score-card-a><span class="score-modal-team-name"><i class="match-side red">{{ __('ui.red_side') }}</i><span data-score-team-a></span><em class="score-leader-badge">{{ __('ui.leading_score') }}</em></span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input type="number" inputmode="decimal" min="0" step="any" name="score_a" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
            <div class="score-versus" aria-hidden="true">VS</div>
            <label class="score-modal-team" data-score-card-b><span class="score-modal-team-name"><i class="match-side blue">{{ __('ui.blue_side') }}</i><span data-score-team-b></span><em class="score-leader-badge">{{ __('ui.leading_score') }}</em></span><span class="score-stepper"><button type="button" data-score-step="-1" aria-label="{{ __('ui.subtract_point') }}">−</button><input type="number" inputmode="decimal" min="0" step="any" name="score_b" value="0" required><button type="button" data-score-step="1" aria-label="{{ __('ui.add_point') }}">+</button></span></label>
        </div>
        <div class="score-modal-actions"><button class="btn secondary" type="button" data-score-modal-close>{{ __('ui.cancel') }}</button><button class="btn" type="submit" data-score-modal-submit>{{ __('ui.confirm_score') }}</button></div>
    </form>
</dialog>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-bracket-view-select]').forEach((select) => {
    select.addEventListener('change', () => {
        if (select.value) window.location.href = select.value;
    });
});

(() => {
    const dialog = document.querySelector('[data-score-modal]');
    if (!dialog) return;

    const form = dialog.querySelector('[data-score-modal-form]');
    const title = dialog.querySelector('[data-score-modal-title]');
    const teamA = dialog.querySelector('[data-score-team-a]');
    const teamB = dialog.querySelector('[data-score-team-b]');
    const scoreA = form.elements.score_a;
    const scoreB = form.elements.score_b;
    const cardA = dialog.querySelector('[data-score-card-a]');
    const cardB = dialog.querySelector('[data-score-card-b]');
    const matchId = form.elements.score_modal_match;
    const submit = dialog.querySelector('[data-score-modal-submit]');
    const titleTemplate = @json(__('ui.score_match_title', ['number' => '__NUMBER__']));
    const scoreLabelTemplate = @json(__('ui.score_for_team', ['team' => '__TEAM__']));
    const correctionConfirm = @json(__('ui.score_correction_confirm'));
    const enterLabel = @json(__('ui.confirm_score'));
    const editLabel = @json(__('ui.save_corrected_score'));

    const openModal = (trigger, restoreOldInput = false) => {
        const editing = trigger.dataset.editing === 'true';
        form.action = trigger.dataset.action;
        matchId.value = trigger.dataset.matchId;
        title.textContent = titleTemplate.replace('__NUMBER__', trigger.dataset.matchNumber);
        teamA.textContent = trigger.dataset.teamA;
        teamB.textContent = trigger.dataset.teamB;
        scoreA.value = restoreOldInput && dialog.dataset.oldScoreA !== '' ? dialog.dataset.oldScoreA : trigger.dataset.scoreA;
        scoreB.value = restoreOldInput && dialog.dataset.oldScoreB !== '' ? dialog.dataset.oldScoreB : trigger.dataset.scoreB;
        scoreA.setAttribute('aria-label', scoreLabelTemplate.replace('__TEAM__', trigger.dataset.teamA));
        scoreB.setAttribute('aria-label', scoreLabelTemplate.replace('__TEAM__', trigger.dataset.teamB));
        submit.textContent = editing ? editLabel : enterLabel;
        if (editing) form.dataset.confirm = correctionConfirm;
        else delete form.dataset.confirm;
        syncLeader();

        dialog.showModal();
        requestAnimationFrame(() => scoreA.focus());
    };

    const scoreValue = (input) => {
        const value = Number(input.value);
        return Number.isFinite(value) ? value : null;
    };

    const syncLeader = () => {
        const a = scoreValue(scoreA);
        const b = scoreValue(scoreB);
        cardA?.classList.remove('leading');
        cardB?.classList.remove('leading');
        if (a === null || b === null || a === b) return;
        (a > b ? cardA : cardB)?.classList.add('leading');
    };

    scoreA.addEventListener('input', syncLeader);
    scoreB.addEventListener('input', syncLeader);

    document.querySelectorAll('[data-score-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => openModal(trigger));
    });
    dialog.querySelectorAll('[data-score-modal-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    if (dialog.dataset.reopenMatch) {
        const trigger = document.querySelector(`[data-score-modal-trigger][data-match-id="${CSS.escape(dialog.dataset.reopenMatch)}"]`);
        if (trigger) openModal(trigger, true);
    }
})();

(() => {
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const HEADER = 58;
    const GAP_X = 54;
    const GAP_Y = 18;
    const ACTION_GUTTER = 48;
    const ROUND_LABEL = @json(__('ui.round'));
    const FINAL_LABEL = @json(__('ui.final'));
    const SEMIFINAL_LABEL = @json(__('ui.semifinals'));
    const FINALS_LABEL = @json(__('ui.finals'));
    const LOSERS_ROUND_LABEL = @json(__('ui.losers_round'));

    document.querySelectorAll('[data-bracket-section]:not(.bracket-grid)').forEach((viewport) => {
        const canvas = viewport.querySelector('[data-bracket-canvas]');
        const nodes = [...viewport.querySelectorAll('.bracket-match-node')];
        if (!canvas || !nodes.length) return;

        nodes.forEach((node) => canvas.appendChild(node));
        const matches = nodes.map((node) => ({
            node, id: node.dataset.matchId, round: Number(node.dataset.round), number: Number(node.dataset.number),
            winnerNext: node.dataset.winnerNext || null, loserNext: node.dataset.loserNext || null, thirdPlace: node.dataset.thirdPlace === 'true',
        }));
        const ids = new Set(matches.map((match) => match.id));
        const rounds = [...new Set(matches.map((match) => match.round))].sort((a,b) => a-b);
        const roundIndex = new Map(rounds.map((round, index) => [round, index]));
        const layout = () => {
            canvas.querySelectorAll('.bracket-connectors, .bracket-round-title').forEach((element) => element.remove());
            nodes.forEach((node) => { node.style.width = ''; });

            const cardWidth = Math.max(...nodes.map((node) => node.offsetWidth));
            const cardHeight = Math.max(...nodes.map((node) => node.offsetHeight));
            const base = cardHeight + GAP_Y;
            const columnWidth = cardWidth + GAP_X;
            const y = new Map();

            rounds.forEach((round) => {
                const inRound = matches.filter((match) => match.round === round).sort((a,b) => a.number-b.number);
                let leafIndex = 0;
                let previousY = -base;
                inRound.forEach((match) => {
                    const feeders = match.thirdPlace ? [] : matches.filter((source) => source.winnerNext === match.id || source.loserNext === match.id);
                    const feederY = feeders.map((source) => y.get(source.id)).filter((value) => value !== undefined);
                    let proposed = feederY.length ? feederY.reduce((sum,value) => sum+value, 0) / feederY.length : leafIndex++ * base;
                    proposed = Math.max(proposed, previousY + base);
                    y.set(match.id, proposed);
                    previousY = proposed;
                });
            });

            const maxY = Math.max(0, ...y.values());
            const width = Math.max(viewport.clientWidth, rounds.length * columnWidth - GAP_X + ACTION_GUTTER + 28);
            const height = maxY + cardHeight + HEADER + 24;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;

            const roundTitle = (round, index) => {
                const type = viewport.dataset.bracketType;
                const hasGrandFinal = viewport.dataset.hasGrandFinal === 'true';
                const remaining = rounds.length - index;

                if (type === 'LOSERS') return `${LOSERS_ROUND_LABEL} ${index + 1}`;
                if (type === 'GRAND_FINAL') return rounds.length > 1 ? `${FINAL_LABEL} ${index + 1}` : FINAL_LABEL;
                if (type === 'WINNERS') {
                    if (hasGrandFinal && remaining === 1) return FINALS_LABEL;
                    if (remaining === 1 || (hasGrandFinal && remaining === 2)) return SEMIFINAL_LABEL;
                    return `${ROUND_LABEL} ${index + 1}`;
                }

                if (rounds.length === 1) return FINAL_LABEL;
                if (remaining === 1) return FINALS_LABEL;
                if (remaining === 2) return SEMIFINAL_LABEL;
                return `${ROUND_LABEL} ${index + 1}`;
            };

            rounds.forEach((round, index) => {
                const title = document.createElement('div');
                title.className = 'bracket-round-title';
                title.dataset.roundTone = ['pink', 'blue', 'orange', 'cyan', 'green', 'violet'][index % 6];
                title.style.left = `${index * columnWidth + 14}px`;
                title.style.top = '10px';
                title.style.width = `${cardWidth}px`;
                title.textContent = roundTitle(round, index);
                canvas.appendChild(title);
            });

            matches.forEach((match) => {
                match.node.style.left = `${(roundIndex.get(match.round) || 0) * columnWidth + 14}px`;
                match.node.style.top = `${(y.get(match.id) || 0) + HEADER}px`;
                match.node.style.width = `${cardWidth}px`;
            });

            const svg = document.createElementNS(SVG_NS, 'svg');
            svg.classList.add('bracket-connectors');
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

            matches.forEach((source) => {
                [source.winnerNext, source.loserNext].forEach((targetId) => {
                    if (!targetId || !ids.has(targetId)) return;
                    const target = matches.find((candidate) => candidate.id === targetId);
                    if (!target || target.thirdPlace) return;
                    const x1 = (roundIndex.get(source.round) || 0) * columnWidth + 14 + cardWidth;
                    const y1 = (y.get(source.id) || 0) + HEADER + source.node.offsetHeight / 2;
                    const x2 = (roundIndex.get(target.round) || 0) * columnWidth + 14;
                    const y2 = (y.get(target.id) || 0) + HEADER + target.node.offsetHeight / 2;
                    const midX = x1 + (x2 - x1) / 2;
                    const path = document.createElementNS(SVG_NS, 'path');
                    path.setAttribute('class', 'bracket-connector');
                    path.setAttribute('d', `M ${x1} ${y1} H ${midX} V ${y2} H ${x2}`);
                    svg.appendChild(path);
                });
            });
            canvas.prepend(svg);
        };

        layout();
        let observedWidth = Math.round(viewport.getBoundingClientRect().width);
        let resizeFrame = null;
        new ResizeObserver(([entry]) => {
            const nextWidth = Math.round(entry.contentRect.width);
            if (nextWidth === observedWidth) return;
            observedWidth = nextWidth;
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(layout);
        }).observe(viewport);
    });
})();
</script>
@endpush
