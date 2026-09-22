@extends('layouts.app')
@section('title', __('ui.results').' · '.$tournament->name)

@php
    $isRanking = $tournament->format === App\Enums\TournamentFormat::RANKING;
    $isDoubleElimination = $tournament->format === App\Enums\TournamentFormat::DOUBLE_ELIMINATION;
    $rankingType = App\Enums\RankingType::tryFrom((string) ($tournament->ranking_config['type'] ?? ''));
    $isRacingRobot = $rankingType === App\Enums\RankingType::RACING_ROBOT;
    $isDroneMission = $rankingType === App\Enums\RankingType::DRONE_MISSION;
    $attemptLimit = $tournament->rankingAttemptLimit();
    $formatRankingValue = fn ($value): string => $value !== null ? number_format((float) $value, 2, '.', '') : '—';
    $formatDroneTime = fn ($value): string => $value !== null ? $formatRankingValue($value).' '.__('ui.minutes_short') : '—';
    $formatMatchScore = function ($value): string {
        $formatted = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    };
    $attemptsByParticipant = $participants->mapWithKeys(fn ($participant) => [
        (string) $participant->id => $participant->rankingAttempts->keyBy('attempt_number'),
    ]);
    $standingsByParticipant = $standings->keyBy(fn ($standing) => (string) ($standing->participant_id ?? $standing->participant?->id ?? ''));
    $rankingLeaders = $isRanking
        ? $standings->filter(fn ($standing) => (int) $standing->rank_number > 0)->take(3)
        : collect();
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $tournament->name }} — {{ __('ui.results') }}</h1>
        <div class="muted">{{ __('ui.live_standings') }}</div>
    </div>
    <span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span>
</div>

@include('tournaments._tabs')
@includeWhen($tournament->status === App\Enums\TournamentStatus::LIVE, 'tournaments._live_refresh', [
    'interval' => 1,
    'refreshTarget' => '[data-live-results]',
])

