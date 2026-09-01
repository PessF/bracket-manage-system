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

@push('styles')
<style>
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.standings-rule{margin:-8px 0 12px}
.ranking-round-selector{display:flex;align-items:center;justify-content:space-between;gap:24px;margin:18px 0;padding:18px 20px;border:1px solid rgb(103 216 245 / .42);border-radius:14px;background:linear-gradient(135deg,rgb(103 216 245 / .15),rgb(111 125 255 / .09));box-shadow:0 14px 34px rgb(0 0 0 / .16)}
.ranking-round-selector>div{display:grid;gap:4px}.ranking-round-selector strong{font-size:1.12rem}.ranking-round-selector span{color:var(--muted);font-size:.9rem}.ranking-round-selector select{width:min(100%,260px);min-height:54px;padding:0 46px 0 18px;border:1px solid rgb(103 216 245 / .55);border-radius:12px;background-color:rgb(9 18 30 / .96);color:#dff8ff;font-size:1.08rem;font-weight:900}
.ranking-save-status{position:fixed;right:22px;bottom:22px;z-index:1200;max-width:min(420px,calc(100vw - 32px));padding:12px 16px;border:1px solid rgb(73 207 155 / .5);border-radius:10px;background:rgb(16 68 51 / .97);color:#dfffee;font-weight:800;box-shadow:0 18px 42px rgb(0 0 0 / .38)}.ranking-save-status.error{border-color:rgb(255 115 115 / .55);background:rgb(91 30 38 / .98);color:#ffe8eb}
.ranking-entry-list{display:grid;gap:10px}
.ranking-entry-row{display:grid;grid-template-columns:minmax(180px,.75fr) minmax(0,2.25fr);gap:16px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--soft)}
.ranking-entry-team label{display:block;font-weight:850}
.ranking-round-panels{min-width:0}.ranking-new-result-form{display:grid;grid-template-columns:minmax(140px,1fr) 90px auto;gap:10px;align-items:end}.ranking-new-result-form.drone{grid-template-columns:repeat(3,minmax(105px,1fr)) 76px auto}.ranking-new-result-form .field{min-width:0;margin:0}.ranking-new-result-form :is(input,select){min-width:0}
.ranking-valid-field input[type="checkbox"]{width:22px;min-height:22px}
.ranking-saved-result{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:18px;min-height:64px;padding:10px 12px;border:1px solid rgb(73 207 155 / .32);border-radius:9px;background:linear-gradient(135deg,rgb(73 207 155 / .12),rgb(18 42 47 / .18))}.ranking-saved-result.invalid{border-color:rgb(151 161 176 / .25);background:rgb(151 161 176 / .07)}
.ranking-saved-heading{display:grid;gap:2px}.ranking-saved-heading span{color:var(--muted);font-size:.72rem;font-weight:850;text-transform:uppercase}.ranking-saved-heading strong{white-space:nowrap}.ranking-saved-values{display:flex;align-items:center;gap:9px;min-width:0}.ranking-saved-values>span{display:grid;min-width:86px;gap:2px;padding:6px 10px;border:1px solid rgb(103 216 245 / .18);border-radius:8px;background:rgb(7 18 29 / .34)}.ranking-saved-values small{color:var(--muted);font-size:.68rem}.ranking-saved-values strong{color:#dff8ff}.ranking-saved-values em{color:#ffadb6;font-size:.76rem;font-style:normal;font-weight:850}.ranking-edit-trigger{white-space:nowrap}
.ranking-edit-modal{width:min(520px,calc(100vw - 24px));max-height:calc(100dvh - 24px);margin:auto;padding:0;overflow:auto;border:1px solid rgb(103 216 245 / .32);border-radius:14px;background:linear-gradient(180deg,rgb(24 34 51 / .99),rgb(11 17 27 / .99));color:var(--ink);box-shadow:0 28px 80px rgb(0 0 0 / .6)}.ranking-edit-modal::backdrop{background:rgb(2 7 12 / .78);backdrop-filter:blur(3px)}
.ranking-edit-modal-head{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 18px;border-bottom:1px solid var(--line);background:rgb(20 29 43 / .98)}.ranking-edit-modal-head>div{display:grid;min-width:0;gap:3px}.ranking-edit-modal-head span{color:#8feaff;font-size:.7rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.ranking-edit-modal-head h2{margin:0;overflow-wrap:anywhere;font-size:1.1rem}.ranking-edit-modal-head>button{width:34px;min-width:34px;min-height:34px;padding:0;border:0;border-radius:7px;background:transparent;color:var(--muted);font-size:24px;cursor:pointer}.ranking-edit-modal-head>button:hover{background:var(--soft);color:var(--ink)}
.ranking-edit-modal-form{display:grid;gap:13px;padding:18px}.ranking-edit-valid{display:flex;align-items:center;gap:9px;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--soft);font-weight:800}.ranking-edit-valid input[type="checkbox"]{width:22px;min-height:22px}.ranking-edit-modal-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:3px}
.ranking-view-hero{display:flex;align-items:center;justify-content:space-between;gap:28px;margin:0 0 14px;padding:26px 28px;overflow:hidden;border:1px solid rgb(103 216 245 / .34);border-radius:16px;background:radial-gradient(circle at 85% 0,rgb(111 125 255 / .22),transparent 42%),linear-gradient(135deg,rgb(18 36 55 / .98),rgb(9 16 27 / .98));box-shadow:0 18px 48px rgb(0 0 0 / .22)}
.ranking-view-copy{display:grid;gap:7px}.ranking-kicker{color:#8feaff;font-size:.76rem;font-weight:950;letter-spacing:.13em;text-transform:uppercase}.ranking-view-copy h2{margin:0;font-size:clamp(1.45rem,3vw,2.2rem)}.ranking-view-copy p{max-width:720px;margin:0;color:var(--muted)}
.ranking-round-count{display:grid;flex:0 0 126px;place-items:center;min-height:104px;padding:12px;border:1px solid rgb(103 216 245 / .35);border-radius:14px;background:rgb(5 13 23 / .48);text-align:center}.ranking-round-count strong{color:#8feaff;font-size:2.35rem;line-height:1}.ranking-round-count span{color:var(--muted);font-size:.78rem;font-weight:800;text-transform:uppercase}
.ranking-leaders{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 14px}.ranking-leader{display:flex;align-items:center;gap:12px;min-height:82px;padding:14px 16px;border:1px solid var(--line);border-radius:13px;background:linear-gradient(145deg,rgb(18 29 45 / .96),rgb(10 17 28 / .96))}.ranking-leader-rank{display:grid;flex:0 0 42px;place-items:center;width:42px;height:42px;border-radius:50%;font-weight:950}.ranking-leader>div{display:grid;min-width:0;gap:3px}.ranking-leader>div strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ranking-leader>div span{color:var(--muted);font-size:.86rem}.ranking-leader.rank-1{border-color:rgb(240 190 114 / .45)}.ranking-leader.rank-1 .ranking-leader-rank{background:linear-gradient(135deg,#f0be72,#7a4b18);color:#211404}.ranking-leader.rank-2 .ranking-leader-rank{background:linear-gradient(135deg,#d8e3f2,#56677e);color:#101722}.ranking-leader.rank-3 .ranking-leader-rank{background:linear-gradient(135deg,#d49362,#693b22);color:#fff0e6}
.rank-medal{display:inline-grid;place-items:center;min-width:38px;height:28px;padding:0 9px;border-radius:999px;font-weight:950}
.rank-medal.rank-1{border:1px solid rgb(240 190 114 / .66);background:linear-gradient(135deg,#f0be72,#7a4b18);color:#211404;box-shadow:0 0 18px rgb(240 190 114 / .22)}
.rank-medal.rank-2{border:1px solid rgb(191 204 222 / .60);background:linear-gradient(135deg,#d8e3f2,#56677e);color:#101722;box-shadow:0 0 16px rgb(191 204 222 / .16)}
.rank-medal.rank-3{border:1px solid rgb(202 132 88 / .60);background:linear-gradient(135deg,#d49362,#693b22);color:#fff0e6;box-shadow:0 0 16px rgb(202 132 88 / .16)}
.rank-row{position:relative}
.rank-row::after{content:"";position:absolute;inset:8px auto 8px 0;width:4px;border-radius:999px}
.rank-row.rank-1{background:linear-gradient(90deg,rgb(240 190 114 / .18),rgb(240 190 114 / .055) 34%,transparent)}
.rank-row.rank-1::after{background:#f0be72;box-shadow:0 0 16px rgb(240 190 114 / .34)}
.rank-row.rank-2{background:linear-gradient(90deg,rgb(191 204 222 / .15),rgb(191 204 222 / .045) 34%,transparent)}
.rank-row.rank-2::after{background:#c9d7e8;box-shadow:0 0 16px rgb(191 204 222 / .24)}
.rank-row.rank-3{background:linear-gradient(90deg,rgb(202 132 88 / .16),rgb(202 132 88 / .05) 34%,transparent)}
.rank-row.rank-3::after{background:#d49362;box-shadow:0 0 16px rgb(202 132 88 / .25)}
.unranked-row{opacity:.72}
.unranked-row:hover{opacity:1;background:rgb(103 216 245 / .035)}
.best-value{display:inline-flex;align-items:center;justify-content:center;min-width:64px;min-height:30px;padding:2px 10px;border:1px solid rgb(103 216 245 / .36);border-radius:999px;background:linear-gradient(135deg,rgb(103 216 245 / .14),rgb(111 125 255 / .08));color:#8feaff;font-size:1.05em;box-shadow:0 0 16px rgb(103 216 245 / .08)}
.ranking-attempts-card{background:linear-gradient(180deg,rgb(18 28 45 / .96),rgb(10 15 25 / .98))}
.ranking-scroll-note{display:none;margin:-6px 0 12px;color:var(--muted);font-size:.78rem}.ranking-scroll-note::after{content:" →";color:#8feaff}.ranking-standings-wrap:focus-visible,.ranking-attempts-wrap:focus-visible{outline:2px solid #67d8f5;outline-offset:3px}
.ranking-attempts-table{border-collapse:separate;border-spacing:0 6px}
.ranking-attempts-table thead th{border-bottom:0;background:linear-gradient(180deg,rgb(20 35 55 / .92),rgb(12 20 32 / .92));color:#8feaff}
.ranking-attempts-table tbody tr{transition:background-color .14s,opacity .14s}
.ranking-attempts-table tbody tr:hover{background:rgb(103 216 245 / .045)}
.ranking-attempts-table th,.ranking-attempts-table td{text-align:center;white-space:nowrap}
.ranking-attempts-table th:nth-child(2),.ranking-attempts-table td:nth-child(2){text-align:left}
.ranking-attempts-table tbody td{background:rgb(15 24 38 / .52)}
.ranking-attempts-table tbody td:first-child{border-radius:8px 0 0 8px}
.ranking-attempts-table tbody td:last-child{border-radius:0 8px 8px 0}
.attempt-value{display:inline-flex;align-items:center;justify-content:center;min-width:58px;min-height:26px;padding:2px 8px;border:1px solid rgb(103 216 245 / .34);border-radius:999px;background:linear-gradient(135deg,rgb(103 216 245 / .14),rgb(25 82 105 / .28));color:#dff8ff;font-weight:850;box-shadow:inset 0 1px 0 rgb(255 255 255 / .06)}
.attempt-value:has(small){flex-direction:column;align-items:flex-start;border-radius:8px;line-height:1.25}.attempt-value small{color:var(--muted);font-size:10px;font-weight:650}
.attempt-value.invalid{border-color:rgb(151 161 176 / .22);background:rgb(151 161 176 / .08);color:#8290aa;text-decoration:line-through}
@media(max-width:1100px){
    .ranking-entry-row{grid-template-columns:1fr}.ranking-entry-team{display:flex;align-items:baseline;justify-content:space-between;gap:12px}.ranking-entry-team .muted{flex:0 0 auto}
    .ranking-new-result-form.drone{grid-template-columns:repeat(3,minmax(100px,1fr)) 76px}.ranking-new-result-form.drone .btn{grid-column:1/-1;width:100%}.ranking-saved-values{flex-wrap:wrap}
}
@media(max-width:820px){
    .ranking-round-selector{gap:16px;padding:15px 16px}.ranking-round-selector select{width:min(42vw,240px)}
    .ranking-new-result-form{grid-template-columns:minmax(0,1fr) 86px auto}.ranking-new-result-form.drone{grid-template-columns:repeat(2,minmax(0,1fr))}.ranking-new-result-form.drone .field:nth-of-type(3){grid-column:1/-1}.ranking-new-result-form .btn{min-height:44px}
    .ranking-saved-result{grid-template-columns:auto minmax(0,1fr)}.ranking-saved-result .ranking-edit-trigger{grid-column:1/-1;width:100%;min-height:44px}
    .ranking-leaders{grid-template-columns:repeat(3,minmax(0,1fr))}.ranking-leader{align-items:flex-start;flex-direction:column}.ranking-leader-rank{flex-basis:42px}
}
@media(max-width:680px){
    .ranking-entry-card{margin-right:-4px;margin-left:-4px;padding:16px 12px}.ranking-entry-card>h2{font-size:17px}.ranking-entry-row{gap:12px;padding:12px}.ranking-entry-team label{overflow-wrap:anywhere;font-size:1rem}
    .ranking-round-selector,.ranking-view-hero{align-items:stretch;flex-direction:column}.ranking-round-selector{gap:11px;margin:14px 0;padding:13px}.ranking-round-selector>div{gap:2px}.ranking-round-selector strong{font-size:1rem}.ranking-round-selector span{font-size:.78rem;line-height:1.4}.ranking-round-selector select{width:100%;min-height:50px;font-size:16px}
    .ranking-view-hero{gap:18px;padding:20px}.ranking-round-count{display:flex;justify-content:center;gap:10px;min-height:68px}.ranking-round-count strong{font-size:2rem}.ranking-round-count span{max-width:100px;text-align:left}
    .ranking-new-result-form,.ranking-new-result-form.drone{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}.ranking-new-result-form:not(.drone) .field:first-of-type,.ranking-new-result-form.drone .field:nth-of-type(3){grid-column:1/-1}.ranking-new-result-form :is(input,select){min-height:48px;font-size:16px}.ranking-valid-field{align-self:end}.ranking-new-result-form .btn{width:100%;min-height:48px}
    .ranking-saved-result{grid-template-columns:1fr;gap:12px}.ranking-saved-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.ranking-saved-values{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ranking-saved-values>span{min-width:0;padding:8px}.ranking-saved-result .ranking-edit-trigger{grid-column:auto;min-height:46px}
    .ranking-leaders{grid-template-columns:1fr}.ranking-leader{align-items:center;flex-direction:row;min-height:72px}.ranking-leader-rank{flex-basis:42px}
    .ranking-edit-modal{width:calc(100% - 16px);max-height:calc(100dvh - 16px);border-radius:12px}.ranking-edit-modal-head{padding:14px}.ranking-edit-modal-form{gap:11px;padding:14px}.ranking-edit-modal-form input{min-height:48px;font-size:16px}.ranking-edit-modal-actions{display:grid;grid-template-columns:1fr 1fr}.ranking-edit-modal-actions .btn{width:100%;min-height:46px}
    .ranking-save-status{right:max(10px,env(safe-area-inset-right));bottom:max(10px,env(safe-area-inset-bottom));left:max(10px,env(safe-area-inset-left));max-width:none;text-align:center}
    .ranking-standings-wrap{margin:0;padding:0;overflow:visible}.ranking-standings-wrap table,.ranking-standings-wrap tbody{display:block}.ranking-standings-wrap thead{display:none}.ranking-standings-wrap tbody{display:grid;gap:10px}.ranking-standings-wrap tbody tr{display:grid;grid-template-columns:72px minmax(0,1fr);overflow:hidden;padding:10px;border:1px solid var(--line);border-radius:11px;background:rgb(15 24 38 / .72)}.ranking-standings-wrap tbody td{position:static!important;display:grid;min-width:0;padding:8px;border:0;background:transparent!important;box-shadow:none!important;white-space:normal}.ranking-standings-wrap tbody td::before{content:attr(data-label);margin-bottom:2px;color:var(--muted);font-size:.65rem;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.ranking-standings-wrap tbody td:first-child{grid-column:1;grid-row:1}.ranking-standings-wrap tbody td:nth-child(2){grid-column:2;grid-row:1;align-content:center;overflow-wrap:anywhere;font-size:1rem;font-weight:850}.ranking-standings-wrap tbody td:nth-child(n+3){grid-column:1/-1;grid-template-columns:minmax(0,1fr) auto;align-items:center;border-top:1px solid var(--line);text-align:right}.ranking-standings-wrap tbody td:nth-child(n+3)::before{margin:0;text-align:left}.ranking-standings-wrap .best-value{justify-self:end}
    .ranking-standings-wrap .empty{grid-column:1/-1!important;text-align:center}
    .ranking-scroll-note{display:block}.ranking-attempts-wrap{margin-right:-12px;margin-left:-12px;padding-right:12px;padding-left:12px;scroll-snap-type:x proximity}.ranking-attempts-table th,.ranking-attempts-table td{scroll-snap-align:start}.ranking-attempts-table th:first-child,.ranking-attempts-table td:first-child{position:sticky;left:0;z-index:2;width:48px;background:var(--card)}.ranking-attempts-table th:nth-child(2),.ranking-attempts-table td:nth-child(2){position:sticky;left:48px;z-index:2;max-width:140px;overflow:hidden;background:var(--card);text-overflow:ellipsis}.ranking-attempts-table thead th:first-child,.ranking-attempts-table thead th:nth-child(2){z-index:3;background:var(--card)}.ranking-attempts-table th:nth-child(2),.ranking-attempts-table td:nth-child(2){box-shadow:5px 0 7px -7px rgb(0 0 0 / .75)}
}
@media(max-width:460px){
    .ranking-entry-team{align-items:flex-start;flex-direction:column;gap:1px}.ranking-new-result-form,.ranking-new-result-form.drone{grid-template-columns:1fr}.ranking-new-result-form .field,.ranking-new-result-form.drone .field:nth-of-type(3),.ranking-valid-field,.ranking-new-result-form .btn{grid-column:1}.ranking-valid-field{display:grid;grid-template-columns:1fr auto;align-items:center;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:rgb(7 18 29 / .25)}.ranking-valid-field label{margin:0}.ranking-saved-values{grid-template-columns:1fr}.ranking-edit-modal-actions{grid-template-columns:1fr}.ranking-standings-wrap tbody tr{grid-template-columns:62px minmax(0,1fr)}.ranking-attempts-table th:nth-child(2),.ranking-attempts-table td:nth-child(2){max-width:120px}
}
@media(max-height:560px) and (orientation:landscape){
    .ranking-edit-modal{width:min(760px,calc(100vw - 16px));max-height:calc(100dvh - 12px)}.ranking-edit-modal-head{padding:10px 14px}.ranking-edit-modal-form.drone{grid-template-columns:repeat(3,minmax(0,1fr));align-items:end}.ranking-edit-modal-form.drone .ranking-edit-valid,.ranking-edit-modal-form.drone .ranking-edit-modal-actions{grid-column:1/-1}.ranking-edit-modal-form.drone .ranking-edit-modal-actions{display:flex}.ranking-edit-modal-form.drone .field{margin:0}
}
</style>
@endpush
