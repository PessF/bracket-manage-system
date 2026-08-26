<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BracketType;
use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Services\BracketGenerator;
use App\Services\MatchResultService;
use App\Services\TournamentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_complete_eight_team_double_elimination_graph(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 8);
        app(TournamentLifecycleService::class)->start($tournament);

        $tournament->refresh();
        $this->assertSame(TournamentStatus::LIVE, $tournament->status);
        $this->assertNotNull($tournament->locked_at);
        $this->assertSame(14, $tournament->matches()->count());
        $this->assertSame(7, $tournament->matches()->where('bracket_type', BracketType::WINNERS)->count());
        $this->assertSame(6, $tournament->matches()->where('bracket_type', BracketType::LOSERS)->count());
        $this->assertSame(1, $tournament->matches()->where('bracket_type', BracketType::GRAND_FINAL)->count());
        $this->assertSame(4, $tournament->matches()->where('status', MatchStatus::READY)->count());
        $this->assertGreaterThan(0, $tournament->matches()->whereNotNull('loser_next_match_id')->count());
        $this->assertSame(StageStatus::LIVE, $tournament->stages()->first()->status);
    }

    public function test_twenty_two_team_double_elimination_uses_standard_match_count_and_complete_routing(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 22);
        app(TournamentLifecycleService::class)->prepareBracket($tournament);

        $matches = $tournament->matches()->orderBy('match_number')->get();
        $winners = $matches->where('bracket_type', BracketType::WINNERS)->values();
        $losers = $matches->where('bracket_type', BracketType::LOSERS)->values();
        $grandFinal = $matches->firstWhere('bracket_type', BracketType::GRAND_FINAL);

        $this->assertCount(42, $matches);
        $this->assertCount(21, $winners);
        $this->assertCount(20, $losers);
        $this->assertNotNull($grandFinal);
        $this->assertSame(range(1, 42), $matches->pluck('match_number')->all());
        $this->assertFalse($matches->contains('is_bye', true));

        foreach ($winners as $match) {
            $this->assertNotNull($match->loser_next_match_id, "Winners match {$match->match_number} has no lower-bracket destination.");
            $this->assertSame(BracketType::LOSERS, $matches->firstWhere('id', $match->loser_next_match_id)?->bracket_type);
        }

        foreach ($losers as $match) {
            $destination = $matches->firstWhere('id', $match->winner_next_match_id);
            $this->assertNotNull($destination, "Lower match {$match->match_number} has no winner destination.");
            $this->assertContains($destination->bracket_type, [BracketType::LOSERS, BracketType::GRAND_FINAL]);
        }

        $this->assertSame(BracketType::WINNERS, $matches->firstWhere('id', $grandFinal->participant_a_source_match_id)?->bracket_type);
        $this->assertSame(MatchOutcome::WINNER, $grandFinal->participant_a_source_outcome);
        $this->assertSame(BracketType::LOSERS, $matches->firstWhere('id', $grandFinal->participant_b_source_match_id)?->bracket_type);
        $this->assertSame(MatchOutcome::WINNER, $grandFinal->participant_b_source_outcome);
    }

    public function test_twenty_two_team_double_elimination_can_play_through_without_a_dead_end(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 22);
        app(TournamentLifecycleService::class)->start($tournament);
        $results = app(MatchResultService::class);

        for ($played = 0; $played < 42; $played++) {
            $next = $tournament->matches()
                ->where('status', MatchStatus::READY)
                ->orderBy('match_number')
                ->first();

            $this->assertNotNull($next, "The bracket stopped after {$played} matches.");
            $results->confirm($next, 1, 0);
        }

        $this->assertSame(42, $tournament->matches()->where('status', MatchStatus::FINISHED)->count());
        $this->assertSame(0, $tournament->matches()->whereIn('status', [MatchStatus::PENDING, MatchStatus::READY, MatchStatus::LIVE])->count());

        $losses = $tournament->matches()
            ->whereNotNull('loser_id')
            ->selectRaw('loser_id, count(*) as losses')
            ->groupBy('loser_id')
            ->pluck('losses', 'loser_id');

        $this->assertCount(21, $losses);
        $this->assertSame([2], $losses->map(fn ($count): int => (int) $count)->unique()->values()->all());
    }

    public function test_partial_lower_round_survivors_are_evenly_distributed_against_upper_drop_downs(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 22);
        app(TournamentLifecycleService::class)->prepareBracket($tournament);

        $matches = $tournament->matches()->get()->keyBy('id');
        $consolidationRound = $matches
            ->where('bracket_type', BracketType::LOSERS)
            ->where('round_number', 5)
            ->sortBy('match_number')
            ->values();

        $this->assertCount(4, $consolidationRound);

        $sourceTypes = $consolidationRound->map(function ($match) use ($matches): array {
            return collect([$match->participant_a_source_match_id, $match->participant_b_source_match_id])
                ->map(fn ($id): ?BracketType => $matches->get($id)?->bracket_type)
                ->filter()
                ->sortBy(fn (BracketType $type): string => $type->value)
                ->values()
                ->all();
        });

        $this->assertSame([
            [BracketType::LOSERS, BracketType::WINNERS],
            [BracketType::LOSERS, BracketType::WINNERS],
            [BracketType::LOSERS, BracketType::WINNERS],
            [BracketType::WINNERS, BracketType::WINNERS],
        ], $sourceTypes->all());
    }

    public function test_explicit_randomization_changes_and_preserves_the_prepared_seed_order(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 8);
        $tournament->forceFill(['seeding_method' => SeedingMethod::RANDOM])->save();
        $originalOrder = $tournament->participants()->orderBy('seed_number')->pluck('id')->all();

        app(TournamentLifecycleService::class)->randomizeParticipants($tournament);

        $randomizedOrder = $tournament->participants()->orderBy('seed_number')->pluck('id')->all();
        $this->assertNotSame($originalOrder, $randomizedOrder);
        $this->assertSame(range(1, 8), $tournament->participants()->orderBy('seed_number')->pluck('seed_number')->all());
        $this->assertSame(SeedingMethod::MANUAL, $tournament->refresh()->seeding_method);

        app(TournamentLifecycleService::class)->prepareBracket($tournament);

        $this->assertSame($randomizedOrder, $tournament->participants()->orderBy('seed_number')->pluck('id')->all());
    }

    public function test_randomization_is_blocked_after_a_bracket_has_been_prepared(): void
    {
        $tournament = $this->draft(TournamentFormat::DOUBLE_ELIMINATION, 4);
        $service = app(TournamentLifecycleService::class);
        $service->prepareBracket($tournament);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('ui.randomize_participants_bracket_exists'));

        $service->randomizeParticipants($tournament);
    }

    public function test_advanced_double_elimination_placeholder_uses_the_same_compact_graph(): void
    {
        $drafts = app(BracketGenerator::class)->generatePlaceholder(
            TournamentFormat::DOUBLE_ELIMINATION,
            ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        );

        $this->assertCount(10, $drafts);
        $this->assertCount(5, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::WINNERS));
        $this->assertCount(4, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::LOSERS));
        $this->assertCount(1, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::GRAND_FINAL));
        $this->assertFalse(collect($drafts)->contains('is_bye', true));
        $this->assertSame(range(1, 10), array_column($drafts, 'match_number'));
    }

    public function test_double_elimination_match_formula_holds_for_every_field_size_through_thirty_two(): void
    {
        $generator = app(BracketGenerator::class);

        for ($count = 2; $count <= 32; $count++) {
            $participants = [];

            for ($seed = 1; $seed <= $count; $seed++) {
                $participants[] = new Participant([
                    'id' => "participant-{$seed}",
                    'team_name' => "Team {$seed}",
                    'seed_number' => $seed,
                ]);
            }

            $drafts = $generator->generate(TournamentFormat::DOUBLE_ELIMINATION, $participants);
            $keys = array_column($drafts, null, 'key');

            $this->assertCount(($count * 2) - 2, $drafts, "Unexpected total for {$count} competitors.");
            $this->assertCount($count - 1, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::WINNERS));
            $this->assertCount($count - 2, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::LOSERS));
            $this->assertCount(1, array_filter($drafts, fn (array $match): bool => $match['bracket_type'] === BracketType::GRAND_FINAL));
            $this->assertFalse(collect($drafts)->contains('is_bye', true));

            foreach ($drafts as $draft) {
                foreach (['winner_next_key', 'loser_next_key'] as $destination) {
                    if ($draft[$destination] !== null) {
                        $this->assertArrayHasKey($draft[$destination], $keys);
                    }
                }
            }
        }
    }

    public function test_it_supports_byes_round_robin_and_ranking(): void
    {
        $single = $this->draft(TournamentFormat::SINGLE_ELIMINATION, 6);
        app(TournamentLifecycleService::class)->start($single);
        $this->assertSame(7, $single->matches()->count());
        $this->assertSame(2, $single->matches()->where('is_bye', true)->count());

        $roundRobin = $this->draft(TournamentFormat::ROUND_ROBIN, 4);
        app(TournamentLifecycleService::class)->start($roundRobin);
        $this->assertSame(6, $roundRobin->matches()->count());

        $ranking = $this->draft(TournamentFormat::RANKING, 3);
        app(TournamentLifecycleService::class)->start($ranking);
        $this->assertSame(TournamentStatus::LIVE, $ranking->refresh()->status);
        $this->assertSame(0, $ranking->matches()->count());
    }

    private function draft(TournamentFormat $format, int $count): Tournament
    {
        $tournament = Tournament::factory()->create([
            'format' => $format,
            'ranking_config' => $format === TournamentFormat::RANKING ? ['attempts' => 3, 'comparator' => 'BEST_SCORE_HIGHER'] : null,
        ]);
        Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $format]);
        Participant::factory()->count($count)->sequence(fn ($sequence) => ['seed_number' => $sequence->index + 1])->create(['tournament_id' => $tournament->id]);

        return $tournament;
    }
}