@if(!request()->routeIs('public.tournaments.*') && (auth()->user()?->isAdmin() ?? false) && $isRanking && $tournament->status === App\Enums\TournamentStatus::LIVE)
<section class="card ranking-entry-card">
    <h2>{{ __('ui.record_attempts') }}@if($rankingType) · {{ __('ui.ranking_type_labels.'.$rankingType->value) }}@endif</h2>
    @if($isRacingRobot)<p class="muted standings-rule">{{ __('ui.racing_ranking_rule') }}</p>@endif
    @if($isDroneMission)<p class="muted standings-rule">{{ __('ui.drone_ranking_rule') }}</p>@endif
    <div class="ranking-round-selector">
        <div>
            <strong>{{ __('ui.ranking_round_selector') }}</strong>
            <span>{{ __('ui.ranking_round_selector_help') }}</span>
        </div>
        <label class="sr-only" for="rankingRoundSelector">{{ __('ui.ranking_round_selector') }}</label>
        <select id="rankingRoundSelector" data-ranking-round-selector>
            @foreach(range(1, $attemptLimit) as $roundNumber)
            <option value="{{ $roundNumber }}" @selected((int) old('attempt_number', 1) === $roundNumber)>{{ __('ui.round_number', ['number' => $roundNumber]) }}</option>
            @endforeach
        </select>
    </div>
    <div class="ranking-save-status" data-ranking-save-status role="status" aria-live="polite" hidden></div>
    <div class="ranking-entry-list">
        @foreach($participants as $participant)
        <div class="ranking-entry-row">
            <div class="ranking-entry-team">
                <label>{{ $participant->team_name }}</label>
                <div class="muted">{{ $participant->rankingAttempts->count() }} {{ __('ui.saved') }}</div>
            </div>
            <div class="ranking-round-panels">
                @foreach(range(1, $attemptLimit) as $roundNumber)
                @php
                    $attempt = $attemptsByParticipant->get((string) $participant->id, collect())->get($roundNumber);
                    $isOldEntry = (string) old('ranking_entry_participant') === (string) $participant->id
                        && (int) old('attempt_number') === $roundNumber;
                @endphp
                <div data-ranking-round-panel data-round="{{ $roundNumber }}" @if((int) old('attempt_number', 1) !== $roundNumber) hidden @endif>
                    @if($attempt)
                    <div class="ranking-saved-result {{ $attempt->is_valid ? '' : 'invalid' }}" data-ranking-saved-result>
                        <div class="ranking-saved-heading">
                            <span>{{ __('ui.saved_result') }}</span>
                            <strong>{{ __('ui.round_number', ['number' => $roundNumber]) }}</strong>
                        </div>
                        <div class="ranking-saved-values">
                            @if($isDroneMission)
                            <span><small>{{ __('ui.total_score') }}</small><strong>{{ $formatRankingValue($attempt->attempt_value) }}</strong></span>
                            <span><small>{{ __('ui.manual_score') }}</small><strong>{{ $formatRankingValue($attempt->manual_score) }}</strong></span>
                            <span><small>{{ __('ui.auto_score') }}</small><strong>{{ $formatRankingValue($attempt->auto_score) }}</strong></span>
                            <span><small>{{ __('ui.time_minutes') }}</small><strong>{{ $formatDroneTime($attempt->attempt_time) }}</strong></span>
                            @else
                            <span><small>{{ $isRacingRobot ? __('ui.time_seconds') : __('ui.value') }}</small><strong>{{ $formatRankingValue($attempt->attempt_value) }}{{ $isRacingRobot ? ' s' : '' }}</strong></span>
                            @endif
                            @unless($attempt->is_valid)<em>{{ __('ui.invalid_attempt') }}</em>@endunless
                        </div>
                        <button
                            class="btn small secondary ranking-edit-trigger"
                            type="button"
                            data-ranking-edit-trigger
                            data-action="{{ route('ranking.attempts.store', [$tournament, $participant]) }}"
                            data-participant="{{ $participant->id }}"
                            data-team="{{ $participant->team_name }}"
                            data-round="{{ $roundNumber }}"
                            data-attempt-value="{{ $formatRankingValue($attempt->attempt_value) }}"
                            data-manual-score="{{ $attempt->manual_score }}"
                            data-auto-score="{{ $attempt->auto_score }}"
                            data-attempt-time="{{ $attempt->attempt_time }}"
                            data-valid="{{ $attempt->is_valid ? '1' : '0' }}"
                        >{{ __('ui.edit_ranking_result') }}</button>
                    </div>
                    @else
                    <form class="ranking-new-result-form {{ $isDroneMission ? 'drone' : '' }}" method="post" action="{{ route('ranking.attempts.store', [$tournament, $participant]) }}" data-ranking-async-form>
                        @csrf
                        <input type="hidden" name="attempt_number" value="{{ $roundNumber }}">
                        <input type="hidden" name="ranking_entry_participant" value="{{ $participant->id }}">
                        @if($isDroneMission)
                        <div class="field"><label>{{ __('ui.manual_score') }}</label><input type="number" name="manual_score" value="{{ $isOldEntry ? old('manual_score') : '' }}" min="0" max="50" step="0.01" inputmode="decimal" required></div>
                        <div class="field"><label>{{ __('ui.auto_score') }}</label><input type="number" name="auto_score" value="{{ $isOldEntry ? old('auto_score') : '' }}" min="0" max="50" step="0.01" inputmode="decimal" required></div>
                        <div class="field"><label>{{ __('ui.time_minutes') }}</label><input type="number" name="attempt_time" value="{{ $isOldEntry ? old('attempt_time') : '' }}" min="0" step="0.01" inputmode="decimal" required></div>
                        @else
                        <div class="field"><label>{{ $isRacingRobot ? __('ui.time_seconds') : __('ui.value') }}</label><input type="number" name="attempt_value" value="{{ $isOldEntry ? old('attempt_value') : '' }}" min="0" step="0.01" inputmode="decimal" required></div>
                        @endif
                        <div class="field ranking-valid-field">
                            <label>{{ __('ui.valid') }}</label>
                            <input type="hidden" name="is_valid" value="0">
                            <input type="checkbox" name="is_valid" value="1" @checked(! $isOldEntry || old('is_valid'))>
                        </div>
                        <button class="btn small">{{ __('ui.save') }}</button>
                    </form>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</section>

