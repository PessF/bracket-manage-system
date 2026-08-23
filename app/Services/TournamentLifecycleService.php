<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Standing;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TournamentLifecycleService
{
    public function __construct(private readonly BracketGenerator $brackets) {}

    public function start(Tournament|string $tournament): Tournament
    {
        $id = $tournament instanceof Tournament ? (string) $tournament->getKey() : $tournament;

        return DB::transaction(function () use ($id): Tournament {
            /** @var Tournament $locked */
            $locked = Tournament::query()->lockForUpdate()->findOrFail($id);

            if (! in_array($locked->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true)) {
                throw new DomainException(__('ui.start_status_invalid'));
            }

            if ($locked->matches()->exists()) {
                throw new DomainException(__('ui.match_graph_exists'));
            }

            /** @var Stage|null $stage */
            $stage = $locked->stages()->orderBy('stage_order')->lockForUpdate()->first();

            if ($stage === null) {
                throw new DomainException(__('ui.stage_required'));
            }

            $participants = $locked->participants()
                ->whereIn('status', [ParticipantStatus::ACTIVE, ParticipantStatus::CHECKED_IN])
                ->get();

            if ($participants->count() < 2) {
                throw new DomainException(__('ui.two_participants_required'));
            }

            $seeded = $this->seedParticipants($participants->all(), $locked->seeding_method);
            $drafts = $this->brackets->generate($locked->format, $seeded);
            $this->persistMatches($locked, $stage, $drafts, $seeded);

            $now = now();
            $stage->fill(['status' => StageStatus::LIVE])->save();
            $locked->fill([
                'status' => TournamentStatus::LIVE,
                'participant_count' => count($seeded),
                'locked_at' => $now,
                'started_at' => $now,
                'source_updated_at' => $now,
                'synced_at' => $now,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function complete(Tournament|string $tournament): Tournament
    {
        $id = $tournament instanceof Tournament ? (string) $tournament->getKey() : $tournament;

        return DB::transaction(function () use ($id): Tournament {
            /** @var Tournament $locked */
            $locked = Tournament::query()->lockForUpdate()->findOrFail($id);

            if ($locked->status !== TournamentStatus::LIVE) {
                throw new DomainException(__('ui.complete_status_invalid'));
            }

            if ($locked->format === TournamentFormat::RANKING) {
                $attempts = max(1, min(20, (int) ($locked->ranking_config['attempts'] ?? 2)));
                $expected = $locked->participant_count * $attempts;

                if ($locked->rankingAttempts()->count() < $expected) {
                    throw new DomainException(__('ui.ranking_incomplete', ['current' => $locked->rankingAttempts()->count(), 'expected' => $expected]));
                }
            } elseif ($locked->matches()->whereNotIn('status', [MatchStatus::FINISHED, MatchStatus::DQ])->exists()) {
                throw new DomainException(__('ui.matches_incomplete'));
            }

            if ($locked->format->isElimination()) {
                $this->recomputeEliminationStandings($locked);
            }

            $now = now();
            $locked->stages()->update(['status' => StageStatus::COMPLETED]);
            $locked->fill([
                'status' => TournamentStatus::COMPLETED,
                'completed_at' => $now,
                'source_updated_at' => $now,
                'synced_at' => $now,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function resetBracket(Tournament|string $tournament): Tournament
    {
        $id = $tournament instanceof Tournament ? (string) $tournament->getKey() : $tournament;

        return DB::transaction(function () use ($id): Tournament {
            /** @var Tournament $locked */
            $locked = Tournament::query()->lockForUpdate()->findOrFail($id);

            if (! in_array($locked->status, [TournamentStatus::LIVE, TournamentStatus::COMPLETED], true)) {
                throw new DomainException(__('ui.reset_bracket_status_invalid'));
            }

            $locked->rankingAttempts()->delete();
            $locked->standings()->delete();
            $locked->matches()->delete();
            $locked->stages()->update(['status' => StageStatus::PENDING]);

            $locked->fill([
                'status' => TournamentStatus::READY,
                'locked_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'source_updated_at' => now(),
                'synced_at' => now(),
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function archive(Tournament|string $tournament): Tournament
    {
        $model = $tournament instanceof Tournament ? $tournament : Tournament::query()->findOrFail($tournament);

        if ($model->status !== TournamentStatus::COMPLETED) {
            throw new DomainException(__('ui.archive_status_invalid'));
        }

        $model->fill([
            'status' => TournamentStatus::ARCHIVED,
            'source_updated_at' => now(),
            'synced_at' => now(),
        ])->save();

        return $model->refresh();
    }

    /**
     * @param  list<Participant>  $participants
     * @return list<Participant>
     */
    private function seedParticipants(array $participants, SeedingMethod $method): array
    {
        if ($method === SeedingMethod::RANDOM) {
            shuffle($participants);
        } elseif (in_array($method, [SeedingMethod::MANUAL, SeedingMethod::RANKING], true)) {
            usort($participants, fn (Participant $a, Participant $b): int => ($a->seed_number ?? PHP_INT_MAX) <=> ($b->seed_number ?? PHP_INT_MAX)
                ?: strcmp((string) $a->id, (string) $b->id));
        } else {
            usort($participants, fn (Participant $a, Participant $b): int => ($a->source_created_at?->getTimestamp() ?? 0) <=> ($b->source_created_at?->getTimestamp() ?? 0)
                ?: strcmp((string) $a->id, (string) $b->id));
        }

        foreach ($participants as $index => $participant) {
            $participant->fill(['seed_number' => $index + 1, 'synced_at' => now()])->save();
        }

        return $participants;
    }

    /**
     * @param  list<array<string, mixed>>  $drafts
     * @param  list<Participant>  $participants
     */
    private function persistMatches(Tournament $tournament, Stage $stage, array $drafts, array $participants): void
    {
        if ($drafts === []) {
            return;
        }

        $ids = [];
        $labels = [];
        $sources = [];

        foreach ($participants as $participant) {
            $labels[(string) $participant->id] = $participant->team_name;
        }

        foreach ($drafts as $draft) {
            $ids[$draft['key']] = (string) Str::uuid();
        }

        foreach ($drafts as $draft) {
            foreach (['winner', 'loser'] as $outcome) {
                $target = $draft["{$outcome}_next_key"];
                $slot = $draft["{$outcome}_next_slot"];

                if ($target !== null && $slot !== null) {
                    $sources[$target][$slot->value] = [
                        'id' => $ids[$draft['key']],
                        'number' => $draft['match_number'],
                        'outcome' => strtoupper($outcome),
                    ];
                }
            }
        }

        foreach ($drafts as $draft) {
            $slotA = $sources[$draft['key']]['A'] ?? null;
            $slotB = $sources[$draft['key']]['B'] ?? null;
            $participantAId = $draft['participant_a_id'];
            $participantBId = $draft['participant_b_id'];

            TournamentMatch::query()->create([
                'id' => $ids[$draft['key']],
                'tournament_id' => $tournament->id,
                'stage_id' => $stage->id,
                'match_number' => $draft['match_number'],
                'bracket_type' => $draft['bracket_type'],
                'round_number' => $draft['round_number'],
                'status' => $draft['status'],
                'is_bye' => $draft['is_bye'],
                'participant_a_id' => $participantAId,
                'participant_a_label' => $participantAId !== null ? $labels[$participantAId] : $this->waitingLabel($slotA),
                'participant_a_source_match_id' => $slotA['id'] ?? null,
                'participant_a_source_outcome' => isset($slotA) ? MatchOutcome::from($slotA['outcome']) : null,
                'participant_b_id' => $participantBId,
                'participant_b_label' => $participantBId !== null ? $labels[$participantBId] : ($draft['is_bye'] ? 'BYE' : $this->waitingLabel($slotB)),
                'participant_b_source_match_id' => $slotB['id'] ?? null,
                'participant_b_source_outcome' => isset($slotB) ? MatchOutcome::from($slotB['outcome']) : null,
                'winner_id' => $draft['winner_id'],
                'loser_id' => $draft['loser_id'],
                'winner_next_match_id' => $draft['winner_next_key'] !== null ? $ids[$draft['winner_next_key']] : null,
                'winner_next_slot' => $draft['winner_next_slot'],
                'loser_next_match_id' => $draft['loser_next_key'] !== null ? $ids[$draft['loser_next_key']] : null,
                'loser_next_slot' => $draft['loser_next_slot'],
                'finished_at' => $draft['is_bye'] ? now() : null,
                'synced_at' => now(),
            ]);
        }
    }

    /** @param array{id: string, number: int, outcome: string}|null $source */
    private function waitingLabel(?array $source): string
    {
        return $source === null
            ? 'TBD'
            : ucfirst(strtolower($source['outcome'])).' of Match #'.$source['number'];
    }

    private function recomputeEliminationStandings(Tournament $tournament): void
    {
        $matches = $tournament->matches()
            ->with(['participantA', 'participantB', 'winner', 'loser'])
            ->where('status', MatchStatus::FINISHED)
            ->orderBy('match_number')
            ->get();

        $participants = $tournament->participants()->orderBy('seed_number')->orderBy('team_name')->get();
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

        $rankedIds = $this->eliminationRankedParticipantIds($matches, $participants);
        $now = now();

        foreach ($rankedIds as $rank => $participantId) {
            $row = $stats[$participantId] ?? null;

            if ($row === null) {
                continue;
            }

            Standing::query()->updateOrCreate(
                ['tournament_id' => $tournament->id, 'participant_id' => $participantId],
                [
                    'rank_number' => $rank + 1,
                    'best_value' => null,
                    'played' => $row['played'],
                    'wins' => $row['wins'],
                    'draws' => $row['draws'],
                    'losses' => $row['losses'],
                    'score_for' => $row['score_for'],
                    'score_against' => $row['score_against'],
                    'score_difference' => $row['score_for'] - $row['score_against'],
                    'points' => $row['wins'] * 3,
                    'format_data' => null,
                    'synced_at' => $now,
                ],
            );
        }
    }

    /** @return list<string> */
    private function eliminationRankedParticipantIds(Collection $matches, Collection $participants): array
    {
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

        return $ranked;
    }
}
