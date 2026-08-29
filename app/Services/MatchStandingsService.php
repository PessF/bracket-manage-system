<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Standing;
use App\Models\Tournament;

class MatchStandingsService
{
    public const CALCULATION_VERSION = 4;

    public const RANKING_RULE = 'WIN_LOSS_DIFFERENCE_THEN_SCORE_FOR';

    public function recompute(Tournament $tournament): void
    {
        $rows = [];

        foreach ($tournament->participants()->get() as $participant) {
            $rows[(string) $participant->id] = [
                'participant_id' => (string) $participant->id,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'score_for' => '0.000000',
            ];
        }

        $matches = $tournament->matches()->where('status', MatchStatus::FINISHED)->get();

        foreach ($matches as $match) {
            $participantAId = (string) $match->participant_a_id;
            $participantBId = (string) $match->participant_b_id;

            if ($participantAId !== '' && isset($rows[$participantAId])) {
                $rows[$participantAId]['played']++;
                $rows[$participantAId]['score_for'] = bcadd(
                    $rows[$participantAId]['score_for'],
                    (string) ($match->score_a ?? '0'),
                    6,
                );
            }

            if ($participantBId !== '' && isset($rows[$participantBId])) {
                $rows[$participantBId]['played']++;
                $rows[$participantBId]['score_for'] = bcadd(
                    $rows[$participantBId]['score_for'],
                    (string) ($match->score_b ?? '0'),
                    6,
                );
            }

            if ($match->winner_id !== null && isset($rows[$match->winner_id])) {
                $rows[$match->winner_id]['wins']++;
            } elseif ($participantAId !== '' && $participantBId !== '') {
                $rows[$participantAId]['draws']++;
                $rows[$participantBId]['draws']++;
            }

            if ($match->loser_id !== null && isset($rows[$match->loser_id])) {
                $rows[$match->loser_id]['losses']++;
            }
        }

        usort($rows, fn (array $a, array $b): int => ($b['wins'] - $b['losses']) <=> ($a['wins'] - $a['losses'])
            ?: bccomp($b['score_for'], $a['score_for'], 6)
            ?: strcmp($a['participant_id'], $b['participant_id']));

        foreach ($rows as $index => $row) {
            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $row['participant_id']],
                [
                    'rank_number' => $index + 1,
                    'best_value' => null,
                    'played' => $row['played'],
                    'wins' => $row['wins'],
                    'draws' => $row['draws'],
                    'losses' => $row['losses'],
                    'score_for' => $row['score_for'],
                    'points' => $row['wins'] - $row['losses'],
                    'format_data' => [
                        'ranking' => self::RANKING_RULE,
                        'calculation_version' => self::CALCULATION_VERSION,
                    ],
                    'synced_at' => now(),
                ],
            );
        }
    }
}
