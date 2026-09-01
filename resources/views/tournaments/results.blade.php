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
            <option value="{{ $roundNumber }}">{{ __('ui.round_number', ['number' => $roundNumber]) }}</option>
            @endforeach
        </select>
    </div>
    <div class="ranking-entry-list">
        @foreach($participants as $participant)
        <form class="ranking-entry-row {{ $isDroneMission ? 'drone' : '' }}" method="post" action="{{ route('ranking.attempts.store', [$tournament, $participant]) }}">
            @csrf
            <input type="hidden" name="attempt_number" value="1" data-ranking-round-input>
            <div class="ranking-entry-team">
                <label>{{ $participant->team_name }}</label>
                <div class="muted">{{ $participant->rankingAttempts->count() }} {{ __('ui.saved') }}</div>
            </div>
            @if($isDroneMission)
            <div class="field">
                <label>{{ __('ui.manual_score') }}</label>
                <input type="number" name="manual_score" min="0" step="0.01" inputmode="decimal" required>
            </div>
            <div class="field"><label>{{ __('ui.auto_score') }}</label><input type="number" name="auto_score" min="0" step="0.01" inputmode="decimal" required></div>
            <div class="field"><label>{{ __('ui.time_seconds') }}</label><input type="number" name="attempt_time" min="0" step="0.01" inputmode="decimal" required></div>
            @else
            <div class="field"><label>{{ $isRacingRobot ? __('ui.time_seconds') : __('ui.value') }}</label><input type="number" name="attempt_value" min="0" step="0.01" inputmode="decimal" required></div>
            @endif
            <div class="field ranking-valid-field">
                <label>{{ __('ui.valid') }}</label>
                <input type="hidden" name="is_valid" value="0">
                <input type="checkbox" name="is_valid" value="1" checked>
            </div>
            <button class="btn small">{{ __('ui.save') }}</button>
        </form>
        @endforeach
    </div>
</section>
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
                {{ __('ui.total_score') }} {{ $formatRankingValue($leader->best_value) }} · {{ $formatRankingValue($leader->format_data['attempt_time'] ?? null) }}s
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
    <div class="table-wrap standings-wrap">
        <table class="standings-table">
            <thead>
                <tr>
                    <th>{{ __('ui.rank') }}</th>
                    <th>{{ __('ui.participant') }}</th>
                    @if($isRanking)
                        @if($isDroneMission)
                        <th>{{ __('ui.total_score') }}</th><th>{{ __('ui.manual_score') }}</th><th>{{ __('ui.auto_score') }}</th><th>{{ __('ui.time_seconds') }}</th>
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
                    <td>
                        @if($rank >= 1 && $rank <= 3)
                        <span class="rank-medal rank-{{ $rank }}">#{{ $rank }}</span>
                        @else
                        <strong>{{ $standing->rank_number ?: '—' }}</strong>
                        @endif
                    </td>
                    <td>{{ $standing->participant->team_name }}</td>
                    @if($isRanking)
                        @if($isDroneMission)
                        <td><strong class="best-value">{{ $formatRankingValue($standing->best_value) }}</strong></td>
                        <td>{{ $formatRankingValue($standing->format_data['manual_score'] ?? null) }}</td>
                        <td>{{ $formatRankingValue($standing->format_data['auto_score'] ?? null) }}</td>
                        <td>{{ $formatRankingValue($standing->format_data['attempt_time'] ?? null) }}</td>
                        @else
                        <td><strong class="best-value">{{ $formatRankingValue($standing->best_value) }}{{ $isRacingRobot && $standing->best_value !== null ? ' s' : '' }}</strong></td>
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
    <div class="table-wrap ranking-attempts-wrap">
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
                            <strong>{{ $formatRankingValue($attempt->attempt_value) }}</strong><small>M {{ $formatRankingValue($attempt->manual_score) }} · A {{ $formatRankingValue($attempt->auto_score) }} · {{ $formatRankingValue($attempt->attempt_time) }}s</small>
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

    const syncRound = () => {
        document.querySelectorAll('[data-ranking-round-input]').forEach((input) => {
            input.value = selector.value;
        });
    };

    selector.addEventListener('change', syncRound);
    syncRound();
});
</script>
@endpush

@push('styles')
<style>
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.standings-rule{margin:-8px 0 12px}
.ranking-round-selector{display:flex;align-items:center;justify-content:space-between;gap:24px;margin:18px 0;padding:18px 20px;border:1px solid rgb(103 216 245 / .42);border-radius:14px;background:linear-gradient(135deg,rgb(103 216 245 / .15),rgb(111 125 255 / .09));box-shadow:0 14px 34px rgb(0 0 0 / .16)}
.ranking-round-selector>div{display:grid;gap:4px}.ranking-round-selector strong{font-size:1.12rem}.ranking-round-selector span{color:var(--muted);font-size:.9rem}.ranking-round-selector select{width:min(100%,260px);min-height:54px;padding:0 46px 0 18px;border:1px solid rgb(103 216 245 / .55);border-radius:12px;background-color:rgb(9 18 30 / .96);color:#dff8ff;font-size:1.08rem;font-weight:900}
.ranking-entry-list{display:grid;gap:10px}
.ranking-entry-row{display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(140px,.7fr) 90px auto;gap:10px;align-items:end;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--soft)}
.ranking-entry-row.drone{grid-template-columns:minmax(160px,1.1fr) repeat(3,minmax(105px,.65fr)) 76px auto}
.ranking-entry-team label{display:block;font-weight:850}
.ranking-valid-field input[type="checkbox"]{width:22px;min-height:22px}
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
@media(max-width:1100px){.ranking-entry-row.drone{grid-template-columns:1fr repeat(3,minmax(100px,.7fr))}.ranking-entry-row.drone .ranking-valid-field,.ranking-entry-row.drone .btn{grid-column:auto}.ranking-entry-row.drone .btn{grid-column:1/-1;width:100%}}
@media(max-width:920px){.ranking-entry-row{grid-template-columns:1fr 120px 76px}.ranking-entry-row .btn{grid-column:1/-1;width:100%}.ranking-entry-row.drone{grid-template-columns:repeat(2,1fr)}.ranking-entry-row.drone .ranking-entry-team{grid-column:1/-1}.ranking-leaders{grid-template-columns:1fr}}
@media(max-width:680px){.ranking-round-selector,.ranking-view-hero{align-items:stretch;flex-direction:column}.ranking-round-selector select{width:100%}.ranking-round-count{display:flex;justify-content:center;gap:10px;min-height:74px}.ranking-round-count span{max-width:90px;text-align:left}.ranking-entry-row{grid-template-columns:1fr 1fr}.ranking-entry-team{grid-column:1/-1}.ranking-valid-field{align-self:center}.standings-table th:first-child,.standings-table td:first-child{position:sticky;left:0;z-index:2;width:48px;background:var(--card)}.standings-table th:nth-child(2),.standings-table td:nth-child(2){position:sticky;left:48px;z-index:2;max-width:150px;overflow:hidden;background:var(--card);text-overflow:ellipsis}.standings-table thead th:first-child,.standings-table thead th:nth-child(2){z-index:3;background:var(--card)}.standings-table th:nth-child(2),.standings-table td:nth-child(2){box-shadow:5px 0 7px -7px rgb(0 0 0 / .75)}}
</style>
@endpush
