<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\RoundRobinStandingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TournamentWorkspaceController extends Controller
{
    public function __construct(private readonly RoundRobinStandingsService $roundRobinStandings) {}

    public function bracket(Request $request, Tournament $tournament): View
    {
        $matches = $tournament->matches()->with([
            'participantA',
            'participantB',
            'winner',
            'loser',
            'winnerNextMatch',
            'loserNextMatch',
            'stageGroup',
        ])->orderBy('match_number')->get();
        $standings = $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number')->get();
        $podium = $this->podium($matches, $standings);
        $groups = $matches
            ->filter(fn (TournamentMatch $match): bool => $match->stage_group_id !== null)
            ->pluck('stageGroup')
            ->filter()
            ->unique('id')
            ->sortBy('group_order')
            ->values();
        $activeBracketView = (string) $request->query('view', 'all');
        $groupedMatches = $this->bracketGroupsForDisplay($matches, $activeBracketView);
        $estimatedStartTimes = $this->estimatedStartTimes($tournament, $matches);

        return view('tournaments.bracket', [
            'tournament' => $tournament,
            'matches' => $groupedMatches,
            'podium' => $podium,
            'bracketViewGroups' => $groups,
            'activeBracketView' => $activeBracketView,
            'estimatedStartTimes' => $estimatedStartTimes,
        ]);
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

        $roundRobinNeedsRepair = $tournament->format === TournamentFormat::ROUND_ROBIN
            && ($standings->contains(fn ($standing): bool => (int) ($standing->format_data['calculation_version'] ?? 0) < RoundRobinStandingsService::CALCULATION_VERSION)
                || ($standings->isEmpty() && $tournament->matches()->where('status', MatchStatus::FINISHED)->exists()));

        if ($roundRobinNeedsRepair) {
            $this->roundRobinStandings->recompute($tournament);
            $standings = $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number')->get();
        }

        $participants = $tournament->participants()->with(['rankingAttempts' => fn ($query) => $query->orderBy('attempt_number')])->orderBy('seed_number')->get();

        if ($standings->isEmpty() && $tournament->format->isElimination()) {
            $standings = $this->derivedEliminationStandings($tournament, $participants);
        }

        if ($tournament->format === TournamentFormat::RANKING) {
            $ranks = $standings->keyBy(fn ($standing): string => (string) $standing->participant_id);
            $participants = $participants->sortBy(function (Participant $participant) use ($ranks): array {
                $rank = (int) ($ranks->get((string) $participant->id)?->rank_number ?? 0);

                return [$rank > 0 ? $rank : PHP_INT_MAX, $participant->seed_number ?? PHP_INT_MAX, $participant->team_name];
            })->values();
        }

        return view('tournaments.results', compact('tournament', 'standings', 'participants'));
    }

    private function bracketGroupsForDisplay(Collection $matches, string $activeView = 'all'): Collection
    {
        if ($matches->contains(fn (TournamentMatch $match): bool => $match->stage_group_id !== null)) {
            $groupSections = $matches
                ->filter(fn (TournamentMatch $match): bool => $match->stage_group_id !== null)
                ->groupBy(fn (TournamentMatch $match): string => 'GROUP:'.$match->stage_group_id.':'.$match->bracket_type->value)
                ->map(fn (Collection $group): Collection => $group->sortBy('match_number')->values());
            $groupSections = $this->mergeGroupGrandFinalsIntoWinners($groupSections);
            $playoffSections = $matches
                ->filter(fn (TournamentMatch $match): bool => $match->stage_group_id === null)
                ->groupBy(fn (TournamentMatch $match): string => 'PLAYOFF:'.$match->bracket_type->value)
                ->map(fn (Collection $group): Collection => $group->sortBy('match_number')->values());

            if (str_starts_with($activeView, 'group:')) {
                $groupId = substr($activeView, 6);

                return $groupSections
                    ->filter(fn (Collection $group, string $key): bool => str_starts_with($key, 'GROUP:'.$groupId.':'))
                    ->merge($playoffSections);
            }

            if ($activeView === 'playoff') {
                return $playoffSections;
            }

            return $groupSections->merge($playoffSections);
        }

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

    /** @return array<string, string> */
    private function estimatedStartTimes(Tournament $tournament, Collection $matches): array
    {
        if ($tournament->format === TournamentFormat::RANKING
            || ! $tournament->bracket_schedule_start_time
            || ! $tournament->bracket_match_duration_minutes) {
            return [];
        }

        $time = CarbonImmutable::createFromFormat('H:i', substr((string) $tournament->bracket_schedule_start_time, 0, 5));
        $duration = (int) $tournament->bracket_match_duration_minutes;
        $estimatedStartTimes = [];

        foreach ($matches->sortBy('match_number') as $match) {
            if ($match->is_bye) {
                continue;
            }

            $timeOfDay = $time->format('H:i');
            if ($timeOfDay >= '12:00' && $timeOfDay < '13:00') {
                $time = $time->setTime(13, 0);
            }

            $estimatedStartTimes[(string) $match->id] = $time->format('H:i');
            $time = $time->addMinutes($duration);
        }

        return $estimatedStartTimes;
    }

    private function mergeGroupGrandFinalsIntoWinners(Collection $sections): Collection
    {
        $merged = collect();
        $grandFinalSections = $sections->filter(fn (Collection $group, string $key): bool => str_ends_with($key, ':'.BracketType::GRAND_FINAL->value));

        foreach ($sections as $key => $group) {
            if (str_ends_with((string) $key, ':'.BracketType::GRAND_FINAL->value)) {
                continue;
            }

            if (! str_ends_with((string) $key, ':'.BracketType::WINNERS->value)) {
                $merged->put($key, $group);

                continue;
            }

            $prefix = substr((string) $key, 0, -strlen(':'.BracketType::WINNERS->value));
            $grandFinals = $grandFinalSections->get($prefix.':'.BracketType::GRAND_FINAL->value, collect());

            if ($grandFinals->isEmpty()) {
                $merged->put($key, $group);

                continue;
            }

            $nextRound = ((int) $group->max('round_number')) + 1;
            $grandFinals->values()->each(function (TournamentMatch $match, int $index) use ($nextRound): void {
                $match->setAttribute('round_number', $nextRound + $index);
            });
            $grandFinals->values()->each(function (TournamentMatch $match, int $index) use ($grandFinals): void {
                $nextGrandFinal = $grandFinals->values()->get($index + 1);

                if ($nextGrandFinal instanceof TournamentMatch && $match->winner_next_match_id === null) {
                    $match->setAttribute('winner_next_match_id', $nextGrandFinal->id);
                }
            });

            $merged->put($key, $group->concat($grandFinals)->sortBy('match_number')->values());
        }

        return $merged;
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
