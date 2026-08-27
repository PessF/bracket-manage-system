<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BracketType;
use App\Enums\MatchSlot;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Enums\TournamentStructure;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class MatchResultService
{
    public function __construct(
        private readonly RoundRobinStandingsService $roundRobinStandings,
        private readonly TournamentLifecycleService $lifecycle,
    ) {}

    /**
     * Confirm a result and atomically propagate its winner and loser.
     *
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ModelNotFoundException
     */
    public function confirm(
        TournamentMatch|string $match,
        string|int|float $scoreA,
        string|int|float $scoreB,
    ): TournamentMatch {
        $matchId = $match instanceof TournamentMatch
            ? (string) $match->getKey()
            : $match;

        if ($matchId === '') {
            throw new InvalidArgumentException(__('ui.match_id_required'));
        }

        $normalizedScoreA = $this->normalizeScore($scoreA, 'score_a');
        $normalizedScoreB = $this->normalizeScore($scoreB, 'score_b');

        return DB::transaction(function () use (
            $matchId,
            $normalizedScoreA,
            $normalizedScoreB,
        ): TournamentMatch {
            /** @var TournamentMatch $currentMatch */
            $currentMatch = TournamentMatch::query()
                ->lockForUpdate()
                ->findOrFail($matchId);

            $currentMatch->load('tournament');

            if ($currentMatch->tournament->status !== TournamentStatus::LIVE) {
                throw new DomainException(__('ui.result_live_only'));
            }

            if (! in_array($currentMatch->status, [MatchStatus::READY, MatchStatus::LIVE, MatchStatus::FINISHED], true)) {
                throw new DomainException(__('ui.match_status_invalid'));
            }

            $isCorrection = $currentMatch->status === MatchStatus::FINISHED;
            $previousWinnerId = $currentMatch->winner_id;
            $previousLoserId = $currentMatch->loser_id;

            if ($isCorrection && $currentMatch->stage_group_id !== null && $this->playoffMatchesExist($currentMatch)) {
                throw new DomainException(__('ui.group_result_locked_after_playoff'));
            }

            if ($currentMatch->is_bye) {
                throw new DomainException(__('ui.bye_result_invalid'));
            }

            if ($currentMatch->participant_a_id === null || $currentMatch->participant_b_id === null) {
                throw new DomainException(__('ui.participants_required_for_result'));
            }

            if ($currentMatch->participant_a_id === $currentMatch->participant_b_id) {
                throw new DomainException(__('ui.self_match_invalid'));
            }

            $this->assertParticipantsBelongToTournament($currentMatch);

            $comparison = bccomp($normalizedScoreA, $normalizedScoreB, 6);

            if ($comparison === 0 && $currentMatch->tournament->format->isElimination()) {
                throw new DomainException(__('ui.elimination_tie_invalid'));
            }

            $winnerId = match (true) {
                $comparison > 0 => $currentMatch->participant_a_id,
                $comparison < 0 => $currentMatch->participant_b_id,
                default => null,
            };

            $loserId = match (true) {
                $comparison > 0 => $currentMatch->participant_b_id,
                $comparison < 0 => $currentMatch->participant_a_id,
                default => null,
            };

            $now = now();

            $currentMatch->fill([
                'score_a' => $normalizedScoreA,
                'score_b' => $normalizedScoreB,
                'winner_id' => $winnerId,
                'loser_id' => $loserId,
                'status' => MatchStatus::FINISHED,
                'started_at' => $currentMatch->started_at ?? $now,
                'finished_at' => $currentMatch->finished_at ?? $now,
                'synced_at' => $now,
            ])->save();

            if ($isCorrection && ($previousWinnerId !== $winnerId || $previousLoserId !== $loserId)) {
                $this->replacePropagation(
                    sourceMatch: $currentMatch,
                    previousParticipantId: $previousWinnerId,
                    participantId: $winnerId,
                    nextMatchId: $currentMatch->winner_next_match_id,
                    nextSlot: $currentMatch->winner_next_slot,
                    outcome: 'winner',
                );
                $this->replacePropagation(
                    sourceMatch: $currentMatch,
                    previousParticipantId: $previousLoserId,
                    participantId: $loserId,
                    nextMatchId: $currentMatch->loser_next_match_id,
                    nextSlot: $currentMatch->loser_next_slot,
                    outcome: 'loser',
                );
            } elseif (! $isCorrection && $winnerId !== null) {
                $this->propagate(
                    sourceMatch: $currentMatch,
                    participantId: $winnerId,
                    nextMatchId: $currentMatch->winner_next_match_id,
                    nextSlot: $currentMatch->winner_next_slot,
                    outcome: 'winner',
                );
            }

            if (! $isCorrection && $loserId !== null) {
                $this->propagate(
                    sourceMatch: $currentMatch,
                    participantId: $loserId,
                    nextMatchId: $currentMatch->loser_next_match_id,
                    nextSlot: $currentMatch->loser_next_slot,
                    outcome: 'loser',
                );
            }

            if (
                $currentMatch->tournament->format === TournamentFormat::DOUBLE_ELIMINATION
                && (int) ($currentMatch->tournament->double_elimination_config['grand_final_matches'] ?? 2) === 2
            ) {
                $this->synchronizeGrandFinalReset($currentMatch, $winnerId);
            }

            if ($currentMatch->tournament->format === TournamentFormat::ROUND_ROBIN) {
                $this->roundRobinStandings->recompute($currentMatch->tournament);
            }

            if ($currentMatch->tournament->structure === TournamentStructure::ADVANCED && $currentMatch->stage_group_id !== null) {
                $this->createPlayoffWhenReady($currentMatch);
            }

            $this->startNextReadyMatch($currentMatch->tournament);

            return $currentMatch->refresh()->load([
                'winner',
                'loser',
                'winnerNextMatch',
                'loserNextMatch',
            ]);
        }, 3);
    }

    private function startNextReadyMatch(Tournament $tournament): void
    {
        if ($tournament->matches()->where('status', MatchStatus::LIVE)->exists()) {
            return;
        }

        $match = $tournament->matches()
            ->where('status', MatchStatus::READY)
            ->where('is_bye', false)
            ->whereNotNull('participant_a_id')
            ->whereNotNull('participant_b_id')
            ->orderBy('match_number')
            ->lockForUpdate()
            ->first();

        if ($match !== null) {
            $match->forceFill(['status' => MatchStatus::LIVE, 'started_at' => now(), 'synced_at' => now()])->save();
        }
    }

    private function createPlayoffWhenReady(TournamentMatch $match): void
    {
        try {
            $this->lifecycle->createAdvancedPlayoff($match->tournament);
        } catch (DomainException $exception) {
            if (! in_array($exception->getMessage(), [
                __('ui.advanced_group_stage_incomplete'),
                __('ui.advanced_playoff_exists'),
            ], true)) {
                throw $exception;
            }

            // Group-stage results can be saved one by one. Until every group
            // match is complete, Playoff creation should simply wait.
        }
    }

    private function playoffMatchesExist(TournamentMatch $match): bool
    {
        return $match->tournament
            ->stages()
            ->where('stage_type', StageType::PLAYOFF)
            ->whereHas('matches', function ($query): void {
                $query->whereNotNull('participant_a_id')->orWhereNotNull('participant_b_id');
            })
            ->exists();
    }

    private function assertParticipantsBelongToTournament(TournamentMatch $match): void
    {
        $participantCount = Participant::query()
            ->where('tournament_id', $match->tournament_id)
            ->whereIn('id', [$match->participant_a_id, $match->participant_b_id])
            ->count();

        if ($participantCount !== 2) {
            throw new DomainException(__('ui.match_participants_wrong_tournament'));
        }
    }

    private function propagate(
        TournamentMatch $sourceMatch,
        string $participantId,
        ?string $nextMatchId,
        ?MatchSlot $nextSlot,
        string $outcome,
    ): void {
        if ($nextMatchId === null) {
            return;
        }

        if ($nextSlot === null) {
            throw new LogicException(__('ui.destination_slot_missing', ['outcome' => __('ui.outcome_labels.'.$outcome)]));
        }

        /** @var TournamentMatch $nextMatch */
        $nextMatch = TournamentMatch::query()
            ->lockForUpdate()
            ->findOrFail($nextMatchId);

        if ($nextMatch->tournament_id !== $sourceMatch->tournament_id) {
            throw new LogicException(__('ui.destination_wrong_tournament', ['outcome' => __('ui.outcome_labels.'.$outcome)]));
        }

        if (in_array($nextMatch->status, [MatchStatus::LIVE, MatchStatus::FINISHED], true)) {
            throw new DomainException(__('ui.destination_status_invalid', ['status' => __('ui.match_status_labels.'.$nextMatch->status->value)]));
        }

        $participantColumn = $nextSlot === MatchSlot::A
            ? 'participant_a_id'
            : 'participant_b_id';

        $existingParticipantId = $nextMatch->getAttribute($participantColumn);

        if ($existingParticipantId !== null && $existingParticipantId !== $participantId) {
            throw new LogicException(
                __('ui.destination_occupied', ['outcome' => __('ui.outcome_labels.'.$outcome)]),
            );
        }

        $nextMatch->setAttribute($participantColumn, $participantId);
        $labelColumn = $nextSlot === MatchSlot::A ? 'participant_a_label' : 'participant_b_label';
        $nextMatch->setAttribute(
            $labelColumn,
            Participant::query()->whereKey($participantId)->value('team_name') ?? 'Unknown participant',
        );

        if (
            $nextMatch->participant_a_id !== null
            && $nextMatch->participant_b_id !== null
            && $nextMatch->status === MatchStatus::PENDING
        ) {
            $nextMatch->status = MatchStatus::READY;
        }

        $nextMatch->synced_at = now();
        $nextMatch->save();
    }

    private function replacePropagation(
        TournamentMatch $sourceMatch,
        ?string $previousParticipantId,
        ?string $participantId,
        ?string $nextMatchId,
        ?MatchSlot $nextSlot,
        string $outcome,
    ): void {
        if ($nextMatchId === null || $previousParticipantId === $participantId) {
            return;
        }

        if ($nextSlot === null) {
            throw new LogicException(__('ui.destination_slot_missing', ['outcome' => __('ui.outcome_labels.'.$outcome)]));
        }

        /** @var TournamentMatch $nextMatch */
        $nextMatch = TournamentMatch::query()->lockForUpdate()->findOrFail($nextMatchId);

        if ($nextMatch->tournament_id !== $sourceMatch->tournament_id) {
            throw new LogicException(__('ui.destination_wrong_tournament', ['outcome' => __('ui.outcome_labels.'.$outcome)]));
        }

        if (in_array($nextMatch->status, [MatchStatus::LIVE, MatchStatus::FINISHED], true)) {
            throw new DomainException(__('ui.score_correction_next_match_started', ['number' => $nextMatch->match_number]));
        }

        $participantColumn = $nextSlot === MatchSlot::A ? 'participant_a_id' : 'participant_b_id';
        $labelColumn = $nextSlot === MatchSlot::A ? 'participant_a_label' : 'participant_b_label';
        $existingParticipantId = $nextMatch->getAttribute($participantColumn);

        if ($existingParticipantId !== $previousParticipantId && $existingParticipantId !== $participantId) {
            throw new LogicException(__('ui.destination_occupied', ['outcome' => __('ui.outcome_labels.'.$outcome)]));
        }

        $nextMatch->setAttribute($participantColumn, $participantId);
        $nextMatch->setAttribute(
            $labelColumn,
            $participantId === null
                ? ($outcome === 'winner' ? 'Winner' : 'Loser').' of Match #'.$sourceMatch->match_number
                : (Participant::query()->whereKey($participantId)->value('team_name') ?? 'Unknown participant'),
        );
        $nextMatch->status = $nextMatch->participant_a_id !== null && $nextMatch->participant_b_id !== null
            ? MatchStatus::READY
            : MatchStatus::PENDING;
        $nextMatch->synced_at = now();
        $nextMatch->save();
    }

    private function synchronizeGrandFinalReset(TournamentMatch $match, ?string $winnerId): void
    {
        $firstGrandFinalId = TournamentMatch::query()
            ->where('tournament_id', $match->tournament_id)
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->orderBy('match_number')
            ->value('id');

        if (
            $match->bracket_type !== BracketType::GRAND_FINAL
            || $firstGrandFinalId !== $match->id
            || $winnerId === null
        ) {
            return;
        }

        $reset = TournamentMatch::query()
            ->where('tournament_id', $match->tournament_id)
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->whereKeyNot($match->id)
            ->lockForUpdate()
            ->first();

        if ($winnerId !== $match->participant_b_id) {
            if ($reset === null) {
                return;
            }

            if (in_array($reset->status, [MatchStatus::LIVE, MatchStatus::FINISHED], true)) {
                throw new DomainException(__('ui.score_correction_next_match_started', ['number' => $reset->match_number]));
            }

            $reset->delete();

            return;
        }

        if ($reset !== null) {
            return;
        }

        TournamentMatch::query()->create([
            'id' => (string) Str::uuid(),
            'tournament_id' => $match->tournament_id,
            'stage_id' => $match->stage_id,
            'match_number' => ((int) TournamentMatch::query()
                ->where('tournament_id', $match->tournament_id)
                ->max('match_number')) + 1,
            'bracket_type' => BracketType::GRAND_FINAL,
            'round_number' => $match->round_number + 1,
            'status' => MatchStatus::READY,
            'is_bye' => false,
            'participant_a_id' => $match->participant_a_id,
            'participant_a_label' => $match->participant_a_label,
            'participant_b_id' => $match->participant_b_id,
            'participant_b_label' => $match->participant_b_label,
            'synced_at' => now(),
        ]);
    }

    private function normalizeScore(string|int|float $score, string $field): string
    {
        $value = trim((string) $score);

        if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $value)) {
            throw new InvalidArgumentException(
                __('ui.score_invalid', ['field' => __('ui.score_field_labels.'.$field)]),
            );
        }

        return bcadd($value, '0', 6);
    }
}
