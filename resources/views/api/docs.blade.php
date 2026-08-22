@extends('layouts.app')
@section('title', __('api.title').' · EasyKids')
@section('container-class', 'container-wide')
@section('content')
<div class="page-head"><div><h1>{{ __('api.title') }}</h1><div class="muted">{{ __('api.subtitle') }}</div></div><a class="btn secondary" href="{{ url('/api') }}" target="_blank" rel="noopener">GET /api</a></div>

<div class="api-layout">
<aside class="card api-toc"><strong>{{ __('api.quick_start') }}</strong><a href="#auth">{{ __('api.authentication') }}</a><a href="#responses">{{ __('api.response_format') }}</a><a href="#read">{{ __('api.public_resources') }}</a><a href="#write">{{ __('api.admin_resources') }}</a><a href="#examples">{{ __('api.request_examples') }}</a><a href="#codes">{{ __('api.status_codes') }}</a></aside>
<div class="api-content">
<section class="card"><p>{{ __('api.intro') }}</p><div class="api-base"><span>{{ __('api.base_url') }}</span><code>{{ url('/api') }}</code></div><h2>{{ __('api.quick_start') }}</h2><ol>@foreach(__('api.quick_start_steps') as $step)<li>{{ $step }}</li>@endforeach</ol></section>

<section class="card" id="auth"><h2>{{ __('api.authentication') }}</h2><p>{{ __('api.authentication_help') }}</p><pre><code>Authorization: Bearer YOUR_ADMIN_TOKEN
Accept: application/json
Content-Type: application/json
Accept-Language: th-TH</code></pre><div class="api-note">{{ __('api.security_note') }}</div></section>

<section class="card" id="responses"><h2>{{ __('api.response_format') }}</h2><p>{{ __('api.success_response') }}</p><pre><code>{
  "success": true,
  "data": { "id": "...", "status": "LIVE" }
}</code></pre><p>{{ __('api.error_response') }}</p><pre><code>{
  "success": false,
  "error": {
    "message": "{{ __('ui.api_validation_failed') }}",
    "fields": { "name": ["{{ __('validation.required', ['attribute' => __('ui.name')]) }}"] }
  }
}</code></pre></section>

<section class="card" id="read"><div class="api-section-head"><div><h2>{{ __('api.public_resources') }}</h2><span class="muted">{{ __('api.public_scope') }}</span></div><span class="badge LIVE">GET</span></div>@include('api._endpoint-table', ['rows' => __('api.read_endpoints'), 'access' => __('api.public_or_admin')])<h3>{{ __('api.pagination_filters') }}</h3><p>{{ __('api.pagination_help') }}</p></section>

<section class="card" id="write"><div class="api-section-head"><h2>{{ __('api.admin_resources') }}</h2><span class="badge">Bearer Token</span></div>@include('api._endpoint-table', ['rows' => __('api.write_endpoints'), 'access' => __('api.admin_only')])</section>

<section class="card"><h2>{{ __('api.compatibility_actions') }}</h2><p>{{ __('api.compatibility_help') }}</p>@include('api._endpoint-table', ['rows' => __('api.action_endpoints'), 'access' => __('api.admin_only')])</section>

<section class="card" id="examples"><h2>{{ __('api.request_examples') }}</h2><h3>{{ __('api.list_example') }}</h3><pre><code>curl "{{ url('/api/tournaments?format=ROUND_ROBIN&search=EasyKids&per_page=20&lang=th') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"</code></pre><h3>{{ __('api.create_example') }}</h3><pre><code>curl -X POST "{{ url('/api/tournaments') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept-Language: th-TH" \
  -d '{"name":"EasyKids 2026","competition":"Robot Challenge","division":"Junior","format":"DOUBLE_ELIMINATION","seeding_method":"REGISTRATION_ORDER","grand_final_matches":2}'</code></pre><h3>{{ __('api.status_example') }}</h3><pre><code>curl -X PATCH "{{ url('/api/tournaments/{id}/status') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"LIVE"}'</code></pre><h3>{{ __('api.score_example') }}</h3><pre><code>curl -X PUT "{{ url('/api/tournaments/{id}/matches/{match}/result') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"score_a":3,"score_b":1}'</code></pre></section>

<section class="card" id="codes"><h2>{{ __('api.status_codes') }}</h2><div class="table-wrap"><table><tbody>@foreach(__('api.status_code_rows') as $row)<tr><td><code>{{ $row[0] }}</code></td><td>{{ $row[1] }}</td></tr>@endforeach</tbody></table></div></section>
</div>
</div>
@endsection

@push('styles')
<style>
.api-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px;align-items:start}.api-toc{position:sticky;top:78px;display:flex;flex-direction:column;gap:4px}.api-toc strong{margin-bottom:7px}.api-toc a{min-height:42px;padding:9px 10px;border-radius:6px;color:var(--muted);font-size:14px}.api-toc a:hover{background:var(--soft);color:var(--ink);opacity:1}.api-content{min-width:0}.api-content h3{margin:22px 0 7px;font-size:16px}.api-content p{color:#52525b}.api-base{display:flex;align-items:center;gap:12px;margin:14px 0 22px;padding:11px;border-radius:8px;background:var(--soft)}.api-base span{font-weight:700}.api-base code{overflow:auto}.api-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.api-section-head h2{margin-bottom:2px}.api-note{padding:10px 12px;border-left:3px solid #f59e0b;background:#fffbeb;color:#92400e}.api-method{font-weight:800;white-space:nowrap}.api-endpoint{display:block;max-width:560px;overflow:auto;white-space:nowrap}pre{max-width:100%;overflow:auto;padding:14px;border:1px solid #27272a;border-radius:8px;background:#18181b;color:#f4f4f5;font-size:13px}ol{padding-left:22px}ol li+li{margin-top:6px}@media(max-width:860px){.api-layout{grid-template-columns:1fr}.api-toc{position:static;display:none}}@media(max-width:680px){.api-section-head{align-items:flex-start}.api-content .card{margin-right:-2px;margin-left:-2px}.api-endpoint{max-width:68vw}}
</style>
@endpush
