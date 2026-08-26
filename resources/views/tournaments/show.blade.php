@extends('layouts.app')
@section('title', $tournament->name.' · EasyKids')
@php
    $isPublicView = request()->routeIs('public.tournaments.*');
    $isAdmin = !$isPublicView && (auth()->user()?->isAdmin() ?? false);
    $shareUrl = $isAdmin ? $tournament->publicShareUrl() : null;
    $bracketPrepared = ($tournament->matches_count ?? 0) > 0;
    $rosterEditable = in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true) && ! $bracketPrepared;
@endphp
@push('styles')
<style>
    #add-participant:target{border-color:#4d8db8;scroll-margin-top:calc(var(--top-height) + 12px)}
    .participant-list{display:flex;flex-direction:column;gap:8px}.participant-item{min-width:0;border:1px solid var(--line);border-radius:7px;background:var(--card);overflow:hidden}.participant-summary,.participant-list-head{display:grid;grid-template-columns:56px minmax(170px,1.5fr) minmax(130px,1fr) minmax(130px,1fr) 110px 24px;gap:12px;align-items:center;padding:10px 12px;list-style:none}.participant-list-head{margin-bottom:8px;border:1px solid var(--line);border-radius:7px;background:var(--soft);color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.participant-item details>.participant-summary{cursor:pointer}.participant-summary::-webkit-details-marker{display:none}.participant-item details>.participant-summary:hover{background:var(--soft)}.participant-team{min-width:0}.participant-team strong{display:block}.participant-team small{display:block}.participant-edit{padding:15px;border-top:1px solid var(--line);background:var(--soft)}.participant-chevron{color:var(--muted);transition:transform .15s}.participant-item details[open] .participant-chevron{transform:rotate(180deg)}@media(max-width:900px){.participant-summary,.participant-list-head{grid-template-columns:42px minmax(0,1fr) auto 24px;gap:8px;padding:10px}.participant-hide-mobile,.participant-list-head .participant-hide-mobile{display:none}.participant-team strong,.participant-team small{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}}@media(max-width:680px){.participant-edit{padding:13px}.participant-edit .form-grid{grid-template-columns:1fr}}
    @media(hover:none){.participant-item details>.participant-summary:hover{background:transparent}}
</style>
@endpush
@section('content')
<div class="page-head">
    <div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div><div class="muted">{{ $tournament->competition }} · {{ $tournament->division }} · {{ __('ui.format_labels.'.$tournament->format->value) }}</div></div>
    @if($isAdmin)<div class="actions"><a class="btn secondary" href="{{ route('tournaments.settings', $tournament) }}">{{ __('ui.settings') }}</a>@if($tournament->structure === App\Enums\TournamentStructure::ADVANCED && ! $bracketPrepared)<a class="btn secondary" href="{{ route('tournaments.groups.edit', $tournament) }}">{{ __('ui.group_assignments') }}</a>@endif @if(in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true) && ! $bracketPrepared)<form method="post" action="{{ route('tournaments.prepare-bracket', $tournament) }}">@csrf<button class="btn secondary">{{ __('ui.prepare_bracket') }}</button></form><form method="post" action="{{ route('tournaments.start', $tournament) }}">@csrf<button class="btn">{{ __('ui.start_tournament') }}</button></form>@elseif($tournament->status === App\Enums\TournamentStatus::READY && $bracketPrepared)<form method="post" action="{{ route('tournaments.start', $tournament) }}">@csrf<button class="btn">{{ __('ui.start_tournament') }}</button></form>@elseif($tournament->status === App\Enums\TournamentStatus::LIVE)<form method="post" action="{{ route('tournaments.complete', $tournament) }}">@csrf<button class="btn">{{ __('ui.complete') }}</button></form>@elseif($tournament->status === App\Enums\TournamentStatus::COMPLETED)<form method="post" action="{{ route('tournaments.archive', $tournament) }}">@csrf<button class="btn secondary">{{ __('ui.archive') }}</button></form>@endif</div>@endif
</div>
@include('tournaments._tabs')
@includeWhen($isPublicView, 'tournaments._live_refresh')
<div class="stats card"><div class="stat"><strong>{{ $tournament->participants_count }}</strong><span>{{ __('ui.participant_count') }}</span></div><div class="stat"><strong>{{ $tournament->matches_count }}</strong><span>{{ __('ui.matches') }}</span></div><div class="stat"><strong>{{ $tournament->ranking_attempts_count }}</strong><span>{{ __('ui.attempt_count') }}</span></div><div class="stat"><strong>{{ __('ui.seeding_labels.'.$tournament->seeding_method->value) }}</strong><span>{{ __('ui.seeding') }}</span></div></div>

