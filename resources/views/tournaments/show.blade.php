@extends('layouts.app')
@section('title', $tournament->name.' · EasyKids')
@push('styles')
<style>
    .participant-list{display:flex;flex-direction:column;gap:9px}.participant-item{border:1px solid var(--line);border-radius:9px;background:#fff;overflow:hidden}.participant-summary{display:grid;grid-template-columns:42px minmax(170px,1.5fr) minmax(130px,1fr) minmax(130px,1fr) auto auto;gap:14px;align-items:center;padding:11px 13px;cursor:pointer;list-style:none}.participant-summary::-webkit-details-marker{display:none}.participant-summary:hover{background:#fafafa}.participant-team strong{display:block}.participant-edit{padding:16px;border-top:1px solid var(--line);background:#fafafa}.participant-chevron{color:var(--muted);transition:transform .15s}.participant-item[open] .participant-chevron{transform:rotate(180deg)}@media(max-width:760px){.participant-summary{grid-template-columns:32px 1fr auto}.participant-hide-mobile{display:none}.participant-edit .form-grid{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
<div class="page-head"><div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ $tournament->status->value }}</span></div><div class="muted">{{ $tournament->competition }} · {{ $tournament->division }} · {{ str_replace('_',' ', $tournament->format->value) }}</div></div><div class="actions"><a class="btn secondary" href="{{ route('tournaments.settings', $tournament) }}">{{ __('ui.settings') }}</a>@if(in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true))<form method="post" action="{{ route('tournaments.start', $tournament) }}">@csrf<button class="btn">{{ __('ui.start_tournament') }}</button></form>@elseif($tournament->status === App\Enums\TournamentStatus::LIVE)<form method="post" action="{{ route('tournaments.complete', $tournament) }}">@csrf<button class="btn">{{ __('ui.complete') }}</button></form>@elseif($tournament->status === App\Enums\TournamentStatus::COMPLETED)<form method="post" action="{{ route('tournaments.archive', $tournament) }}">@csrf<button class="btn secondary">{{ __('ui.archive') }}</button></form>@endif</div></div>
@include('tournaments._tabs')
<div class="stats card"><div class="stat"><strong>{{ $tournament->participants_count }}</strong><span>Participants</span></div><div class="stat"><strong>{{ $tournament->matches_count }}</strong><span>Matches</span></div><div class="stat"><strong>{{ $tournament->ranking_attempts_count }}</strong><span>Attempts</span></div><div class="stat"><strong>{{ str_replace('_',' ', $tournament->seeding_method->value) }}</strong><span>Seeding</span></div></div>
@if(in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true))
<div class="grid"><section class="card"><h2>{{ __('ui.add_participant') }}</h2><form class="form-grid" method="post" action="{{ route('participants.store', $tournament) }}">@csrf<div class="field"><label>{{ __('ui.team_name') }}</label><input name="team_name" required></div><div class="field"><label>{{ __('ui.team_code') }}</label><input name="team_code"></div><div class="field"><label>{{ __('ui.school') }}</label><input name="school"></div><div class="field"><label>{{ __('ui.coach') }}</label><input name="coach_name"></div><div class="full"><button class="btn">{{ __('ui.add_team') }}</button></div></form></section>
<section class="card"><h2>{{ __('ui.import_csv') }}</h2><p class="muted">{{ __('ui.csv_help') }}</p><form method="post" enctype="multipart/form-data" action="{{ route('participants.import', $tournament) }}">@csrf<div class="field"><label for="csv_file">{{ __('ui.csv_file') }}</label><input id="csv_file" type="file" name="csv_file" accept=".csv,text/csv,text/plain" required></div><div class="actions"><button class="btn">{{ __('ui.import_participants') }}</button><a class="btn secondary" href="{{ route('participants.import.template') }}">{{ __('ui.download_template') }}</a></div></form></section></div>
@else
<section class="card"><div class="actions" style="justify-content:space-between"><div><h2 style="margin-bottom:3px">{{ __('ui.import_csv') }}</h2><div class="muted">{{ __('ui.csv_locked_help') }}</div></div><a class="btn secondary" href="{{ route('participants.import.template') }}">{{ __('ui.download_template') }}</a></div></section>
@endif
<section class="card"><div class="actions" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="margin:0">{{ __('ui.participants') }}</h2><div class="muted">{{ __('ui.participant_help') }}</div></div>@if(!in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true))<span class="badge">{{ __('ui.seeds_locked') }}</span>@endif</div>
<div class="participant-list">
@forelse($tournament->participants as $participant)
<details class="participant-item"><summary class="participant-summary"><strong>#{{ $participant->seed_number ?? '—' }}</strong><span class="participant-team"><strong>{{ $participant->team_name }}</strong><small class="muted">{{ $participant->team_code ?: __('ui.no_team_code') }}</small></span><span class="participant-hide-mobile">{{ $participant->school ?? '—' }}</span><span class="participant-hide-mobile">{{ $participant->coach_name ?? '—' }}</span><span class="badge">{{ $participant->status->value }}</span><span class="participant-chevron">⌄</span></summary>
<div class="participant-edit">
    <form method="post" action="{{ route('participants.update', [$tournament, $participant]) }}">@csrf @method('PUT')
        <div class="form-grid">
            <div class="field"><label>{{ __('ui.team_name') }}</label><input name="team_name" required maxlength="200" value="{{ $participant->team_name }}"></div>
            <div class="field"><label>{{ __('ui.team_code') }}</label><input name="team_code" maxlength="100" value="{{ $participant->team_code }}"></div>
            <div class="field"><label>{{ __('ui.school') }}</label><input name="school" maxlength="200" value="{{ $participant->school }}"></div>
            <div class="field"><label>{{ __('ui.coach_name') }}</label><input name="coach_name" maxlength="200" value="{{ $participant->coach_name }}"></div>
            @if(in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true))
            <div class="field"><label>{{ __('ui.seed') }}</label><input type="number" name="seed_number" min="1" value="{{ $participant->seed_number }}"></div>
            <div class="field"><label>{{ __('ui.status') }}</label><select name="status">@foreach(App\Enums\ParticipantStatus::cases() as $status)<option value="{{ $status->value }}" @selected($participant->status === $status)>{{ str_replace('_',' ', $status->value) }}</option>@endforeach</select></div>
            @endif
        </div>
        <button class="btn small">{{ __('ui.save_participant') }}</button>
    </form>
    @if(in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true))
    <form style="margin-top:8px" method="post" action="{{ route('participants.destroy', [$tournament, $participant]) }}" onsubmit="return confirm('Remove {{ addslashes($participant->team_name) }}?')">@csrf @method('DELETE')<button class="btn danger small">{{ __('ui.remove_participant') }}</button></form>
    @endif
</div></details>
@empty<div class="empty">{{ __('ui.no_participants') }}</div>@endforelse
</div></section>
@endsection
