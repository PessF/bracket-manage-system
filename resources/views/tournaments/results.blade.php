@extends('layouts.app')
@section('title', __('ui.results').' · '.$tournament->name)

@php
    $isRanking = $tournament->format === App\Enums\TournamentFormat::RANKING;
    $isDoubleElimination = $tournament->format === App\Enums\TournamentFormat::DOUBLE_ELIMINATION;
    $attemptLimit = max(1, min(20, (int) ($tournament->ranking_config['attempts'] ?? 2)));
    $formatRankingValue = fn ($value): string => $value !== null ? number_format((float) $value, 2, '.', '') : '—';
    $formatMatchScore = function ($value): string {
        $formatted = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    };
    $attemptsByParticipant = $participants->mapWithKeys(fn ($participant) => [
        (string) $participant->id => $participant->rankingAttempts->keyBy('attempt_number'),
    ]);
    $standingsByParticipant = $standings->keyBy(fn ($standing) => (string) ($standing->participant_id ?? $standing->participant?->id ?? ''));
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
    <h2>{{ __('ui.record_attempts') }}</h2>
    <div class="ranking-entry-list">
        @foreach($participants as $participant)
        <form class="ranking-entry-row" method="post" action="{{ route('ranking.attempts.store', [$tournament, $participant]) }}">
            @csrf
            <div class="ranking-entry-team">
                <label>{{ $participant->team_name }}</label>
                <div class="muted">{{ $participant->rankingAttempts->count() }} {{ __('ui.saved') }}</div>
            </div>
            <div class="field">
                <label>{{ __('ui.attempt') }}</label>
                <input type="number" name="attempt_number" min="1" max="{{ $attemptLimit }}" required>
            </div>
            <div class="field">
                <label>{{ __('ui.value') }}</label>
                <input type="number" name="attempt_value" min="0" step="0.01" inputmode="decimal">
            </div>
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
<section class="card">
    <h2>{{ __('ui.standings') }}</h2>
    @if(!$isRanking)
    <p class="muted standings-rule">{{ __($isDoubleElimination ? 'ui.double_elimination_standings_rule' : 'ui.round_robin_standings_rule') }}</p>
    @endif
    <div class="table-wrap standings-wrap">
        <table class="standings-table">
            <thead>
                <tr>
                    <th>{{ __('ui.rank') }}</th>
                    <th>{{ __('ui.participant') }}</th>
                    @if($isRanking)
                    <th>{{ __('ui.best_value') }}</th>
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
                    <td><strong class="best-value">{{ $formatRankingValue($standing->best_value) }}</strong></td>
                    @else
                    <td>{{ $standing->played }}</td>
                    <td><strong>{{ $standing->wins }}</strong></td>
                    <td>{{ $standing->draws }}</td>
                    <td>{{ $standing->losses }}</td>
                    <td>{{ $formatMatchScore($standing->score_for) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $isRanking ? 3 : 7 }}" class="empty">{{ __('ui.standings_empty') }}</td></tr>
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
                    <th>{{ __('ui.attempt') }} {{ $attemptNumber }}</th>
                    @endfor
                    <th>{{ __('ui.best_value') }}</th>
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
                            {{ $formatRankingValue($attempt->attempt_value) }}
                        </span>
                        @else
                        <span class="muted" title="{{ __('ui.not_recorded') }}">—</span>
                        @endif
                    </td>
                    @endfor
                    <td><strong class="best-value">{{ $formatRankingValue($standing?->best_value) }}</strong></td>
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

@push('styles')
<style>
.standings-rule{margin:-8px 0 12px}
.ranking-entry-list{display:grid;gap:10px}
.ranking-entry-row{display:grid;grid-template-columns:minmax(180px,1.2fr) 110px minmax(140px,.7fr) 90px auto;gap:10px;align-items:end;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--soft)}
.ranking-entry-team label{display:block;font-weight:850}
.ranking-valid-field input[type="checkbox"]{width:22px;min-height:22px}
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
.attempt-value.invalid{border-color:rgb(151 161 176 / .22);background:rgb(151 161 176 / .08);color:#8290aa;text-decoration:line-through}
@media(max-width:920px){.ranking-entry-row{grid-template-columns:1fr 90px 120px 76px}.ranking-entry-row .btn{grid-column:1/-1;width:100%}}
@media(max-width:680px){.ranking-entry-row{grid-template-columns:1fr 1fr}.ranking-entry-team{grid-column:1/-1}.ranking-valid-field{align-self:center}.standings-table th:first-child,.standings-table td:first-child{position:sticky;left:0;z-index:2;width:48px;background:var(--card)}.standings-table th:nth-child(2),.standings-table td:nth-child(2){position:sticky;left:48px;z-index:2;max-width:150px;overflow:hidden;background:var(--card);text-overflow:ellipsis}.standings-table thead th:first-child,.standings-table thead th:nth-child(2){z-index:3;background:var(--card)}.standings-table th:nth-child(2),.standings-table td:nth-child(2){box-shadow:5px 0 7px -7px rgb(0 0 0 / .75)}}
</style>
@endpush