<section class="card competition-detail-card">
    <h2>{{ __('ui.competition_details') }}</h2>
    <div class="detail-grid">
        <div class="detail-item"><small>{{ __('ui.competition_date') }}</small><strong>{{ $tournament->competition_date?->translatedFormat('j M Y · H:i') ?? __('ui.not_specified') }}</strong></div>
        <div class="detail-item"><small>{{ __('ui.venue') }}</small><strong>{{ $tournament->venue ?: __('ui.not_specified') }}</strong></div>
        <div class="detail-item"><small>{{ __('ui.division') }}</small><strong>{{ $tournament->division }}</strong></div>
        @if($tournament->format === App\Enums\TournamentFormat::DOUBLE_ELIMINATION)<div class="detail-item detail-item-accent"><small>{{ __('ui.grand_final_setting') }}</small><strong>{{ (int) ($tournament->double_elimination_config['grand_final_matches'] ?? 2) === 1 ? __('ui.grand_final_one_match') : __('ui.grand_final_two_matches') }}</strong></div>@endif
        @if($tournament->notes)<div class="detail-item full"><small>{{ __('ui.notes') }}</small><span>{!! nl2br(e($tournament->notes)) !!}</span></div>@endif
    </div>
</section>

@if($isAdmin)
<section class="card">
    <div class="actions split-actions" style="justify-content:space-between;margin-bottom:10px"><div><h2 style="margin:0 0 3px">{{ __('ui.share_view_link') }}</h2><div class="muted">{{ __('ui.share_link_help') }}</div></div><span class="badge {{ in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE], true) ? 'available-now' : $tournament->status->value }}">{{ in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE], true) ? __('ui.available_now') : __('ui.available_when_live') }}</span></div>
    @if($shareUrl)
    <div class="share-link-row"><input id="share-link" readonly value="{{ $shareUrl }}" aria-label="{{ __('ui.share_view_link') }}"><button class="btn secondary" type="button" data-copy-target="#share-link" data-copied="{{ __('ui.share_link_copied') }}">{{ __('ui.copy_share_link') }}</button>@if(in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE], true))<a class="btn secondary" href="{{ $shareUrl }}" target="_blank" rel="noopener">{{ __('ui.open_view_page') }}</a>@else<span class="btn secondary" aria-disabled="true" style="opacity:.5;cursor:not-allowed">{{ __('ui.waiting_for_bracket') }}</span>@endif</div>
    <form class="short-link-form" method="post" action="{{ route('tournaments.share-link.update', $tournament) }}">@csrf @method('PATCH')
        <label for="share_slug">{{ __('ui.short_viewer_link') }}</label><div class="short-link-control-row"><div class="short-link-input"><span>{{ url('/view') }}/</span><input id="share_slug" name="share_slug" required minlength="4" maxlength="36" pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])" value="{{ old('share_slug', Illuminate\Support\Str::isUuid($tournament->public_token) ? '' : $tournament->public_token) }}" placeholder="{{ __('ui.short_viewer_link_placeholder') }}" aria-label="{{ __('ui.share_slug') }}" autocomplete="off" autocapitalize="none" spellcheck="false"></div><button class="btn" type="submit" data-submitting="{{ __('ui.saving') }}">{{ __('ui.save_short_link') }}</button></div><small class="muted">{{ __('ui.short_viewer_link_help') }}</small>
    </form>
    @else
    <div class="alert error" style="margin:12px 0 0"><strong>{{ __('ui.share_link_not_ready') }}</strong><div>{{ __('ui.run_migrations_help') }}</div></div>
    @endif
</section>
@endif

@if($isAdmin && $rosterEditable)
<div class="grid">
    <section class="card" id="add-participant" tabindex="-1"><h2>{{ __('ui.add_participant') }}</h2><form class="form-grid" method="post" action="{{ route('participants.store', $tournament) }}">@csrf<div class="field"><label for="new-team-name">{{ __('ui.team_name') }}</label><input id="new-team-name" name="team_name" value="{{ old('team_name') }}" required></div><div class="field"><label for="new-team-code">{{ __('ui.team_code') }}</label><input id="new-team-code" name="team_code" value="{{ old('team_code') }}"></div><div class="field"><label for="new-school">{{ __('ui.school') }}</label><input id="new-school" name="school" value="{{ old('school') }}"></div><div class="field"><label for="new-coach">{{ __('ui.coach') }}</label><input id="new-coach" name="coach_name" value="{{ old('coach_name') }}"></div><div class="full"><button class="btn">{{ __('ui.add_team') }}</button></div></form></section>
    <section class="card bulk-participant-card"><h2>{{ __('ui.bulk_add_participants') }}</h2><p class="muted">{{ __('ui.bulk_add_participants_help') }}</p><form method="post" action="{{ route('participants.bulk-store', $tournament) }}">@csrf<div class="field"><label for="bulk_participants">{{ __('ui.participants') }}</label><textarea id="bulk_participants" name="bulk_participants" rows="9" placeholder="{{ __('ui.bulk_participants_placeholder') }}">{{ old('bulk_participants') }}</textarea></div><button class="btn" type="submit">{{ __('ui.add_bulk_participants') }}</button></form></section>
    <section class="card"><h2>{{ __('ui.import_csv') }}</h2><p class="muted">{{ __('ui.csv_help') }}</p><form method="post" enctype="multipart/form-data" action="{{ route('participants.import', $tournament) }}">@csrf<div class="field"><label for="csv_file">{{ __('ui.csv_file') }}</label><input id="csv_file" type="file" name="csv_file" accept=".csv,text/csv,text/plain,application/vnd.ms-excel" required></div><div class="actions"><button class="btn" type="submit">{{ __('ui.import_participants') }}</button><a class="btn secondary" href="{{ route('participants.import.template') }}">{{ __('ui.download_template') }}</a></div></form></section>
