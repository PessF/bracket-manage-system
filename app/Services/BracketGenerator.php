<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BracketType;
use App\Enums\MatchSlot;
use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Models\Participant;
use InvalidArgumentException;

class BracketGenerator
{
    private int $losersMatchCounter = 0;

    /**
     * @param  list<Participant>  $participants
     * @return list<array<string, mixed>>
     */
    public function generate(TournamentFormat $format, array $participants, bool $thirdPlace = false): array
    {
        return match ($format) {
            TournamentFormat::RANKING => [],
            TournamentFormat::ROUND_ROBIN => $this->roundRobin($participants),
            TournamentFormat::SINGLE_ELIMINATION => $this->withThirdPlace($this->winnersBracket($participants, 'SE')['matches'], $thirdPlace, 'SE'),
            TournamentFormat::DOUBLE_ELIMINATION => $this->doubleElimination($participants),
        };
    }

    /**
     * @param  list<string>  $labels
     * @return list<array<string, mixed>>
     */
    public function generatePlaceholder(TournamentFormat $format, array $labels, bool $thirdPlace = false): array
    {
        return match ($format) {
            TournamentFormat::SINGLE_ELIMINATION, TournamentFormat::DOUBLE_ELIMINATION => $this->withThirdPlace($this->placeholderWinnersBracket($labels, 'PO'), $thirdPlace, 'PO'),
            default => [],
        };
    }