<dialog class="ranking-edit-modal" data-ranking-edit-modal aria-labelledby="rankingEditModalTitle"
    data-reopen-participant="{{ old('ranking_edit_participant') }}"
    data-old-attempt-value="{{ old('attempt_value') }}"
    data-old-manual-score="{{ old('manual_score') }}"
    data-old-auto-score="{{ old('auto_score') }}"
    data-old-attempt-time="{{ old('attempt_time') }}"
    data-old-valid="{{ old('is_valid') }}">
    <div class="ranking-edit-modal-head">
        <div><span>{{ __('ui.edit_ranking_result') }}</span><h2 id="rankingEditModalTitle" data-ranking-edit-title></h2></div>
        <button type="button" data-ranking-edit-close aria-label="{{ __('ui.close') }}">×</button>
    </div>
    <form class="ranking-edit-modal-form {{ $isDroneMission ? 'drone' : '' }}" method="post" data-ranking-edit-form data-ranking-async-form>
        @csrf
        <input type="hidden" name="attempt_number" data-ranking-edit-round>
        <input type="hidden" name="ranking_edit_participant" data-ranking-edit-participant>
        @if($isDroneMission)
        <div class="field"><label>{{ __('ui.manual_score') }}</label><input type="number" name="manual_score" min="0" max="50" step="0.01" inputmode="decimal" required></div>
        <div class="field"><label>{{ __('ui.auto_score') }}</label><input type="number" name="auto_score" min="0" max="50" step="0.01" inputmode="decimal" required></div>
        <div class="field"><label>{{ __('ui.time_minutes') }}</label><input type="number" name="attempt_time" min="0" step="0.01" inputmode="decimal" required></div>
        @else
        <div class="field"><label>{{ $isRacingRobot ? __('ui.time_seconds') : __('ui.value') }}</label><input type="number" name="attempt_value" min="0" step="0.01" inputmode="decimal" required></div>
        @endif
        <label class="ranking-edit-valid"><input type="hidden" name="is_valid" value="0"><input type="checkbox" name="is_valid" value="1"> <span>{{ __('ui.valid') }}</span></label>
        <div class="ranking-edit-modal-actions"><button class="btn secondary" type="button" data-ranking-edit-close>{{ __('ui.cancel') }}</button><button class="btn" type="submit">{{ __('ui.save_corrected_score') }}</button></div>
    </form>
</dialog>
@endif

<div data-live-results>
@if($isRanking)
<section class="ranking-view-hero" aria-labelledby="rankingViewTitle">
    <div class="ranking-view-copy">
        <span class="ranking-kicker">{{ __('ui.live_rankings') }}</span>
        <h2 id="rankingViewTitle">{{ $rankingType ? __('ui.ranking_type_labels.'.$rankingType->value) : $tournament->name }}</h2>
        <p>{{ $isRacingRobot ? __('ui.racing_ranking_rule') : ($isDroneMission ? __('ui.drone_ranking_rule') : __('ui.live_standings')) }}</p>
    </div>
    <div class="ranking-round-count">
        <strong>{{ $attemptLimit }}</strong>
        <span>{{ __('ui.configured_rounds') }}</span>
    </div>
</section>

