@extends('layouts.app')
@section('title', __('ui.title_bracket').' · '.$tournament->name)
@section('container-class', 'container-wide')

@push('styles')
<style>
    main.container-wide { max-width:2800px; padding-inline:20px; }
    .bracket-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .bracket-hint { display:flex; align-items:center; gap:7px; color:var(--muted); font-size:13px; }
    .bracket-hint svg { width:15px; height:15px; }
    .bracket-section { margin:0 0 30px; }
    .bracket-section-head { display:flex; align-items:center; gap:10px; margin:0 0 10px; }
    .bracket-section-head h2 { margin:0; font-size:16px; }
    .bracket-count { color:var(--muted); font-size:12px; }
    .bracket-admin-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .bracket-admin-actions form { margin:0; }
    .bracket-viewport { --section-accent:#d4af37; position:relative; overflow:auto; overscroll-behavior-inline:contain; min-height:190px; border:1px solid var(--line); border-top:2px solid var(--section-accent); border-radius:7px; background:#0c1219; scrollbar-color:#3a4653 transparent; -webkit-overflow-scrolling:touch; }
    .bracket-viewport[data-bracket-type$="LOSERS"],
    .bracket-viewport[data-bracket-type$="GRAND_FINAL"] { --section-accent:#d4af37; }
    .bracket-canvas { position:relative; min-width:100%; }
    .bracket-round-lane { position:absolute; z-index:0; top:48px; bottom:12px; border-inline:1px solid rgb(148 163 184 / .08); border-radius:6px; background:rgb(148 163 184 / .025); pointer-events:none; }
    .bracket-round-lane.is-alternate { background:rgb(148 163 184 / .04); }
    .bracket-connectors { position:absolute; inset:0; z-index:1; overflow:visible; pointer-events:none; }
    .bracket-connector-outline { fill:none; stroke:#080d16; stroke-width:4.5; stroke-linecap:round; stroke-linejoin:round; opacity:.96; vector-effect:non-scaling-stroke; }
    .bracket-connector { fill:none; stroke:#b0b5bd; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; opacity:1; vector-effect:non-scaling-stroke; }
    .bracket-connector-outline.is-loss,
    .bracket-connector.is-loss { stroke-dasharray:6 5; }
    .bracket-connector-port { stroke:#080d16; stroke-width:2; vector-effect:non-scaling-stroke; }
    .bracket-round-title { position:absolute; top:0; z-index:3; height:44px; display:flex; align-items:center; color:#71717a; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.055em; }
    .bracket-match-node { position:absolute; z-index:2; width:272px; min-height:126px; padding:10px; border:1px solid var(--line); border-radius:7px; background:var(--card); box-shadow:none; transition:border-color .14s; }
    .bracket-match-node:hover { z-index:4; border-color:var(--line-strong); box-shadow:none; transform:none; }
    .bracket-match-meta { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:6px; min-height:24px; margin-bottom:6px; color:var(--muted); font-size:11px; }
    .bracket-match-number { display:inline-flex; align-items:baseline; gap:4px; font-weight:700; color:#52525b; }
    .bracket-match-number strong { font-size:16px; font-weight:900; }
    .bracket-match-number span { font-size:10px; }
    .bracket-scheduled-time { display:inline-flex; align-items:center; min-height:18px; padding:0 5px; border:1px solid var(--line); border-radius:3px; background:var(--soft); color:var(--muted); font-size:10px; font-weight:700; font-style:normal; margin-left:2px; }
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
    .bracket-destinations { display:flex; align-items:center; gap:5px; min-height:18px; margin-top:5px; color:#71717a; font-size:10px; }
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
    body[data-theme="dark"] .bracket-round-lane { border-color:rgb(148 163 184 / .08); background:rgb(148 163 184 / .018); }
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
    body[data-theme="easykids"] .bracket-toolbar { padding:8px 10px; border:1px solid #2a2f37; border-radius:6px; background:#171a20; }
    body[data-theme="easykids"] .bracket-hint,
    body[data-theme="easykids"] .bracket-legend,
    body[data-theme="easykids"] .bracket-count { color:#a6abb3; }
    body[data-theme="easykids"] .bracket-section-head h2 { color:#f5f6f7; }
    body[data-theme="easykids"] .bracket-viewport { padding-top:8px; border-color:#2a2f37; border-top-color:#d4af37; border-radius:6px; background:#0f1115; box-shadow:0 10px 26px rgb(0 0 0 / .22); scrollbar-color:#d4af37 transparent; }
    body[data-theme="easykids"] .bracket-canvas { padding-top:8px; }
    body[data-theme="easykids"] .bracket-round-lane { top:0; border-color:#2a2f37; border-radius:6px; background:#171a20; box-shadow:inset 0 1px 0 rgb(255 255 255 / .025); }
    body[data-theme="easykids"] .bracket-round-lane.is-alternate { background:#171a20; }
    body[data-theme="easykids"] .bracket-connector-outline { stroke:#0f1115; }
    body[data-theme="easykids"] .bracket-connector,
    body[data-theme="easykids"] .legend-line { stroke:#b0b5bd; border-color:#b0b5bd; }
    body[data-theme="easykids"] .bracket-round-title,
    body[data-theme="easykids"] .bracket-destinations { color:#a6abb3; }
    body[data-theme="easykids"] .bracket-round-title { justify-content:center; height:42px; padding:0 9px; border:0; border-bottom:1px solid #2a2f37; border-radius:0; background:transparent; color:#f5f6f7; font-size:12px; letter-spacing:0; text-transform:none; box-shadow:none; }
    body[data-theme="easykids"] .bracket-match-node { width:220px; min-height:0; padding:5px; border-color:#3a4049; border-radius:6px; background:#1f232a; box-shadow:inset 3px 0 0 var(--round-accent,#d4af37); }
    body[data-theme="easykids"] .bracket-match-node.is-finished { border-color:#3a4049; background:#1f232a; box-shadow:inset 3px 0 0 var(--round-accent,#d4af37); }
    body[data-theme="easykids"] .bracket-match-node.is-finished::after { content:""; position:absolute; inset:0 auto 0 0; width:4px; border-radius:7px 0 0 7px; background:var(--round-accent,#66d7ed); }
    body[data-theme="easykids"] .bracket-match-node.is-finished .badge.FINISHED { font-weight:850; }
    body[data-theme="easykids"] .bracket-match-node.is-unscored,
    body[data-theme="easykids"] .bracket-match-node.in-progress,
    body[data-theme="easykids"] .bracket-match-node:hover { border-color:#d4af37; box-shadow:inset 3px 0 0 #d4af37, 0 5px 12px rgb(0 0 0 / .24); }
    body[data-theme="easykids"] .bracket-match-node.is-ready .badge,
    body[data-theme="easykids"] .bracket-match-node.in-progress .badge { border-color:#d4af37; background:#3f3518; color:#fff8df; }
    body[data-theme="easykids"] .bracket-match-node.is-unscored .bracket-score { color:#d4af37; }
    body[data-theme="easykids"] .bracket-match-meta { min-height:17px; margin-bottom:2px; font-size:9px; }
    body[data-theme="easykids"] .bracket-scheduled-time { display:inline-flex; align-items:center; min-height:17px; padding:0 5px; border:1px solid rgb(212 175 55 / .44); border-radius:3px; background:#3f3518; color:#fff8df; font-size:9px; font-weight:900; }
    body[data-theme="easykids"] .bracket-match-number { color:#f5f6f7; }
    body[data-theme="easykids"] .bracket-award-badge.champion,
    body[data-theme="easykids"] .bracket-award-badge.third { border-color:#d4af37; background:#3f3518; color:#fff8df; box-shadow:none; }
    body[data-theme="easykids"] .bracket-team { min-height:24px; padding:2px 5px; gap:5px; border-color:#353b44; background:#2a2f37; color:#f5f6f7; }
    body[data-theme="easykids"] .bracket-team.winner,
    body[data-theme="easykids"] .legend-win { border-color:rgb(212 175 55 / .68); background:rgb(83 68 25 / .72); color:#fff8df; box-shadow:inset 3px 0 0 #d4af37; }
    body[data-theme="easykids"] .bracket-team.winner.advancing { border-color:#d4af37; box-shadow:inset 4px 0 0 #d4af37, 0 0 0 1px rgb(212 175 55 / .14); }
    body[data-theme="easykids"] .bracket-team.winner .bracket-team-name { font-weight:800; }
    body[data-theme="easykids"] .bracket-team.winner .bracket-score { align-self:stretch; display:grid; place-items:center; margin:-2px -5px -2px 0; background:#d4af37; color:#171a20; font-size:14px; font-weight:950; }
    body[data-theme="easykids"] .bracket-team:not(.waiting) { border-color:rgb(212 175 55 / .26); background:#2e3130; }
    body[data-theme="easykids"] .bracket-team:not(.waiting) .bracket-team-name::before { content:""; width:6px; height:6px; flex:0 0 auto; border-radius:50%; background:#d4af37; box-shadow:0 0 0 3px rgb(212 175 55 / .12); }
    body[data-theme="easykids"] .bracket-team.waiting { border-style:dashed; border-color:#414750; background:#252a31; color:#a6abb3; }
    body[data-theme="easykids"] .bracket-team.waiting .bracket-team-name::before { content:"?"; display:grid; width:15px; height:15px; flex:0 0 auto; place-items:center; border:1px solid #59616c; border-radius:50%; color:#a6abb3; font-size:10px; font-weight:900; }
    body[data-theme="easykids"] .bracket-team-name { gap:5px; font-size:12px; }
    body[data-theme="easykids"] .bracket-team-name span { min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    body[data-theme="easykids"] .bracket-seed { display:none; }
    body[data-theme="easykids"] .bracket-seed { min-width:18px; height:18px; padding:0 4px; background:#171a20; color:#a6abb3; font-size:10px; }
    body[data-theme="easykids"] .bracket-score { min-width:18px; color:#d4af37; font-size:12px; }
    body[data-theme="easykids"] .match-side { min-width:38px; height:20px; padding:0 7px; border-color:transparent; color:#fff; font-size:10px; font-weight:900; box-shadow:inset 0 -1px 0 rgb(0 0 0 / .12); }
    body[data-theme="easykids"] .match-side.red { background:#5b1d2f; color:#fff1f5; border-color:rgb(255 117 145 / .46); }
    body[data-theme="easykids"] .match-side.blue { background:#122f5f; color:#dff7ff; border-color:rgb(102 215 237 / .42); }
    body[data-theme="easykids"] .bracket-destinations { display:flex; min-height:14px; margin-top:3px; color:#a6abb3; font-size:9px; }
    body[data-theme="easykids"] .bracket-destination { height:14px; padding:0; border:0; border-radius:0; background:transparent; color:inherit; }
    body[data-theme="easykids"] .bracket-destination.win { background:transparent; color:#d4af37; }
    body[data-theme="easykids"] .bracket-destination.loss { background:transparent; color:#a6abb3; }
    body[data-theme="easykids"] .bracket-card-actions { position:absolute; right:-39px; top:50%; z-index:5; flex-direction:column; transform:translateY(-50%); margin-top:0; }
    body[data-theme="easykids"] .bracket-card-actions::before { content:""; position:absolute; top:50%; right:100%; width:7px; border-top:2px solid #b0b5bd; transform:translateY(-50%); }
    body[data-theme="easykids"] .bracket-card-actions form { margin:0; }
    body[data-theme="easykids"] .bracket-card-actions .bracket-icon-button { width:32px; min-width:32px; height:32px; min-height:32px; border-radius:7px; box-shadow:0 4px 10px rgb(31 143 207 / .18); }
    body[data-theme="easykids"] .record-result-button { border:1px solid #d4af37; background:#d4af37; color:#171a20; box-shadow:0 5px 12px rgb(0 0 0 / .22); }
    body[data-theme="easykids"] .edit-result-button { border:1px solid #3a4049; background:#2a2f37; color:#f5f6f7; box-shadow:none; }
    body[data-theme="easykids"] .edit-result-button:hover { background:#353b44; color:#f5f6f7; }
    body[data-theme="easykids"] .progress-button { border-color:#d4af37; background:#3f3518; color:#fff8df; box-shadow:none; }
    body[data-theme="easykids"] .bracket-icon-button svg { width:17px; height:17px; }
    body[data-theme="easykids"] .bracket-match-node.in-progress .badge.LIVE { font-weight:900; }
    body[data-theme="easykids"] .bracket-current { display:none; }
    body[data-theme="easykids"] .bracket-match-node.in-progress::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; border-radius:7px 0 0 7px; background:#d4af37; }
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
    body[data-theme="easykids"] .podium-card { display:flex; align-items:center; gap:12px; min-width:0; padding:12px; border:1px solid #3a4049; border-radius:8px; background:#1f232a; }
    body[data-theme="easykids"] .podium-card.rank-1 { border-color:#d4af37; background:linear-gradient(135deg, rgb(212 175 55 / .18), #1f232a 64%); }
    body[data-theme="easykids"] .podium-card.rank-2 { border-color:#a6abb3; }
    body[data-theme="easykids"] .podium-card.rank-3 { border-color:#b86b3f; }
    body[data-theme="easykids"] .podium-medal { width:64px; height:64px; flex:0 0 auto; object-fit:contain; }
    body[data-theme="easykids"] .podium-rank { display:grid; place-items:center; width:48px; height:48px; flex:0 0 auto; border-radius:8px; background:#2a2f37; color:#f5f6f7; font-weight:900; }
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
    @media(max-width:380px){.score-modal-actions{grid-template-columns:1fr}.bracket-destinations{align-items:flex-start;flex-direction:column;gap:2px}.bracket-destinations span{white-space:normal}}
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
@php
    $podiumMedalPaths = [
        1 => 'assets/images/medals/1st.png',
        2 => 'assets/images/medals/2nd.png',
        3 => 'assets/images/medals/3nd.png',
    ];
@endphp
<section class="card bracket-results-summary">
    <h2>{{ __('ui.results') }}</h2>
    <div class="podium-grid">
        @foreach($podium as $row)
        <div class="podium-card rank-{{ $row['rank'] }}">
            @php
                $medalPath = $podiumMedalPaths[$row['rank']] ?? null;
            @endphp
            @if($medalPath && is_file(public_path($medalPath)))
            <img class="podium-medal" src="{{ asset($medalPath) }}" alt="{{ __('ui.rank') }} {{ $row['rank'] }}">
            @else
            <span class="podium-rank">#{{ $row['rank'] }}</span>
            @endif
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
    $displayMatchNumbersById = $matches
        ->flatten(1)
        ->unique('id')
        ->mapWithKeys(fn ($match): array => [(string) $match->id => (int) $match->match_number]);
    $isThirdPlaceMatch = fn ($match): bool => $match->participant_a_source_outcome === App\Enums\MatchOutcome::LOSER
        && $match->participant_b_source_outcome === App\Enums\MatchOutcome::LOSER;

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
            $winnerDestinationNumber = $displayMatchNumbersById->get((string) $match->winner_next_match_id, $match->winnerNextMatch?->match_number);
            $loserDestinationNumber = $displayMatchNumbersById->get((string) $match->loser_next_match_id, $match->loserNextMatch?->match_number);
            $isAwardMatch = !$isGrid && in_array($match->bracket_type, [App\Enums\BracketType::WINNERS, App\Enums\BracketType::GRAND_FINAL], true);
            $isThirdPlace = $isAwardMatch && $isThirdPlaceMatch($match);
            $isChampionship = $isAwardMatch && !$isThirdPlace && (int) $match->round_number === $lastRoundNumber && $match->winner_next_match_id === null;
            $championshipBadgeLabel = $isGroupSection ? __('ui.group_championship_match_badge', ['group' => $groupName]) : __('ui.championship_match_badge');
            $layoutSortNumber = $isThirdPlace ? $match->match_number + 100000 : $match->match_number;
        @endphp
        <article class="bracket-match-node {{ $match->status === App\Enums\MatchStatus::LIVE ? 'in-progress' : '' }} {{ $match->status === App\Enums\MatchStatus::FINISHED ? 'is-finished' : '' }} {{ $match->status === App\Enums\MatchStatus::READY ? 'is-ready' : '' }} {{ $isUnscored ? 'is-unscored' : '' }}"
            data-match-id="{{ $match->id }}" data-match-number="{{ $displayMatchNumber }}" data-round="{{ $match->round_number }}" data-number="{{ $layoutSortNumber }}"
            data-winner-next="{{ $match->winner_next_match_id }}" data-loser-next="{{ $match->loser_next_match_id }}" data-third-place="{{ $isThirdPlace ? 'true' : 'false' }}">
            <div class="bracket-match-meta">
                <span class="bracket-match-number"><span>{{ __('ui.display_match') }}</span><strong>#{{ $displayMatchNumber }}</strong>@if(isset($estimatedStartTimes[(string) $match->id]))<i class="bracket-scheduled-time">{{ $estimatedStartTimes[(string) $match->id] }} น.</i>@endif</span>
                @if($isChampionship)
                <span class="bracket-award-badge champion">{{ $championshipBadgeLabel }}</span>
                @elseif($isThirdPlace)
                <span class="bracket-award-badge third">{{ __('ui.third_place_match_badge') }}</span>
                @else
                <span class="badge {{ $match->is_bye ? 'BYE' : $match->status->value }}">{{ $match->is_bye ? __('ui.bye') : __('ui.match_status_labels.'.$match->status->value) }}</span>
                @endif
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_a_id ? 'winner' : '' }} {{ $match->winner_id && $match->winner_id === $match->participant_a_id && $match->winner_next_match_id ? 'advancing' : '' }} {{ !$match->participant_a_id ? 'waiting' : '' }}" data-bracket-slot-state="{{ $match->participant_a_id ? 'confirmed' : 'waiting' }}">
                <span class="bracket-team-name">
                    <i class="match-side red">{{ __('ui.red_side') }}</i>
                    @if($match->participantA?->seed_number)
                    <i class="bracket-seed">{{ $match->participantA->seed_number }}</i>
                    @endif
                    <span title="{{ $nameA }}">{{ $nameA }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_a !== null ? (float)$match->score_a : '—' }}</span>
            </div>
            <div class="bracket-team {{ $match->winner_id && $match->winner_id === $match->participant_b_id ? 'winner' : '' }} {{ $match->winner_id && $match->winner_id === $match->participant_b_id && $match->winner_next_match_id ? 'advancing' : '' }} {{ !$match->participant_b_id ? 'waiting' : '' }}" data-bracket-slot-state="{{ $match->participant_b_id ? 'confirmed' : 'waiting' }}">
                <span class="bracket-team-name">
                    <i class="match-side blue">{{ __('ui.blue_side') }}</i>
                    @if($match->participantB?->seed_number)
                    <i class="bracket-seed">{{ $match->participantB->seed_number }}</i>
                    @endif
                    <span title="{{ $nameB }}">{{ $nameB }}</span>
                </span>
                <span class="bracket-score">{{ $match->score_b !== null ? (float)$match->score_b : '—' }}</span>
            </div>
            @if($winnerDestinationNumber || $loserDestinationNumber)
            <div class="bracket-destinations" aria-label="{{ __('ui.match_destinations') }}">
                @if($winnerDestinationNumber)<span class="bracket-destination win"><strong>{{ __('ui.winner_short') }}</strong> → #{{ $winnerDestinationNumber }}</span>@endif
                @if($loserDestinationNumber)<span class="bracket-destination loss"><strong>{{ __('ui.loser_short') }}</strong> → #{{ $loserDestinationNumber }}</span>@endif
            </div>
            @endif
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
    const HEADER = 54;
    const GAP_X = 56;
    const GAP_Y = 12;
    const ACTION_GUTTER = 44;
    const ROUND_COLORS = ['#d4af37'];
    const ROUND_LABEL = @json(__('ui.round'));
    const FINAL_LABEL = @json(__('ui.final'));
    const SEMIFINAL_LABEL = @json(__('ui.semifinals'));
    const QUARTERFINAL_LABEL = @json(__('ui.quarterfinals'));
    const FINALS_LABEL = @json(__('ui.finals'));
    const LOSERS_ROUND_LABEL = @json(__('ui.losers_round'));

    let nextRoundColorIndex = 0;
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
        const sectionColorOffset = nextRoundColorIndex;
        nextRoundColorIndex += rounds.length;
        const layout = () => {
            canvas.querySelectorAll('.bracket-connectors, .bracket-round-title, .bracket-round-lane').forEach((element) => element.remove());
            nodes.forEach((node) => { node.style.width = ''; });

            const cardWidth = Math.max(...nodes.map((node) => node.offsetWidth));
            const cardHeight = Math.max(...nodes.map((node) => node.offsetHeight));
            const base = cardHeight + GAP_Y;
            const columnWidth = cardWidth + GAP_X;
            const matchesByRound = rounds.map((round) => matches.filter((match) => match.round === round).sort((a,b) => a.number-b.number));
            const anchorIndex = matchesByRound.reduce((best, inRound, index) => inRound.length > matchesByRound[best].length ? index : best, 0);
            const anchorCount = matchesByRound[anchorIndex].length;
            const y = new Map();
            const fallbackY = (index, count) => ((index + .5) * anchorCount / count - .5) * base;
            const placeRound = (inRound, ideals) => {
                let previous = -base;
                inRound.forEach((match, index) => {
                    const position = Math.max(ideals[index], previous + base);
                    y.set(match.id, position);
                    previous = position;
                });
            };

            placeRound(matchesByRound[anchorIndex], matchesByRound[anchorIndex].map((_, index) => index * base));

            for (let index = anchorIndex + 1; index < matchesByRound.length; index++) {
                const inRound = matchesByRound[index];
                const ideals = inRound.map((match, matchIndex) => {
                    const feeders = matches.filter((source) => source.winnerNext === match.id || source.loserNext === match.id);
                    const positions = feeders.map((source) => y.get(source.id)).filter((value) => value !== undefined);
                    return positions.length ? positions.reduce((sum, value) => sum + value, 0) / positions.length : fallbackY(matchIndex, inRound.length);
                });
                placeRound(inRound, ideals);
            }

            for (let index = anchorIndex - 1; index >= 0; index--) {
                const inRound = matchesByRound[index];
                const targets = new Map();
                inRound.forEach((match) => {
                    const targetId = [match.winnerNext, match.loserNext].find((id) => id && ids.has(id));
                    if (!targetId) return;
                    if (!targets.has(targetId)) targets.set(targetId, []);
                    targets.get(targetId).push(match.id);
                });
                const ideals = inRound.map((match, matchIndex) => {
                    const targetId = [match.winnerNext, match.loserNext].find((id) => id && y.has(id));
                    if (!targetId) return fallbackY(matchIndex, inRound.length);
                    const siblings = targets.get(targetId) || [match.id];
                    const siblingIndex = siblings.indexOf(match.id);
                    return y.get(targetId) + (siblingIndex - (siblings.length - 1) / 2) * base;
                });
                placeRound(inRound, ideals);
            }

            const layoutCenter = (anchorCount - 1) * base / 2;
            matchesByRound.forEach((inRound) => {
                const positions = inRound.map((match) => y.get(match.id));
                const roundCenter = (Math.min(...positions) + Math.max(...positions)) / 2;
                const shift = layoutCenter - roundCenter;
                inRound.forEach((match) => y.set(match.id, y.get(match.id) + shift));
            });

            const minY = Math.min(0, ...y.values());
            if (minY < 0) y.forEach((value, id) => y.set(id, value - minY));

            const maxY = Math.max(0, ...y.values());
            const width = Math.max(viewport.clientWidth, rounds.length * columnWidth - GAP_X + ACTION_GUTTER + 28);
            const height = maxY + cardHeight + HEADER + 24;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;

            const roundTitle = (round, index) => {
                const type = viewport.dataset.bracketType;
                const sectionType = type.split(':').pop();
                const hasGrandFinal = viewport.dataset.hasGrandFinal === 'true';
                const remaining = rounds.length - index;

                if (sectionType === 'LOSERS') return `${LOSERS_ROUND_LABEL} ${index + 1}`;
                if (sectionType === 'GRAND_FINAL') return rounds.length > 1 ? `${FINAL_LABEL} ${index + 1}` : FINAL_LABEL;
                if (sectionType === 'WINNERS') {
                    if (remaining === 1) return FINALS_LABEL;
                    if (remaining === 2) return SEMIFINAL_LABEL;
                    if (remaining === 3) return QUARTERFINAL_LABEL;
                    return `${ROUND_LABEL} ${index + 1}`;
                }

                if (rounds.length === 1) return FINAL_LABEL;
                if (remaining === 1) return FINALS_LABEL;
                if (remaining === 2) return SEMIFINAL_LABEL;
                if (remaining === 3) return QUARTERFINAL_LABEL;
                return `${ROUND_LABEL} ${index + 1}`;
            };

            rounds.forEach((round, index) => {
                const lane = document.createElement('div');
                lane.className = `bracket-round-lane${index % 2 ? ' is-alternate' : ''}`;
                lane.style.left = `${index * columnWidth + 7}px`;
                lane.style.width = `${cardWidth + 14}px`;
                lane.style.setProperty('--round-accent', ROUND_COLORS[(sectionColorOffset + index) % ROUND_COLORS.length]);
                canvas.appendChild(lane);

                const title = document.createElement('div');
                title.className = 'bracket-round-title';
                title.style.left = `${index * columnWidth + 14}px`;
                title.style.top = '10px';
                title.style.width = `${cardWidth}px`;
                title.style.setProperty('--round-accent', ROUND_COLORS[(sectionColorOffset + index) % ROUND_COLORS.length]);
                title.textContent = roundTitle(round, index);
                canvas.appendChild(title);
            });

            matches.forEach((match) => {
                const index = roundIndex.get(match.round) || 0;
                const accent = ROUND_COLORS[(sectionColorOffset + index) % ROUND_COLORS.length];
                match.node.style.left = `${index * columnWidth + 14}px`;
                match.node.style.top = `${(y.get(match.id) || 0) + HEADER}px`;
                match.node.style.width = `${cardWidth}px`;
                match.node.style.setProperty('--round-accent', accent);
                match.node.style.setProperty('--round-accent-border', `${accent}8c`);
                match.node.style.setProperty('--round-accent-strong', `${accent}d1`);
            });

            const svg = document.createElementNS(SVG_NS, 'svg');
            svg.classList.add('bracket-connectors');
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

            const edges = matches.flatMap((source) => [
                {source, targetId:source.winnerNext, outcome:'winner'},
                {source, targetId:source.loserNext, outcome:'loser'},
            ])
                .filter((edge) => edge.targetId && ids.has(edge.targetId))
                .map((edge) => ({...edge, target:matches.find((candidate) => candidate.id === edge.targetId)}))
                .filter((edge) => edge.target && !edge.target.thirdPlace);

            edges.forEach((edge) => {
                const incoming = edges.filter((candidate) => candidate.targetId === edge.targetId);
                const x1 = (roundIndex.get(edge.source.round) || 0) * columnWidth + 14 + cardWidth;
                const y1 = (y.get(edge.source.id) || 0) + HEADER + edge.source.node.offsetHeight / 2;
                const x2 = (roundIndex.get(edge.target.round) || 0) * columnWidth + 14;
                const y2 = (y.get(edge.target.id) || 0) + HEADER + edge.target.node.offsetHeight / 2;
                const furthestSourceX = Math.max(...incoming.map((candidate) => (roundIndex.get(candidate.source.round) || 0) * columnWidth + 14 + cardWidth));
                const trackX = furthestSourceX + (x2 - furthestSourceX) * (incoming.length === 1 ? .66 : .5);
                const path = document.createElementNS(SVG_NS, 'path');
                const outline = document.createElementNS(SVG_NS, 'path');
                outline.setAttribute('class', `bracket-connector-outline is-${edge.outcome}`);
                outline.setAttribute('d', `M ${x1} ${y1} H ${trackX} V ${y2} H ${x2}`);
                svg.appendChild(outline);
                path.setAttribute('class', `bracket-connector is-${edge.outcome}`);
                path.setAttribute('d', `M ${x1} ${y1} H ${trackX} V ${y2} H ${x2}`);
                path.style.stroke = '#b0b5bd';
                svg.appendChild(path);

                const port = document.createElementNS(SVG_NS, 'circle');
                port.setAttribute('class', 'bracket-connector-port');
                port.setAttribute('cx', x2);
                port.setAttribute('cy', y2);
                port.setAttribute('r', 3.5);
                port.style.fill = path.style.stroke;
                svg.appendChild(port);
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
