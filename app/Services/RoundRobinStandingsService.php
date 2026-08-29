<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Standing;
use App\Models\Tournament;

class RoundRobinStandingsService
{
    public const CALCULATION_VERSION = 2;

    public function recompute(Tournament $tournament): void
    {
        $rows = [];

        foreach ($tournament->participants()->get() as $participant) {
            $rows[(string) $participant->id] = [
                'participant_id' => (string) $participant->id,
                'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                'score_for' => '0.000000', 'score_against' => '0.000000',
                'score_difference' => '0.000000',
            ];
        }

        $matches = $tournament->matches()->where('status', MatchStatus::FINISHED)->get();

        foreach ($matches as $match) {
            if (! isset($rows[$match->participant_a_id], $rows[$match->participant_b_id])) {
                continue;
            }

            $scoreA = (string) $match->score_a;
            $scoreB = (string) $match->score_b;
            $a = &$rows[$match->participant_a_id];
            $b = &$rows[$match->participant_b_id];
            $a['played']++;
            $b['played']++;
            $a['score_for'] = bcadd($a['score_for'], $scoreA, 6);
            $a['score_against'] = bcadd($a['score_against'], $scoreB, 6);
            $b['score_for'] = bcadd($b['score_for'], $scoreB, 6);
            $b['score_against'] = bcadd($b['score_against'], $scoreA, 6);

            $comparison = bccomp($scoreA, $scoreB, 6);

            if ($comparison > 0) {
                $a['wins']++;
                $b['losses']++;
            } elseif ($comparison < 0) {
                $b['wins']++;
                $a['losses']++;
            } else {
                $a['draws']++;
                $b['draws']++;
            }

            unset($a, $b);
        }

        foreach ($rows as &$row) {
            $row['score_difference'] = bcsub($row['score_for'], $row['score_against'], 6);
        }
        unset($row);

        usort($rows, fn (array $a, array $b): int => $b['wins'] <=> $a['wins']
            ?: ($b['draws'] <=> $a['draws'])
            ?: bccomp($b['score_difference'], $a['score_difference'], 6)
            ?: bccomp($b['score_for'], $a['score_for'], 6)
            ?: strcmp($a['participant_id'], $b['participant_id']));

        foreach ($rows as $index => $row) {
            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $row['participant_id']],
                [
                    'rank_number' => $index + 1,
                    'best_value' => null,
                    'played' => $row['played'], 'wins' => $row['wins'],
                    'draws' => $row['draws'], 'losses' => $row['losses'],
                    'score_for' => $row['score_for'],
                    'score_against' => $row['score_against'],
                    'score_difference' => $row['score_difference'],
                    // Retain the legacy column for API compatibility. It now
                    // represents the number of wins, not configurable points.
                    'points' => $row['wins'],
                    'format_data' => [
                        'ranking' => 'WINS_THEN_DRAWS_THEN_SCORE_DIFFERENCE',
                        'calculation_version' => self::CALCULATION_VERSION,
                    ],
                    'synced_at' => now(),
                ],
            );
        }
    }
}
