@extends('layouts.app')
@section('title', 'API documentation · EasyKids')
@section('content')
<div class="page-head"><div><h1>EasyKids API v1</h1><div class="muted">JSON endpoints for tournament viewing and administration.</div></div></div>
<section class="card"><h2>Authentication</h2><p>All GET endpoints are public. POST, PATCH, PUT, and DELETE endpoints require an administrator bearer token:</p><pre><code>Authorization: Bearer YOUR_TOKEN
Accept: application/json</code></pre></section>
<section class="card"><h2>Public endpoints</h2><div class="api-list"><code>GET /api/health</code><code>GET /api/tournaments?status=LIVE&amp;format=ROUND_ROBIN&amp;search=name&amp;per_page=20</code><code>GET /api/tournaments/{id}</code><code>GET /api/tournaments/{id}/participants</code><code>GET /api/tournaments/{id}/matches</code><code>GET /api/tournaments/{id}/standings</code></div></section>
<section class="card"><h2>Administrator endpoints</h2><div class="api-list"><code>POST /api/tournaments</code><code>PATCH /api/tournaments/{id}</code><code>DELETE /api/tournaments/{id}</code><code>POST /api/tournaments/{id}/participants</code><code>POST /api/tournaments/{id}/participants/import (multipart: csv_file)</code><code>PATCH /api/tournaments/{id}/participants/{participant}</code><code>DELETE /api/tournaments/{id}/participants/{participant}</code><code>POST /api/tournaments/{id}/start</code><code>POST /api/tournaments/{id}/complete</code><code>POST /api/tournaments/{id}/archive</code><code>POST /api/tournaments/{id}/matches/{match}/result</code><code>POST /api/tournaments/{id}/participants/{participant}/attempts</code></div></section>
<section class="card"><h2>Examples</h2><pre><code>curl {{ url('/api/tournaments') }}

curl -X POST {{ url('/api/tournaments/{id}/matches/{match}/result') }} \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"score_a":3,"score_b":1}'</code></pre></section>
@endsection
@push('styles')<style>pre{max-width:100%;overflow:auto;padding:14px;border:1px solid var(--line);border-radius:8px;background:#18181b;color:#f4f4f5}.api-list{display:flex;flex-direction:column;gap:9px}.api-list code{overflow:auto;padding:9px;border-radius:6px;background:var(--soft);white-space:nowrap}</style>@endpush