@if($rankingLeaders->isNotEmpty())
<section class="ranking-leaders" aria-label="{{ __('ui.leading_participants') }}">
    @foreach($rankingLeaders as $leader)
    @php
        $leaderRank = (int) $leader->rank_number;
    @endphp
    <article class="ranking-leader rank-{{ $leaderRank }}">
        <span class="ranking-leader-rank">#{{ $leaderRank }}</span>
        <div>
            <strong>{{ $leader->participant->team_name }}</strong>
            <span>
                @if($isDroneMission)
                {{ __('ui.total_score') }} {{ $formatRankingValue($leader->best_value) }} · {{ $formatDroneTime($leader->format_data['attempt_time'] ?? null) }}
                @else
                {{ $formatRankingValue($leader->best_value) }}{{ $isRacingRobot ? ' s' : '' }}
                @endif
            </span>
        </div>
    </article>
    @endforeach
</section>
@endif
@endif

<section class="card">
    <h2>{{ __('ui.standings') }}</h2>
    @if($isRacingRobot)<p class="muted standings-rule">{{ __('ui.racing_ranking_rule') }}</p>
    @elseif($isDroneMission)<p class="muted standings-rule">{{ __('ui.drone_ranking_rule') }}</p>
    @elseif(!$isRanking)
    <p class="muted standings-rule">{{ __($isDoubleElimination ? 'ui.double_elimination_standings_rule' : 'ui.round_robin_standings_rule') }}</p>
    @endif
    <div class="table-wrap standings-wrap {{ $isRanking ? 'ranking-standings-wrap' : '' }}" @if($isRanking) role="region" aria-label="{{ __('ui.standings') }}" tabindex="0" @endif>
        <table class="standings-table">
            <thead>
                <tr>
                    <th>{{ __('ui.rank') }}</th>
                    <th>{{ __('ui.participant') }}</th>
                    @if($isRanking)
                        @if($isDroneMission)
                        <th>{{ __('ui.total_score') }}</th><th>{{ __('ui.manual_score') }}</th><th>{{ __('ui.auto_score') }}</th><th>{{ __('ui.time_minutes') }}</th>
                        @else
                        <th>{{ $isRacingRobot ? __('ui.best_time') : __('ui.best_value') }}</th>
                        @endif
                    @else
                    <th>{{ __('ui.played') }}</th>
                    <th>{{ __('ui.wins') }}</th>
                    <th>{{ __('ui.draws') }}</th>
                    <th>{{ __('ui.losses') }}</th>
                    <th>{{ __('ui.score_for') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($standings as $standing)
                @php
                    $rank = (int) $standing->rank_number;
                @endphp
                <tr class="{{ $rank >= 1 && $rank <= 3 ? 'rank-row rank-'.$rank : ($rank === 0 ? 'unranked-row' : '') }}">
                    <td data-label="{{ __('ui.rank') }}">
                        @if($rank >= 1 && $rank <= 3)
                        <span class="rank-medal rank-{{ $rank }}">#{{ $rank }}</span>
                        @else
                        <strong>{{ $standing->rank_number ?: '—' }}</strong>
                        @endif
                    </td>
                    <td data-label="{{ __('ui.participant') }}">{{ $standing->participant->team_name }}</td>
                    @if($isRanking)
                        @if($isDroneMission)
                        <td data-label="{{ __('ui.total_score') }}"><strong class="best-value">{{ $formatRankingValue($standing->best_value) }}</strong></td>
                        <td data-label="{{ __('ui.manual_score') }}">{{ $formatRankingValue($standing->format_data['manual_score'] ?? null) }}</td>
                        <td data-label="{{ __('ui.auto_score') }}">{{ $formatRankingValue($standing->format_data['auto_score'] ?? null) }}</td>
                        <td data-label="{{ __('ui.time_minutes') }}">{{ $formatDroneTime($standing->format_data['attempt_time'] ?? null) }}</td>
                        @else
                        <td data-label="{{ $isRacingRobot ? __('ui.best_time') : __('ui.best_value') }}"><strong class="best-value">{{ $formatRankingValue($standing->best_value) }}{{ $isRacingRobot && $standing->best_value !== null ? ' s' : '' }}</strong></td>
                        @endif
                    @else
                    <td>{{ $standing->played }}</td>
                    <td><strong>{{ $standing->wins }}</strong></td>
                    <td>{{ $standing->draws }}</td>
                    <td>{{ $standing->losses }}</td>
                    <td>{{ $formatMatchScore($standing->score_for) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $isDroneMission ? 6 : ($isRanking ? 3 : 7) }}" class="empty">{{ __('ui.standings_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($isRanking)
<section class="card ranking-attempts-card">
    <h2>{{ __('ui.attempt_results') }}</h2>
    <p class="ranking-scroll-note">{{ __('ui.swipe_ranking_rounds') }}</p>
    <div class="table-wrap ranking-attempts-wrap" role="region" aria-label="{{ __('ui.attempt_results') }}" tabindex="0">
        <table class="standings-table ranking-attempts-table">
            <thead>
                <tr>
                    <th>{{ __('ui.rank') }}</th>
                    <th>{{ __('ui.participant') }}</th>
                    @for($attemptNumber = 1; $attemptNumber <= $attemptLimit; $attemptNumber++)
                    <th>{{ $rankingType ? __('ui.lap') : __('ui.attempt') }} {{ $attemptNumber }}</th>
                    @endfor
                    <th>{{ $isRacingRobot ? __('ui.best_time') : ($isDroneMission ? __('ui.total_score') : __('ui.best_value')) }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($participants as $participant)
                @php
                    $standing = $standingsByParticipant->get((string) $participant->id);
                    $rank = (int) ($standing?->rank_number ?? 0);
                    $participantAttempts = $attemptsByParticipant->get((string) $participant->id, collect());
                @endphp
                <tr class="{{ $rank >= 1 && $rank <= 3 ? 'rank-row rank-'.$rank : ($rank === 0 ? 'unranked-row' : '') }}">
                    <td>
                        @if($rank >= 1 && $rank <= 3)
                        <span class="rank-medal rank-{{ $rank }}">#{{ $rank }}</span>
                        @else
                        <strong>{{ $rank ?: '—' }}</strong>
                        @endif
                    </td>
                    <td>{{ $participant->team_name }}</td>
                    @for($attemptNumber = 1; $attemptNumber <= $attemptLimit; $attemptNumber++)
                    @php
                        $attempt = $participantAttempts->get($attemptNumber);
                    @endphp
                    <td>
                        @if($attempt)
                        <span class="attempt-value {{ $attempt->is_valid ? '' : 'invalid' }}" title="{{ $attempt->is_valid ? __('ui.valid') : __('ui.invalid_attempt') }}">
                            @if($isDroneMission)
                            <strong>{{ $formatRankingValue($attempt->attempt_value) }}</strong><small>M {{ $formatRankingValue($attempt->manual_score) }} · A {{ $formatRankingValue($attempt->auto_score) }} · {{ $formatDroneTime($attempt->attempt_time) }}</small>
                            @else
                            {{ $formatRankingValue($attempt->attempt_value) }}{{ $isRacingRobot ? ' s' : '' }}
                            @endif
                        </span>
                        @else
                        <span class="muted" title="{{ __('ui.not_recorded') }}">—</span>
                        @endif
                    </td>
                    @endfor
                    <td><strong class="best-value">{{ $formatRankingValue($standing?->best_value) }}{{ $isRacingRobot && $standing?->best_value !== null ? ' s' : '' }}</strong></td>
                </tr>
                @empty
                <tr><td colspan="{{ $attemptLimit + 3 }}" class="empty">{{ __('ui.standings_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selector = document.querySelector('[data-ranking-round-selector]');
    if (!selector) return;
    const dialog = document.querySelector('[data-ranking-edit-modal]');
    const editForm = dialog?.querySelector('[data-ranking-edit-form]');
    const status = document.querySelector('[data-ranking-save-status]');
    const editTitleTemplate = @json(__('ui.ranking_edit_title', ['team' => '__TEAM__', 'round' => '__ROUND__']));
    const processingLabel = @json(__('ui.processing'));
    const requestFailedLabel = @json(__('ui.request_failed'));
    let statusTimer;

    const syncRound = () => {
        document.querySelectorAll('[data-ranking-round-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.round !== selector.value;
        });
    };

    const showStatus = (message, failed = false) => {
        if (!status) return;
        window.clearTimeout(statusTimer);
        status.textContent = message;
        status.classList.toggle('error', failed);
        status.hidden = false;
        statusTimer = window.setTimeout(() => { status.hidden = true; }, 3500);
    };

    const refreshRankingContent = async () => {
        const scrollY = window.scrollY;
        const response = await fetch(window.location.href, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error(requestFailedLabel);

        const replacementDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        const selectors = ['.ranking-entry-list', '[data-live-results]'];
        selectors.forEach((contentSelector) => {
            const current = document.querySelector(contentSelector);
            const replacement = replacementDocument.querySelector(contentSelector);
            if (current && replacement) current.replaceChildren(...replacement.cloneNode(true).childNodes);
        });
        syncRound();
        document.dispatchEvent(new CustomEvent('easykids:live-content-updated', { detail: { target: document } }));
        requestAnimationFrame(() => window.scrollTo({ top: scrollY, left: window.scrollX, behavior: 'auto' }));
    };

    const openEditModal = (trigger, restoreOldInput = false) => {
        if (!dialog || !editForm) return;
        editForm.reset();
        editForm.action = trigger.dataset.action;
        editForm.elements.attempt_number.value = trigger.dataset.round;
        editForm.elements.ranking_edit_participant.value = trigger.dataset.participant;
        dialog.querySelector('[data-ranking-edit-title]').textContent = editTitleTemplate
            .replace('__TEAM__', trigger.dataset.team)
            .replace('__ROUND__', trigger.dataset.round);

        ['attempt_value', 'manual_score', 'auto_score', 'attempt_time'].forEach((name) => {
            const input = editForm.elements[name];
            if (!input) return;
            const dataName = name.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
            const oldName = `old${dataName.charAt(0).toUpperCase()}${dataName.slice(1)}`;
            input.value = restoreOldInput && dialog.dataset[oldName] !== ''
                ? dialog.dataset[oldName]
                : trigger.dataset[dataName];
        });
        const valid = editForm.querySelector('input[type="checkbox"][name="is_valid"]');
        if (valid) valid.checked = restoreOldInput ? dialog.dataset.oldValid === '1' : trigger.dataset.valid === '1';

        dialog.showModal();
        requestAnimationFrame(() => editForm.querySelector('input[type="number"]')?.focus());
    };

    selector.addEventListener('change', syncRound);
    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-ranking-edit-trigger]') : null;
        if (trigger) openEditModal(trigger);
    });
    dialog?.querySelectorAll('[data-ranking-edit-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement && event.target.matches('[data-ranking-async-form]') ? event.target : null;
        if (!form) return;
        event.preventDefault();

        const submit = form.querySelector('button[type="submit"]');
        const originalLabel = submit?.textContent;
        if (submit) { submit.disabled = true; submit.textContent = processingLabel; }
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || payload.message || requestFailedLabel);
            }

            if (dialog?.open) dialog.close();
            await refreshRankingContent();
            showStatus(payload.message || @json(__('ui.attempt_saved', ['number' => '__NUMBER__'])).replace('__NUMBER__', form.elements.attempt_number.value));
        } catch (error) {
            showStatus(error instanceof Error ? error.message : requestFailedLabel, true);
        } finally {
            if (submit) { submit.disabled = false; submit.textContent = originalLabel; }
        }
    });
    syncRound();

    if (dialog?.dataset.reopenParticipant) {
        const round = @json((string) old('attempt_number'));
        if (round) { selector.value = round; syncRound(); }
        const trigger = document.querySelector(`[data-ranking-edit-trigger][data-participant="${CSS.escape(dialog.dataset.reopenParticipant)}"][data-round="${CSS.escape(round)}"]`);
        if (trigger) openEditModal(trigger, true);
    }
});
</script>
@endpush
