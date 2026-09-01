<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RankingType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\RankingAttempt;
use App\Models\Standing;
use App\Models\Tournament;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RankingService
{
    public function saveAttempt(
        Tournament $tournament,
        Participant $participant,
        int $number,
        string|int|float|null $value,
        bool $valid = true,
        string|int|float|null $manualScore = null,
        string|int|float|null $autoScore = null,
        string|int|float|null $attemptTime = null,
    ): RankingAttempt {
        return DB::transaction(function () use ($tournament, $participant, $number, $value, $valid, $manualScore, $autoScore, $attemptTime): RankingAttempt {
            /** @var Tournament $locked */
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);

            if ($locked->format !== TournamentFormat::RANKING || $locked->status !== TournamentStatus::LIVE) {
                throw new DomainException(__('ui.ranking_live_only'));
            }

            if ($participant->tournament_id !== $locked->id) {
                throw new DomainException(__('ui.participant_wrong_tournament'));
            }

            $limit = $locked->rankingAttemptLimit();

            if ($number < 1 || $number > $limit) {
                throw new InvalidArgumentException(__('ui.attempt_number_range', ['limit' => $limit]));
            }

            $attributes = $this->normalizeAttempt($locked, $value, $manualScore, $autoScore, $attemptTime);

            /** @var RankingAttempt $attempt */
            $attempt = RankingAttempt::query()->updateOrCreate(
                ['tournament_id' => $locked->id, 'participant_id' => $participant->id, 'attempt_number' => $number],
                $attributes + ['is_valid' => $valid, 'synced_at' => now()],
            );

            $this->recompute($locked);

            return $attempt->refresh();
        }, 3);
    }

    public function recompute(Tournament $tournament): void
    {
        $configuredType = RankingType::tryFrom((string) ($tournament->ranking_config['type'] ?? ''));
        $comparator = (string) ($tournament->ranking_config['comparator'] ?? 'BEST_SCORE_HIGHER');
        $rows = [];

        foreach ($tournament->participants()->get() as $participant) {
            $attempts = $participant->rankingAttempts()
                ->where('is_valid', true)
                ->whereNotNull('attempt_value')
                ->orderBy('attempt_number')
                ->get();
            $bestAttempt = $this->bestAttempt($attempts, $configuredType, $comparator);
            $best = $bestAttempt?->attempt_value;
            $secondary = $configuredType === RankingType::DRONE_MISSION ? $bestAttempt?->attempt_time : null;

            $rows[] = [
                'participant_id' => (string) $participant->id,
                'best' => $best !== null ? (string) $best : null,
                'secondary' => $secondary !== null ? (string) $secondary : null,
                'attempt' => $bestAttempt,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b, $configuredType, $comparator));

        $lastKey = null;
        $lastRank = 0;

        foreach ($rows as $index => $row) {
            $rank = 0;

            if ($row['best'] !== null) {
                $key = $this->rankKey($row, $configuredType);
                $rank = $lastKey !== null && $key === $lastKey ? $lastRank : $index + 1;
                $lastKey = $key;
                $lastRank = $rank;
            }

            /** @var RankingAttempt|null $bestAttempt */
            $bestAttempt = $row['attempt'];
            $formatData = [
                'type' => $configuredType?->value,
                'comparator' => $configuredType === RankingType::DRONE_MISSION
                    ? 'BEST_SCORE_HIGHER_THEN_TIME_LOWER'
                    : $comparator,
                'best_attempt_number' => $bestAttempt?->attempt_number,
            ];

            if ($configuredType === RankingType::DRONE_MISSION && $bestAttempt !== null) {
                $formatData += [
                    'manual_score' => (string) $bestAttempt->manual_score,
                    'auto_score' => (string) $bestAttempt->auto_score,
                    'attempt_time' => (string) $bestAttempt->attempt_time,
                ];
            } elseif ($configuredType === RankingType::RACING_ROBOT && $bestAttempt !== null) {
                $formatData['attempt_time'] = (string) ($bestAttempt->attempt_time ?? $bestAttempt->attempt_value);
            }

            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $row['participant_id']],
                [
                    'rank_number' => $rank,
                    'best_value' => $row['best'],
                    'played' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'score_for' => $row['best'] ?? 0,
                    'score_against' => 0,
                    'score_difference' => 0,
                    'points' => 0,
                    'format_data' => $formatData,
                    'synced_at' => now(),
                ],
            );
        }
    }

    /** @return array<string, string|null> */
    private function normalizeAttempt(
        Tournament $tournament,
        string|int|float|null $value,
        string|int|float|null $manualScore,
        string|int|float|null $autoScore,
        string|int|float|null $attemptTime,
    ): array {
        $configuredType = RankingType::tryFrom((string) ($tournament->ranking_config['type'] ?? ''));

        if ($configuredType === RankingType::RACING_ROBOT) {
            $time = $this->normalizeRequired($attemptTime ?? $value, 'ui.racing_time_required');

            return ['attempt_value' => $time, 'manual_score' => null, 'auto_score' => null, 'attempt_time' => $time];
        }

        if ($configuredType === RankingType::DRONE_MISSION) {
            $manual = $this->normalizeDroneScore($manualScore);
            $auto = $this->normalizeDroneScore($autoScore);
            $time = $this->normalizeRequired($attemptTime, 'ui.drone_fields_required');

            return [
                'attempt_value' => bcadd($manual, $auto, 2),
                'manual_score' => $manual,
                'auto_score' => $auto,
                'attempt_time' => $time,
            ];
        }

        return [
            'attempt_value' => $value === null || $value === '' ? null : $this->normalizeValue($value),
            'manual_score' => null,
            'auto_score' => null,
            'attempt_time' => null,
        ];
    }

    private function normalizeRequired(string|int|float|null $value, string $messageKey): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(__($messageKey));
        }

        return $this->normalizeValue($value);
    }

    private function normalizeDroneScore(string|int|float|null $value): string
    {
        $score = $this->normalizeRequired($value, 'ui.drone_fields_required');

        if (bccomp($score, '50', 2) > 0) {
            throw new InvalidArgumentException(__('ui.drone_score_range'));
        }

        return $score;
    }

    private function bestAttempt(Collection $attempts, ?RankingType $type, string $comparator): ?RankingAttempt
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        return $attempts->sort(function (RankingAttempt $a, RankingAttempt $b) use ($type, $comparator): int {
            if ($type === RankingType::DRONE_MISSION) {
                return bccomp((string) $b->attempt_value, (string) $a->attempt_value, 2)
                    ?: bccomp((string) $a->attempt_time, (string) $b->attempt_time, 2)
                    ?: $a->attempt_number <=> $b->attempt_number;
            }

            $valueOrder = $comparator === 'BEST_TIME_LOWER'
                ? bccomp((string) $a->attempt_value, (string) $b->attempt_value, 2)
                : bccomp((string) $b->attempt_value, (string) $a->attempt_value, 2);

            return $valueOrder ?: $a->attempt_number <=> $b->attempt_number;
        })->first();
    }

    private function compareRows(array $a, array $b, ?RankingType $type, string $comparator): int
    {
        if ($a['best'] === null || $b['best'] === null) {
            return (($a['best'] === null ? 1 : 0) <=> ($b['best'] === null ? 1 : 0))
                ?: strcmp($a['participant_id'], $b['participant_id']);
        }

        if ($type === RankingType::DRONE_MISSION) {
            return bccomp($b['best'], $a['best'], 2)
                ?: bccomp((string) $a['secondary'], (string) $b['secondary'], 2)
                ?: strcmp($a['participant_id'], $b['participant_id']);
        }

        return ($comparator === 'BEST_TIME_LOWER'
            ? bccomp($a['best'], $b['best'], 2)
            : bccomp($b['best'], $a['best'], 2))
            ?: strcmp($a['participant_id'], $b['participant_id']);
    }

    private function rankKey(array $row, ?RankingType $type): string
    {
        $primary = bcadd((string) $row['best'], '0', 2);

        return $type === RankingType::DRONE_MISSION
            ? $primary.'|'.bcadd((string) $row['secondary'], '0', 2)
            : $primary;
    }

    private function normalizeValue(string|int|float $value): string
    {
        $text = trim((string) $value);

        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $text)) {
            throw new InvalidArgumentException(__('ui.attempt_value_invalid'));
        }

        return bcadd($text, '0', 2);
    }
}
