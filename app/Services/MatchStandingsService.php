<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

class MatchStandingsService
{
    public const CALCULATION_VERSION = 5;

    public const RANKING_RULE = 'WIN_LOSS_DIFFERENCE_THEN_SCORE_FOR';

    public const DOUBLE_ELIMINATION_RANKING_RULE = 'BRACKET_PROGRESSION';

    private const UNREACHABLE_DISTANCE = 1_000_000;

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
                'seed_number' => $participant->seed_number,
            ];
        }

        $matches = $tournament->matches()->get();
        $finishedMatches = $matches->where('status', MatchStatus::FINISHED);

        foreach ($finishedMatches as $match) {
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

        if ($tournament->format === TournamentFormat::DOUBLE_ELIMINATION) {
            $rows = $this->rankDoubleElimination($rows, $matches);
        } else {
            usort($rows, fn (array $a, array $b): int => ($b['wins'] - $b['losses']) <=> ($a['wins'] - $a['losses'])
                ?: bccomp($b['score_for'], $a['score_for'], 6)
                ?: strcmp($a['participant_id'], $b['participant_id']));

            foreach ($rows as $index => &$row) {
                $row['rank_number'] = $index + 1;
                $row['points'] = $row['wins'] - $row['losses'];
                $row['format_data'] = [
                    'ranking' => self::RANKING_RULE,
                    'calculation_version' => self::CALCULATION_VERSION,
                ];
            }
            unset($row);
        }

        foreach ($rows as $row) {
            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $row['participant_id']],
                [
                    'rank_number' => $row['rank_number'],
                    'best_value' => null,
                    'played' => $row['played'],
                    'wins' => $row['wins'],
                    'draws' => $row['draws'],
                    'losses' => $row['losses'],
                    'score_for' => $row['score_for'],
                    'points' => $row['points'],
                    'format_data' => $row['format_data'],
                    'synced_at' => now(),
                ],
            );
        }
    }

    /**
     * Double-elimination placement is determined by the match graph, not by
     * aggregate wins or scores. A participant's second-loss match determines
     * their final placement, and equal graph depth produces a shared rank.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  Collection<int, TournamentMatch>  $matches
     * @return list<array<string, mixed>>
     */
    private function rankDoubleElimination(array $rows, Collection $matches): array
    {
        $matchesById = $matches->keyBy(fn (TournamentMatch $match): string => (string) $match->id);
        $distanceMemo = [];
        $grandFinals = $matches
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->sortBy('match_number')
            ->values();
        $decisiveFinal = $grandFinals->isNotEmpty()
            && $grandFinals->every(fn (TournamentMatch $match): bool => $match->status === MatchStatus::FINISHED)
                ? $grandFinals->last()
                : null;

        foreach ($rows as &$row) {
            $participantId = $row['participant_id'];
            $placementStatus = 'ACTIVE';
            $placementMatch = null;
            $distance = self::UNREACHABLE_DISTANCE;
            $sortGroup = 2;

            if ($decisiveFinal instanceof TournamentMatch && $decisiveFinal->winner_id === $participantId) {
                $placementStatus = 'CHAMPION';
                $placementMatch = $decisiveFinal;
                $distance = 0;
                $sortGroup = 4;
            } elseif ($decisiveFinal instanceof TournamentMatch && $decisiveFinal->loser_id === $participantId) {
                $placementStatus = 'RUNNER_UP';
                $placementMatch = $decisiveFinal;
                $distance = 0;
                $sortGroup = 3;
            } elseif ($row['losses'] >= 2) {
                $placementStatus = 'ELIMINATED';
                $placementMatch = $this->eliminationMatch(
                    $participantId,
                    $matches,
                    $matchesById,
                    $distanceMemo,
                );
                $distance = $placementMatch instanceof TournamentMatch
                    ? $this->distanceToGrandFinal($placementMatch, $matchesById, $distanceMemo)
                    : self::UNREACHABLE_DISTANCE;
                $sortGroup = 1;
            } else {
                $placementMatch = $this->currentBracketMatch(
                    $participantId,
                    $matches,
                    $matchesById,
                    $distanceMemo,
                );
                $distance = $placementMatch instanceof TournamentMatch
                    ? $this->distanceToGrandFinal($placementMatch, $matchesById, $distanceMemo)
                    : self::UNREACHABLE_DISTANCE;
            }

            $row['_sort_group'] = $sortGroup;
            $row['_distance'] = $distance;
            $row['_placement_match_number'] = $placementMatch?->match_number ?? PHP_INT_MAX;
            $row['_rank_signature'] = $placementStatus === 'ACTIVE'
                ? "{$placementStatus}:{$distance}:{$row['losses']}"
                : "{$placementStatus}:{$distance}";
            $row['points'] = 0;
            $row['format_data'] = array_filter([
                'ranking' => self::DOUBLE_ELIMINATION_RANKING_RULE,
                'calculation_version' => self::CALCULATION_VERSION,
                'placement_status' => $placementStatus,
                'bracket_distance_to_final' => $distance === self::UNREACHABLE_DISTANCE ? null : $distance,
                'placement_match_id' => $placementMatch?->id,
                'placement_match_number' => $placementMatch?->match_number,
                'placement_bracket' => $placementMatch?->bracket_type->value,
                'placement_round' => $placementMatch?->round_number,
            ], fn (mixed $value): bool => $value !== null);
        }
        unset($row);

        $ranked = array_values($rows);
        usort($ranked, fn (array $a, array $b): int => $b['_sort_group'] <=> $a['_sort_group']
            ?: $a['_distance'] <=> $b['_distance']
            ?: $a['losses'] <=> $b['losses']
            ?: $b['_placement_match_number'] <=> $a['_placement_match_number']
            ?: ($a['seed_number'] ?? PHP_INT_MAX) <=> ($b['seed_number'] ?? PHP_INT_MAX)
            ?: strcmp($a['participant_id'], $b['participant_id']));

        $previousSignature = null;
        $rank = 0;

        foreach ($ranked as $index => &$row) {
            if ($row['_rank_signature'] !== $previousSignature) {
                $rank = $index + 1;
                $previousSignature = $row['_rank_signature'];
            }

            $row['rank_number'] = $rank;
        }
        unset($row);

        return $ranked;
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @param  Collection<string, TournamentMatch>  $matchesById
     * @param  array<string, int>  $distanceMemo
     */
    private function eliminationMatch(
        string $participantId,
        Collection $matches,
        Collection $matchesById,
        array &$distanceMemo,
    ): ?TournamentMatch {
        $losses = $matches
            ->filter(fn (TournamentMatch $match): bool => $match->status === MatchStatus::FINISHED
                && $match->loser_id === $participantId);
        $terminalLosses = $losses->whereNull('loser_next_match_id');

        return ($terminalLosses->isNotEmpty() ? $terminalLosses : $losses)
            ->sort(function (TournamentMatch $a, TournamentMatch $b) use ($matchesById, &$distanceMemo): int {
                return $this->distanceToGrandFinal($a, $matchesById, $distanceMemo)
                    <=> $this->distanceToGrandFinal($b, $matchesById, $distanceMemo)
                    ?: $b->match_number <=> $a->match_number;
            })
            ->first();
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @param  Collection<string, TournamentMatch>  $matchesById
     * @param  array<string, int>  $distanceMemo
     */
    private function currentBracketMatch(
        string $participantId,
        Collection $matches,
        Collection $matchesById,
        array &$distanceMemo,
    ): ?TournamentMatch {
        return $matches
            ->filter(fn (TournamentMatch $match): bool => ! in_array($match->status, [MatchStatus::FINISHED, MatchStatus::DQ], true)
                && ($match->participant_a_id === $participantId || $match->participant_b_id === $participantId))
            ->sort(function (TournamentMatch $a, TournamentMatch $b) use ($matchesById, &$distanceMemo): int {
                return $this->distanceToGrandFinal($a, $matchesById, $distanceMemo)
                    <=> $this->distanceToGrandFinal($b, $matchesById, $distanceMemo)
                    ?: $b->match_number <=> $a->match_number;
            })
            ->first();
    }

    /**
     * Count the winner-path edges from a match to the grand final. This makes
     * the graph itself the source of truth and naturally accounts for byes.
     *
     * @param  Collection<string, TournamentMatch>  $matchesById
     * @param  array<string, int>  $memo
     * @param  array<string, bool>  $visiting
     */
    private function distanceToGrandFinal(
        TournamentMatch $match,
        Collection $matchesById,
        array &$memo,
        array $visiting = [],
    ): int {
        $matchId = (string) $match->id;

        if (isset($memo[$matchId])) {
            return $memo[$matchId];
        }

        if ($match->bracket_type === BracketType::GRAND_FINAL) {
            return $memo[$matchId] = 0;
        }

        if (isset($visiting[$matchId]) || $match->winner_next_match_id === null) {
            return $memo[$matchId] = self::UNREACHABLE_DISTANCE;
        }

        $nextMatch = $matchesById->get((string) $match->winner_next_match_id);

        if (! $nextMatch instanceof TournamentMatch) {
            return $memo[$matchId] = self::UNREACHABLE_DISTANCE;
        }

        $visiting[$matchId] = true;
        $nextDistance = $this->distanceToGrandFinal($nextMatch, $matchesById, $memo, $visiting);

        return $memo[$matchId] = $nextDistance === self::UNREACHABLE_DISTANCE
            ? self::UNREACHABLE_DISTANCE
            : $nextDistance + 1;
    }
}
