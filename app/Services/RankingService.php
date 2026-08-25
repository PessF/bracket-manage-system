<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\RankingAttempt;
use App\Models\Standing;
use App\Models\Tournament;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RankingService
{
    public function saveAttempt(Tournament $tournament, Participant $participant, int $number, string|int|float|null $value, bool $valid = true): RankingAttempt
    {
        return DB::transaction(function () use ($tournament, $participant, $number, $value, $valid): RankingAttempt {
            /** @var Tournament $locked */
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);

            if ($locked->format !== TournamentFormat::RANKING || $locked->status !== TournamentStatus::LIVE) {
                throw new DomainException(__('ui.ranking_live_only'));
            }

            if ($participant->tournament_id !== $locked->id) {
                throw new DomainException(__('ui.participant_wrong_tournament'));
            }

            $limit = max(1, min(20, (int) ($locked->ranking_config['attempts'] ?? 2)));

            if ($number < 1 || $number > $limit) {
                throw new InvalidArgumentException(__('ui.attempt_number_range', ['limit' => $limit]));
            }

            $normalized = $value === null || $value === '' ? null : $this->normalizeValue($value);

            /** @var RankingAttempt $attempt */
            $attempt = RankingAttempt::query()->updateOrCreate(
                ['tournament_id' => $locked->id, 'participant_id' => $participant->id, 'attempt_number' => $number],
                ['attempt_value' => $normalized, 'is_valid' => $valid, 'synced_at' => now()],
            );

            $this->recompute($locked);

            return $attempt->refresh();
        }, 3);
    }

    public function recompute(Tournament $tournament): void
    {
        $comparator = (string) ($tournament->ranking_config['comparator'] ?? 'BEST_SCORE_HIGHER');
        $rows = [];

        foreach ($tournament->participants()->get() as $participant) {
            $values = $participant->rankingAttempts()
                ->where('is_valid', true)
                ->whereNotNull('attempt_value')
                ->pluck('attempt_value')
                ->map(fn ($value): float => (float) $value)
                ->all();
            $best = $values === [] ? null : ($comparator === 'BEST_TIME_LOWER' ? min($values) : max($values));
            $rows[] = ['participant_id' => (string) $participant->id, 'best' => $best];
        }

        usort($rows, function (array $a, array $b) use ($comparator): int {
            if ($a['best'] === null || $b['best'] === null) {
                return ($a['best'] === null ? 1 : 0) <=> ($b['best'] === null ? 1 : 0);
            }

            return ($comparator === 'BEST_TIME_LOWER' ? $a['best'] <=> $b['best'] : $b['best'] <=> $a['best'])
                ?: strcmp($a['participant_id'], $b['participant_id']);
        });

        $lastValue = null;
        $lastRank = 0;

        foreach ($rows as $index => $row) {
            $rank = 0;

            if ($row['best'] !== null) {
                $rank = $lastValue !== null && bccomp((string) $row['best'], (string) $lastValue, 6) === 0
                    ? $lastRank
                    : $index + 1;
                $lastValue = $row['best'];
                $lastRank = $rank;
            }

            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $row['participant_id']],
                [
                    'rank_number' => $rank, 'best_value' => $row['best'],
                    'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                    'score_for' => 0, 'score_against' => 0, 'score_difference' => 0, 'points' => 0,
                    'format_data' => ['comparator' => $comparator], 'synced_at' => now(),
                ],
            );
        }
    }

    private function normalizeValue(string|int|float $value): string
    {
        $text = trim((string) $value);

        if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $text)) {
            throw new InvalidArgumentException(__('ui.attempt_value_invalid'));
        }

        return bcadd($text, '0', 6);
    }
}
