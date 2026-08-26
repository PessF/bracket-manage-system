@extends('layouts.app')
@section('title', __('ui.group_assignments').' · EasyKids')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ __('ui.group_assignments') }}</h1>
        <div class="muted">{{ $tournament->name }}</div>
    </div>
</div>
@include('tournaments._tabs')

<section class="card">
    <div class="actions" style="justify-content:space-between">
        <div>
            <h2 style="margin-bottom:3px">{{ __('ui.assign_teams_to_groups') }}</h2>
            <div class="muted">{{ __('ui.assign_teams_to_groups_help') }}</div>
        </div>
        @if($locked)<span class="badge">{{ __('ui.seeds_locked') }}</span>@else<form method="post" action="{{ route('tournaments.groups.randomize', $tournament) }}" data-confirm="{{ __('ui.randomize_group_assignments_confirm') }}">@csrf<button class="btn secondary" type="submit">{{ __('ui.randomize_group_assignments') }}</button></form>@endif
    </div>

    <div class="group-assignment-summary">
        @foreach($groupStage->groups as $group)
            @php
                $assignedCount = $group->participants->count();
                $limit = $group->team_limit;
            @endphp
            <div class="group-summary-card" data-group-card="{{ $group->id }}" data-group-limit="{{ $limit ?: '' }}">
                <strong>{{ $group->name }}</strong>
                <span data-group-count>{{ $assignedCount }}{{ $limit ? ' / '.$limit : '' }} {{ __('ui.teams') }}</span>
                <small data-group-status></small>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('tournaments.groups.update', $tournament) }}">
        @csrf
        @method('PUT')
        <div class="participant-group-table">
            <div class="participant-group-row participant-group-head">
                <div>{{ __('ui.seed') }}</div>
                <div>{{ __('ui.team_name') }}</div>
                <div>{{ __('ui.group') }}</div>
            </div>
            @foreach($participants as $participant)
                <div class="participant-group-row">
                    <div class="participant-seed">#{{ $participant->seed_number }}</div>
                    <div>
                        <strong>{{ $participant->team_name }}</strong>
                        <div class="muted">{{ $participant->team_code ?: __('ui.no_team_code') }}</div>
                    </div>
                    <div class="field">
                        <label class="sr-only" for="group_{{ $participant->id }}">{{ __('ui.group') }}</label>
                        <select id="group_{{ $participant->id }}" name="groups[{{ $participant->id }}]" data-group-select @disabled($locked)>
                            <option value="">{{ __('ui.not_assigned') }}</option>
                            @foreach($groupStage->groups as $group)
                                <option value="{{ $group->id }}" data-group-option="{{ $group->id }}" @selected((string) ($assignments[$participant->id] ?? '') === (string) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="actions" style="margin-top:16px">
            <button class="btn" data-save-groups @disabled($locked)>{{ __('ui.save_group_assignments') }}</button>
            <a class="btn secondary" href="{{ route('tournaments.show', $tournament) }}">{{ __('ui.cancel') }}</a>
        </div>
        <div class="muted group-form-status" data-group-form-status></div>
    </form>
</section>
@endsection

@push('styles')
<style>
    .group-assignment-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin:16px 0; }
    .group-summary-card { display:flex; flex-direction:column; gap:3px; padding:12px; border:1px solid var(--line); border-radius:8px; background:var(--soft); }
    .group-summary-card span { color:var(--muted); font-size:13px; }
    .group-summary-card small { min-height:18px; color:var(--muted); font-size:12px; font-weight:700; }
    .group-summary-card.complete { border-color:rgb(73 207 155 / .45); background:rgb(22 82 59 / .34); }
    .group-summary-card.complete small { color:#8cf0bf; }
    .group-summary-card.over { border-color:rgb(255 117 145 / .52); background:rgb(91 29 47 / .28); }
    .group-summary-card.over small { color:#ff9caf; }
    .group-summary-card.open small { color:#8be9ff; }
    .participant-group-table { display:flex; flex-direction:column; gap:8px; }
    .participant-group-row { display:grid; grid-template-columns:90px minmax(0,1fr) minmax(190px,280px); gap:12px; align-items:center; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:var(--card); }
    .participant-group-head { color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; background:transparent; }
    .participant-group-row .field { margin:0; }
    .participant-seed { color:var(--accent); font-weight:800; }
    .group-form-status { margin-top:8px; font-size:13px; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @media (max-width: 900px) { .group-assignment-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } .participant-group-row { grid-template-columns:70px minmax(0,1fr); } .participant-group-row > .field { grid-column:1 / -1; } }
    @media (max-width: 560px) { .group-assignment-summary { grid-template-columns:1fr; } }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const locked = @js($locked);
    const selects = Array.from(document.querySelectorAll('[data-group-select]'));
    const cards = Array.from(document.querySelectorAll('[data-group-card]'));
    const saveButton = document.querySelector('[data-save-groups]');
    const formStatus = document.querySelector('[data-group-form-status]');
    if (locked || selects.length === 0 || cards.length === 0) return;

    const labels = {
        complete: @js(__('ui.group_complete')),
        remaining: @js(__('ui.group_remaining')),
        over: @js(__('ui.group_over')),
        formReady: @js(__('ui.group_assignment_ready')),
        formIncomplete: @js(__('ui.group_assignment_incomplete')),
    };
    let syncing = false;

    const sync = () => {
        if (syncing) return;
        const counts = new Map(cards.map((card) => [card.dataset.groupCard, 0]));
        let unassigned = 0;

        selects.forEach((select) => {
            if (select.value) counts.set(select.value, (counts.get(select.value) || 0) + 1);
            else unassigned += 1;
        });

        let hasOverLimit = false;
        cards.forEach((card) => {
            const groupId = card.dataset.groupCard;
            const count = counts.get(groupId) || 0;
            const limit = Number(card.dataset.groupLimit || 0);
            const countTarget = card.querySelector('[data-group-count]');
            const statusTarget = card.querySelector('[data-group-status]');
            const remaining = limit > 0 ? limit - count : 0;

            card.classList.remove('complete', 'open', 'over');
            if (limit > 0 && remaining < 0) {
                card.classList.add('over');
                hasOverLimit = true;
                statusTarget.textContent = labels.over.replace(':count', Math.abs(remaining));
            } else if (limit > 0 && remaining === 0) {
                card.classList.add('complete');
                statusTarget.textContent = labels.complete;
            } else if (limit > 0) {
                card.classList.add('open');
                statusTarget.textContent = labels.remaining.replace(':count', remaining);
            } else {
                card.classList.add('open');
                statusTarget.textContent = '';
            }

            countTarget.textContent = limit > 0 ? `${count} / ${limit} ${@js(__('ui.teams'))}` : `${count} ${@js(__('ui.teams'))}`;
        });

        syncing = true;
        selects.forEach((select) => {
            const current = select.value;
            Array.from(select.options).forEach((option) => {
                const groupId = option.dataset.groupOption;
                if (!groupId) return;
                const card = cards.find((candidate) => candidate.dataset.groupCard === groupId);
                const limit = Number(card?.dataset.groupLimit || 0);
                const full = limit > 0 && (counts.get(groupId) || 0) >= limit;
                option.hidden = full && current !== groupId;
                option.disabled = full && current !== groupId;
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        syncing = false;

        const ready = unassigned === 0 && !hasOverLimit;
        if (saveButton) saveButton.disabled = !ready;
        if (formStatus) {
            formStatus.textContent = ready
                ? labels.formReady
                : labels.formIncomplete.replace(':count', unassigned);
        }
    };

    selects.forEach((select) => select.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
