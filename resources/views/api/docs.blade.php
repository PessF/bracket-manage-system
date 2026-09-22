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
  -d '{"name":"EasyKids 2026","competition":"Robot Challenge","division":"Junior","structure":"STANDARD","format":"DOUBLE_ELIMINATION","seeding_method":"REGISTRATION_ORDER","grand_final_matches":2}'</code></pre><h3>{{ __('api.advanced_example') }}</h3><pre><code>curl -X POST "{{ url('/api/tournaments') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Advanced Cup","competition":"Robot Challenge","division":"Open","structure":"ADVANCED","format":"SINGLE_ELIMINATION","seeding_method":"REGISTRATION_ORDER","advanced_group_count":4,"advanced_group_limits":[8,8,8,8],"advanced_group_format":"ROUND_ROBIN","advanced_qualifiers_per_group":2,"advanced_playoff_format":"SINGLE_ELIMINATION","advanced_third_place":true}'</code></pre><h3>{{ __('api.group_assignment_example') }}</h3><pre><code>curl -X PUT "{{ url('/api/tournaments/{id}/group-assignments') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"assignments":{"PARTICIPANT_UUID":"GROUP_UUID"}}'</code></pre><h3>{{ __('api.share_link_example') }}</h3><pre><code>curl -X PATCH "{{ url('/api/tournaments/{id}/share-link') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"share_slug":"easykids-final-26"}'</code></pre><h3>{{ __('api.status_example') }}</h3><pre><code>curl -X PATCH "{{ url('/api/tournaments/{id}/status') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"LIVE"}'</code></pre><h3>{{ __('api.score_example') }}</h3><pre><code>curl -X PUT "{{ url('/api/tournaments/{id}/matches/{match}/result') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"score_a":3,"score_b":1}'</code></pre><p>{{ __('api.score_correction_help') }}</p><h3>{{ __('api.ranking_example') }}</h3><pre><code>curl -X PUT "{{ url('/api/tournaments/{id}/participants/{participant}/attempts/1') }}" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"manual_score":40,"auto_score":45,"attempt_time":72.5,"is_valid":true}'</code></pre></section>

<section class="card" id="codes"><h2>{{ __('api.status_codes') }}</h2><div class="table-wrap"><table><tbody>@foreach(__('api.status_code_rows') as $row)<tr><td><code>{{ $row[0] }}</code></td><td>{{ $row[1] }}</td></tr>@endforeach</tbody></table></div></section>
</div>
</div>
@endsection
