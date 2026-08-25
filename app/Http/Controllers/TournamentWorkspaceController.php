<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TournamentWorkspaceController extends Controller
{
    public function bracket(Tournament $tournament): View
    {
        $matches = $tournament->matches()->with([
            'participantA',
            'participantB',
            'winner',
            'loser',
            'winnerNextMatch',
            'loserNextMatch',
        ])->orderBy('match_number')->get();
        $standings = $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number')->get();
        $podium = $this->podium($matches, $standings);
        $groupedMatches = $this->bracketGroupsForDisplay($matches);

        return view('tournaments.bracket', ['tournament' => $tournament, 'matches' => $groupedMatches, 'podium' => $podium]);
    }

    public function matches(Tournament $tournament): View
    {
        $matches = $tournament->matches()->with(['participantA', 'participantB', 'winner'])->orderBy('match_number')->get();
        $grandFinalRounds = $matches
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->values()
            ->mapWithKeys(fn ($match, int $index): array => [$match->id => $index + 1]);

        return view('tournaments.matches', compact('tournament', 'matches', 'grandFinalRounds'));
    }

    public function adminMatches(Tournament $tournament): RedirectResponse
    {
        return redirect()->route('tournaments.bracket', $tournament);
    }

    public function results(Tournament $tournament): View
    {
        $standings = $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number')->get();
        $participants = $tournament->participants()->with(['rankingAttempts' => fn ($query) => $query->orderBy('attempt_number')])->orderBy('seed_number')->get();

        if ($standings->isEmpty() && $tournament->format->isElimination()) {
            $standings = $this->derivedEliminationStandings($tournament, $participants);
        }

        return view('tournaments.results', compact('tournament', 'standings', 'participants'));
    }

    private function bracketGroupsForDisplay(Collection $matches): Collection
    {
        $groups = $matches->groupBy(fn (TournamentMatch $match): string => $match->bracket_type->value);
        $grandFinals = $groups->get(BracketType::GRAND_FINAL->value, collect());

        if ($grandFinals->isNotEmpty() && $groups->has(BracketType::WINNERS->value)) {
            $winnerRounds = $groups->get(BracketType::WINNERS->value);
            $nextRound = ((int) $winnerRounds->max('round_number')) + 1;
            $grandFinals->values()->each(function (TournamentMatch $match, int $index) use ($nextRound): void {
                $match->setAttribute('round_number', $nextRound + $index);
            });
            $grandFinals->values()->each(function (TournamentMatch $match, int $index) use ($grandFinals): void {
                $nextGrandFinal = $grandFinals->values()->get($index + 1);

                if ($nextGrandFinal instanceof TournamentMatch && $match->winner_next_match_id === null) {
                    $match->setAttribute('winner_next_match_id', $nextGrandFinal->id);
                }
            });
            $groups->put(BracketType::WINNERS->value, $winnerRounds->concat($grandFinals)->sortBy('match_number')->values());
            $groups->forget(BracketType::GRAND_FINAL->value);
        }

        return $groups;
    }

    private function podium(Collection $matches, Collection $standings): Collection
    {
        if ($standings->isNotEmpty()) {
            return $standings->take(3)->map(fn ($standing): array => [
                'rank' => $standing->rank_number,
                'participant' => $standing->participant,
                'source' => null,
            ])->values();
        }

        $final = $matches
            ->where('status', MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $match): bool => $match->winner_id !== null)
            ->sortByDesc('match_number')
            ->first(fn (TournamentMatch $match): bool => $match->winner_next_match_id === null);

        if (! $final instanceof TournamentMatch) {
            return collect();
        }

        $thirdPlaceMatch = $matches
            ->where('status', MatchStatus::FINISHED)
            ->where('bracket_type', BracketType::LOSERS)
            ->sortByDesc('match_number')
            ->first(fn (TournamentMatch $match): bool => $match->loser_id !== null && $match->loser_id !== $final->loser_id);

        return collect([
            ['rank' => 1, 'participant' => $final->winner, 'source' => $final],
            ['rank' => 2, 'participant' => $final->loser, 'source' => $final],
            ['rank' => 3, 'participant' => $thirdPlaceMatch?->loser, 'source' => $thirdPlaceMatch],
        ])->filter(fn (array $row): bool => $row['participant'] !== null)->values();
    }

    private function derivedEliminationStandings(Tournament $tournament, Collection $participants): Collection
    {
        $matches = $tournament->matches()
            ->with(['participantA', 'participantB', 'winner', 'loser'])
            ->where('status', MatchStatus::FINISHED)
            ->orderBy('match_number')
            ->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $stats = $participants->mapWithKeys(fn (Participant $participant): array => [(string) $participant->id => [
            'participant' => $participant,
            'played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'score_for' => 0.0,
            'score_against' => 0.0,
        ]])->all();

        foreach ($matches as $match) {
            if ($match->participant_a_id !== null && isset($stats[$match->participant_a_id])) {
                $stats[$match->participant_a_id]['played']++;
                $stats[$match->participant_a_id]['score_for'] += (float) ($match->score_a ?? 0);
                $stats[$match->participant_a_id]['score_against'] += (float) ($match->score_b ?? 0);
            }

            if ($match->participant_b_id !== null && isset($stats[$match->participant_b_id])) {
                $stats[$match->participant_b_id]['played']++;
                $stats[$match->participant_b_id]['score_for'] += (float) ($match->score_b ?? 0);
                $stats[$match->participant_b_id]['score_against'] += (float) ($match->score_a ?? 0);
            }

            if ($match->winner_id !== null && isset($stats[$match->winner_id])) {
                $stats[$match->winner_id]['wins']++;
            }

            if ($match->loser_id !== null && isset($stats[$match->loser_id])) {
                $stats[$match->loser_id]['losses']++;
            }
        }

        $ranked = [];
        $final = $matches
            ->filter(fn (TournamentMatch $match): bool => $match->winner_id !== null)
            ->sortByDesc('match_number')
            ->first(fn (TournamentMatch $match): bool => $match->winner_next_match_id === null);

        if ($final instanceof TournamentMatch) {
            foreach ([$final->winner_id, $final->loser_id] as $participantId) {
                if ($participantId !== null && ! in_array($participantId, $ranked, true)) {
                    $ranked[] = $participantId;
                }
            }
        }

        $matches->sortByDesc('match_number')->each(function (TournamentMatch $match) use (&$ranked): void {
            if ($match->loser_id !== null && ! in_array($match->loser_id, $ranked, true)) {
                $ranked[] = $match->loser_id;
            }
        });

        $participants->each(function (Participant $participant) use (&$ranked): void {
            if (! in_array((string) $participant->id, $ranked, true)) {
                $ranked[] = (string) $participant->id;
            }
        });

        return collect($ranked)->map(function (string $participantId, int $index) use ($stats): object {
            $row = $stats[$participantId];
            $scoreDifference = $row['score_for'] - $row['score_against'];

            return (object) [
                'rank_number' => $index + 1,
                'participant' => $row['participant'],
                'played' => $row['played'],
                'wins' => $row['wins'],
                'draws' => $row['draws'],
                'losses' => $row['losses'],
                'score_for' => $row['score_for'],
                'score_against' => $row['score_against'],
                'score_difference' => $scoreDifference,
                'best_value' => null,
            ];
        })->values();
    }
}