    /**
     * @param  list<string>  $labels
     * @return list<array<string, mixed>>
     */
    private function placeholderWinnersBracket(array $labels, string $prefix): array
    {
        if (count($labels) < 2) {
            throw new InvalidArgumentException(__('ui.bracket_two_participants_required'));
        }

        $size = $this->nextPowerOfTwo(count($labels));
        $seedOrder = $this->standardSeedOrder($size);
        $labelsBySeed = [];

        foreach ($labels as $index => $label) {
            $labelsBySeed[$index + 1] = $label;
        }

        $matches = [];
        $roundKeys = [];
        $matchNumber = 1;
        $firstRoundKeys = [];

        for ($index = 0; $index < $size / 2; $index++) {
            $key = "{$prefix}R1M".($index + 1);
            $firstRoundKeys[] = $key;
            $matches[] = $this->draft([
                'key' => $key,
                'match_number' => $matchNumber++,
                'participant_a_label' => $labelsBySeed[$seedOrder[$index * 2]] ?? 'BYE',
                'participant_b_label' => $labelsBySeed[$seedOrder[$index * 2 + 1]] ?? 'BYE',
                'status' => MatchStatus::PENDING,
            ]);
        }

        $roundKeys[] = $firstRoundKeys;
        $currentRoundKeys = $firstRoundKeys;
        $totalRounds = (int) log($size, 2);

        for ($round = 2; $round <= $totalRounds; $round++) {
            $nextRoundKeys = [];
            $matchesInRound = (int) ($size / (2 ** $round));

            for ($index = 0; $index < $matchesInRound; $index++) {
                $key = "{$prefix}R{$round}M".($index + 1);
                $nextRoundKeys[] = $key;
                $sourceAIndex = $this->indexByKey($matches, $currentRoundKeys[$index * 2]);
                $sourceBIndex = $this->indexByKey($matches, $currentRoundKeys[$index * 2 + 1]);
                $matches[$sourceAIndex]['winner_next_key'] = $key;
                $matches[$sourceAIndex]['winner_next_slot'] = MatchSlot::A;
                $matches[$sourceBIndex]['winner_next_key'] = $key;
                $matches[$sourceBIndex]['winner_next_slot'] = MatchSlot::B;

                $matches[] = $this->draft([
                    'key' => $key,
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'status' => MatchStatus::PENDING,
                ]);
            }

            $roundKeys[] = $nextRoundKeys;
            $currentRoundKeys = $nextRoundKeys;
        }

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function withThirdPlace(array $matches, bool $enabled, string $prefix): array
    {
        if (! $enabled || count($matches) < 3) {
            return $matches;
        }

        $finalRound = max(array_map(fn (array $match): int => (int) $match['round_number'], $matches));
        $semifinalRound = $finalRound - 1;
        $semifinals = array_values(array_filter(
            $matches,
            fn (array $match): bool => $match['bracket_type'] === BracketType::WINNERS && (int) $match['round_number'] === $semifinalRound,
        ));

        if (count($semifinals) !== 2) {
            return $matches;
        }

        $thirdPlaceKey = "{$prefix}THIRD_PLACE";
        $matches[] = $this->draft([
            'key' => $thirdPlaceKey,
            'bracket_type' => BracketType::WINNERS,
            'round_number' => $finalRound,
            'match_number' => max(array_map(fn (array $match): int => (int) $match['match_number'], $matches)) + 1,
            'status' => MatchStatus::PENDING,
        ]);

        $this->wireToken($matches, ['match_key' => $semifinals[0]['key'], 'outcome' => 'loser'], $thirdPlaceKey, MatchSlot::A);
        $this->wireToken($matches, ['match_key' => $semifinals[1]['key'], 'outcome' => 'loser'], $thirdPlaceKey, MatchSlot::B);

        return $matches;
    }

    /**
     * @param  list<Participant>  $participants
     * @return list<array<string, mixed>>
     */
    private function roundRobin(array $participants): array
    {
        $this->assertParticipantCount($participants);
        usort($participants, fn (Participant $a, Participant $b): int => $a->seed_number <=> $b->seed_number);

        $matches = [];
        $matchNumber = 1;

        for ($first = 0; $first < count($participants); $first++) {
            for ($second = $first + 1; $second < count($participants); $second++) {
                $matches[] = $this->draft([
                    'key' => "RR{$matchNumber}",
                    'bracket_type' => BracketType::ROUND_ROBIN,
                    'match_number' => $matchNumber++,
                    'participant_a_id' => $participants[$first]->id,
                    'participant_b_id' => $participants[$second]->id,
                    'status' => MatchStatus::READY,
                ]);
            }
        }

        return $matches;
    }

    /**
     * @param  list<Participant>  $participants
     * @return array{matches: list<array<string, mixed>>, round_keys: list<list<string>>, total_rounds: int}
     */
    private function winnersBracket(array $participants, string $prefix): array
    {
        $this->assertParticipantCount($participants);
        usort($participants, fn (Participant $a, Participant $b): int => $a->seed_number <=> $b->seed_number);

        $size = $this->nextPowerOfTwo(count($participants));
        $seedOrder = $this->standardSeedOrder($size);
        $participantsBySeed = [];

        foreach ($participants as $index => $participant) {
            $participantsBySeed[$index + 1] = $participant;
        }

        $matches = [];
        $roundKeys = [];
        $matchNumber = 1;
        $firstRoundKeys = [];

        for ($index = 0; $index < $size / 2; $index++) {
            $participantA = $participantsBySeed[$seedOrder[$index * 2]] ?? null;
            $participantB = $participantsBySeed[$seedOrder[$index * 2 + 1]] ?? null;
            $key = "{$prefix}R1M".($index + 1);
            $isBye = $participantA === null || $participantB === null;
            $firstRoundKeys[] = $key;
            $matches[] = $this->draft([
                'key' => $key,
                'match_number' => $matchNumber++,
                'participant_a_id' => $participantA?->id,
                'participant_b_id' => $participantB?->id,
                'is_bye' => $isBye,
                'status' => $isBye ? MatchStatus::FINISHED : MatchStatus::READY,
                'winner_id' => $isBye ? ($participantA?->id ?? $participantB?->id) : null,
            ]);
        }

        $roundKeys[] = $firstRoundKeys;
        $currentRoundKeys = $firstRoundKeys;
        $totalRounds = (int) log($size, 2);

        for ($round = 2; $round <= $totalRounds; $round++) {
            $nextRoundKeys = [];
            $matchesInRound = (int) ($size / (2 ** $round));

            for ($index = 0; $index < $matchesInRound; $index++) {
                $key = "{$prefix}R{$round}M".($index + 1);
                $nextRoundKeys[] = $key;
                $sourceAIndex = $this->indexByKey($matches, $currentRoundKeys[$index * 2]);
                $sourceBIndex = $this->indexByKey($matches, $currentRoundKeys[$index * 2 + 1]);
                $matches[$sourceAIndex]['winner_next_key'] = $key;
                $matches[$sourceAIndex]['winner_next_slot'] = MatchSlot::A;
                $matches[$sourceBIndex]['winner_next_key'] = $key;
                $matches[$sourceBIndex]['winner_next_slot'] = MatchSlot::B;
                $participantAId = $matches[$sourceAIndex]['is_bye']
                    ? $matches[$sourceAIndex]['winner_id']
                    : null;
                $participantBId = $matches[$sourceBIndex]['is_bye']
                    ? $matches[$sourceBIndex]['winner_id']
                    : null;

                $matches[] = $this->draft([
                    'key' => $key,
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'participant_a_id' => $participantAId,
                    'participant_b_id' => $participantBId,
                    'status' => $participantAId !== null && $participantBId !== null
                        ? MatchStatus::READY
                        : MatchStatus::PENDING,
                ]);
            }

            $roundKeys[] = $nextRoundKeys;
            $currentRoundKeys = $nextRoundKeys;
        }

        return [
            'matches' => $matches,
            'round_keys' => $roundKeys,
            'total_rounds' => $totalRounds,
        ];
    }

    /**
     * @param  list<Participant>  $participants
     * @return list<array<string, mixed>>
     */
    private function doubleElimination(array $participants): array
    {
        $this->losersMatchCounter = 0;
        $winnersBracket = $this->winnersBracket($participants, 'WB');
        $matches = $winnersBracket['matches'];
        $roundKeys = $winnersBracket['round_keys'];
        $winnersRoundCount = $winnersBracket['total_rounds'];
        $matchNumber = count($matches) + 1;
        $losersPool = [];

        for ($winnersRound = 1; $winnersRound <= $winnersRoundCount; $winnersRound++) {
            $winnersLosers = [];

            foreach ($roundKeys[$winnersRound - 1] as $key) {
                $match = $matches[$this->indexByKey($matches, $key)];

                if (! $match['is_bye']) {
                    $winnersLosers[] = ['match_key' => $key, 'outcome' => 'loser'];
                }
            }

            $pool = $winnersRound === 1
                ? $winnersLosers
                : $this->mergeAndAdvance(
                    $matches,
                    $losersPool,
                    $winnersLosers,
                    $winnersRound * 2,
                    $matchNumber,
                );

            $losersPool = $winnersRound < $winnersRoundCount
                ? $this->pairAndAdvance($matches, $pool, $winnersRound * 2 + 1, $matchNumber)
                : $pool;
        }

        $safetyRound = $winnersRoundCount * 2 + 2;

        while (count($losersPool) > 1) {
            $losersPool = $this->pairAndAdvance($matches, $losersPool, $safetyRound++, $matchNumber);
        }

        $grandFinalKey = 'GF';
        $matches[] = $this->draft([
            'key' => $grandFinalKey,
            'bracket_type' => BracketType::GRAND_FINAL,
            'round_number' => $winnersRoundCount * 2 + 100,
            'match_number' => $matchNumber,
        ]);
        $this->wireToken(
            $matches,
            ['match_key' => $roundKeys[$winnersRoundCount - 1][0], 'outcome' => 'winner'],
            $grandFinalKey,
            MatchSlot::A,
        );

        if (isset($losersPool[0])) {
            $this->wireToken($matches, $losersPool[0], $grandFinalKey, MatchSlot::B);
        }

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  list<array{match_key: string, outcome: string}>  $tokens
     * @return list<array{match_key: string, outcome: string}>
     */
    private function pairAndAdvance(
        array &$matches,
        array $tokens,
        int $round,
        int &$matchNumber,
    ): array {
        $winners = [];

        for ($index = 0; $index < count($tokens); $index += 2) {
            $winners[] = isset($tokens[$index + 1])
                ? $this->makeLosersMatch(
                    $matches,
                    $tokens[$index],
                    $tokens[$index + 1],
                    $round,
                    $matchNumber,
                )
                : $tokens[$index];
        }

        return $winners;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  list<array{match_key: string, outcome: string}>  $firstPool
     * @param  list<array{match_key: string, outcome: string}>  $secondPool
     * @return list<array{match_key: string, outcome: string}>
     */
    private function mergeAndAdvance(
        array &$matches,
        array $firstPool,
        array $secondPool,
        int $round,
        int &$matchNumber,
    ): array {
        $winners = [];

        for ($index = 0; $index < max(count($firstPool), count($secondPool)); $index++) {
            $first = $firstPool[$index] ?? null;
            $second = $secondPool[$index] ?? null;

            if ($first !== null && $second !== null) {
                $winners[] = $this->makeLosersMatch(
                    $matches,
                    $first,
                    $second,
                    $round,
                    $matchNumber,
                );
            } elseif ($first !== null) {
                $winners[] = $first;
            } elseif ($second !== null) {
                $winners[] = $second;
            }
        }

        return $winners;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  array{match_key: string, outcome: string}  $firstToken
     * @param  array{match_key: string, outcome: string}  $secondToken
     * @return array{match_key: string, outcome: string}
     */
    private function makeLosersMatch(
        array &$matches,
        array $firstToken,
        array $secondToken,
        int $round,
        int &$matchNumber,
    ): array {
        $key = 'LB'.++$this->losersMatchCounter;
        $matches[] = $this->draft([
            'key' => $key,
            'bracket_type' => BracketType::LOSERS,
            'round_number' => $round,
            'match_number' => $matchNumber++,
        ]);
        $this->wireToken($matches, $firstToken, $key, MatchSlot::A);
        $this->wireToken($matches, $secondToken, $key, MatchSlot::B);

        return ['match_key' => $key, 'outcome' => 'winner'];
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  array{match_key: string, outcome: string}  $token
     */
    private function wireToken(array &$matches, array $token, string $targetKey, MatchSlot $slot): void
    {
        $index = $this->indexByKey($matches, $token['match_key']);
        $prefix = $token['outcome'] === 'winner' ? 'winner' : 'loser';
        $matches[$index]["{$prefix}_next_key"] = $targetKey;
        $matches[$index]["{$prefix}_next_slot"] = $slot;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draft(array $overrides): array
    {
        return array_merge([
            'key' => '',
            'bracket_type' => BracketType::WINNERS,
            'round_number' => 1,
            'match_number' => 1,
            'participant_a_id' => null,
            'participant_b_id' => null,
            'participant_a_label' => null,
            'participant_b_label' => null,
            'is_bye' => false,
            'status' => MatchStatus::PENDING,
            'winner_id' => null,
            'loser_id' => null,
            'winner_next_key' => null,
            'winner_next_slot' => null,
            'loser_next_key' => null,
            'loser_next_slot' => null,
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function indexByKey(array $matches, string $key): int
    {
        foreach ($matches as $index => $match) {
            if ($match['key'] === $key) {
                return $index;
            }
        }

        throw new InvalidArgumentException(__('ui.unknown_bracket_match_key', ['key' => $key]));
    }

    /**
     * @param  list<Participant>  $participants
     */
    private function assertParticipantCount(array $participants): void
    {
        if (count($participants) < 2) {
            throw new InvalidArgumentException(__('ui.bracket_two_participants_required'));
        }
    }

    private function nextPowerOfTwo(int $number): int
    {
        $size = 2;

        while ($size < $number) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * @return list<int>
     */
    private function standardSeedOrder(int $size): array
    {
        $seeds = [1];

        while (count($seeds) < $size) {
            $length = count($seeds) * 2 + 1;
            $next = [];

            foreach ($seeds as $seed) {
                $next[] = $seed;
                $next[] = $length - $seed;
            }

            $seeds = $next;
        }

        return $seeds;
    }
}