</div>
@elseif($isAdmin)
<section class="card"><div class="actions split-actions" style="justify-content:space-between"><div><h2 style="margin-bottom:3px">{{ __('ui.import_csv') }}</h2><div class="muted">{{ __('ui.csv_locked_help') }}</div></div><a class="btn secondary" href="{{ route('participants.import.template') }}">{{ __('ui.download_template') }}</a></div></section>
@endif

<section class="card">
    <div class="actions split-actions" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="margin:0">{{ __('ui.participants') }}</h2><div class="muted">{{ $isAdmin ? __('ui.participant_help') : __('ui.read_only_notice') }}</div></div>@if($isAdmin && $rosterEditable && $tournament->participants_count > 1)<form method="post" action="{{ route('tournaments.randomize-participants', $tournament) }}" data-confirm="{{ __('ui.randomize_participants_confirm') }}">@csrf<button class="btn secondary small" type="submit">{{ __('ui.auto_assign_bracket') }}</button></form>@elseif(!$rosterEditable)<span class="badge">{{ __('ui.seeds_locked') }}</span>@endif</div>
    <div class="participant-list">
    <div class="participant-list-head" aria-hidden="true"><span>{{ __('ui.seed') }}</span><span>{{ __('ui.team_name') }}</span><span class="participant-hide-mobile">{{ __('ui.school') }}</span><span class="participant-hide-mobile">{{ __('ui.coach_name') }}</span><span>{{ __('ui.status') }}</span><span></span></div>
    @forelse($tournament->participants as $participant)
        <article class="participant-item">
        @if($isAdmin)
            <details><summary class="participant-summary"><strong>#{{ $participant->seed_number ?? '—' }}</strong><span class="participant-team"><strong>{{ $participant->team_name }}</strong><small class="muted">{{ $participant->team_code ?: __('ui.no_team_code') }}</small></span><span class="participant-hide-mobile">{{ $participant->school ?? '—' }}</span><span class="participant-hide-mobile">{{ $participant->coach_name ?? '—' }}</span><span class="badge">{{ __('ui.participant_status_labels.'.$participant->status->value) }}</span><span class="participant-chevron">⌄</span></summary>
                <div class="participant-edit">
                    <form method="post" action="{{ route('participants.update', [$tournament, $participant]) }}">@csrf @method('PUT')
                        <div class="form-grid">
                            <div class="field"><label>{{ __('ui.team_name') }}</label><input name="team_name" required maxlength="200" value="{{ $participant->team_name }}"></div>
                            <div class="field"><label>{{ __('ui.team_code') }}</label><input name="team_code" maxlength="100" value="{{ $participant->team_code }}"></div>
                            <div class="field"><label>{{ __('ui.school') }}</label><input name="school" maxlength="200" value="{{ $participant->school }}"></div>
                            <div class="field"><label>{{ __('ui.coach_name') }}</label><input name="coach_name" maxlength="200" value="{{ $participant->coach_name }}"></div>
                            @if($rosterEditable)<div class="field"><label>{{ __('ui.seed') }}</label><input type="number" name="seed_number" min="1" value="{{ $participant->seed_number }}"></div><div class="field"><label>{{ __('ui.status') }}</label><select name="status">@foreach(App\Enums\ParticipantStatus::cases() as $status)<option value="{{ $status->value }}" @selected($participant->status === $status)>{{ __('ui.participant_status_labels.'.$status->value) }}</option>@endforeach</select></div>@endif
                        </div>
                        <button class="btn small">{{ __('ui.save_participant') }}</button>
                    </form>
                    @if($rosterEditable)<form style="margin-top:8px" method="post" action="{{ route('participants.destroy', [$tournament, $participant]) }}" data-confirm="{{ __('ui.remove_participant_confirm', ['name' => $participant->team_name]) }}">@csrf @method('DELETE')<button class="btn danger small">{{ __('ui.remove_participant') }}</button></form>@endif
                </div>
            </details>
        @else
            <div class="participant-summary"><strong>#{{ $participant->seed_number ?? '—' }}</strong><span class="participant-team"><strong>{{ $participant->team_name }}</strong><small class="muted">{{ $participant->team_code ?: __('ui.no_team_code') }}</small></span><span class="participant-hide-mobile">{{ $participant->school ?? '—' }}</span><span class="participant-hide-mobile">{{ $participant->coach_name ?? '—' }}</span><span class="badge">{{ __('ui.participant_status_labels.'.$participant->status->value) }}</span><span></span></div>
        @endif
        </article>
    @empty<div class="empty">{{ __('ui.no_participants') }}</div>@endforelse
    </div>
</section>
@endsection
