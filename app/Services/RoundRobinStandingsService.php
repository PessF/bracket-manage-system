<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Standing;
use App\Models\Tournament;

class RoundRobinStandingsService
{
    public function recompute(Tournament $tournament): void
    {
        $config = $tournament->round_robin_config ?? [];
        $winPoints = (int) ($config['win_points'] ?? 3);
        $drawPoints = (int) ($config['draw_points'] ?? 1);
        $lossPoints = (int) ($config['loss_points'] ?? 0);
        $rows = [];

        foreach ($tournament->participants()->get() as $participant) {
            $rows[(string) $participant->id] = [
                'participant_id' => (string) $participant->id,
                'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                'score_for' => 0.0, 'score_against' => 0.0, 'points' => 0,
            ];
        }

        $matches = $tournament->matches()->where('status', MatchStatus::FINISHED)->get();

        foreach ($matches as $match) {
            if (! isset($rows[$match->participant_a_id], $rows[$match->participant_b_id])) {
                continue;
            }

            $scoreA = (float) $match->score_a;
            $scoreB = (float) $match->score_b;
            $a = &$rows[$match->participant_a_id];
            $b = &$rows[$match->participant_b_id];
            $a['played']++;
            $b['played']++;
            $a['score_for'] += $scoreA;
            $a['score_against'] += $scoreB;
            $b['score_for'] += $scoreB;
            $b['score_against'] += $scoreA;

            if ($scoreA > $scoreB) {
                $a['wins']++;
                $a['points'] += $winPoints;
                $b['losses']++;
                $b['points'] += $lossPoints;
            } elseif ($scoreB > $scoreA) {
                $b['wins']++;
                $b['points'] += $winPoints;
                $a['losses']++;
                $a['points'] += $lossPoints;
            } else {
                $a['draws']++;
                $b['draws']++;
                $a['points'] += $drawPoints;
                $b['points'] += $drawPoints;
            }

            unset($a, $b);
        }

        usort($rows, fn (array $a, array $b): int => $b['points'] <=> $a['points']
            ?: (($b['score_for'] - $b['score_against']) <=> ($a['score_for'] - $a['score_against']))
            ?: ($b['score_for'] <=> $a['score_for'])
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
                    'score_difference' => $row['score_for'] - $row['score_against'],
                    'points' => $row['points'],
                    'format_data' => ['points' => ['win' => $winPoints, 'draw' => $drawPoints, 'loss' => $lossPoints]],
                    'synced_at' => now(),
                ],
            );
        }
    }
}
