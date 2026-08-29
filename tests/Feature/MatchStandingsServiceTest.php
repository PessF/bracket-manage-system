<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\MatchStandingsService;
use App\Services\TournamentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchStandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_win_loss_difference_ranks_before_cumulative_points_in_non_double_elimination_match_formats(): void
    {
        foreach ([
            TournamentFormat::ROUND_ROBIN,
            TournamentFormat::SINGLE_ELIMINATION,
        ] as $format) {
            [$tournament, $participants] = $this->tournamentWithCompletedMatches($format);

            app(MatchStandingsService::class)->recompute($tournament);

            $standings = $tournament->standings()->orderBy('rank_number')->get();

            $this->assertSame($participants['alpha']->id, $standings[0]->participant_id);
            $this->assertSame(2, $standings[0]->wins);
            $this->assertSame('2.000000', $standings[0]->score_for);
            $this->assertSame($participants['bravo']->id, $standings[1]->participant_id);
            $this->assertSame(1, $standings[1]->wins);
            $this->assertSame('101.000000', $standings[1]->score_for);
            $this->assertSame($participants['charlie']->id, $standings[2]->participant_id);
            $this->assertSame('10.000000', $standings[2]->score_for);
            $this->assertSame('0.000000', $standings[0]->score_against);
            $this->assertSame('0.000000', $standings[0]->score_difference);
            $this->assertSame(MatchStandingsService::RANKING_RULE, $standings[0]->format_data['ranking']);
            $this->assertSame(MatchStandingsService::CALCULATION_VERSION, $standings[0]->format_data['calculation_version']);
        }
    }

    public function test_fewer_wins_can_rank_higher_when_win_loss_difference_is_better(): void
    {
        foreach ([
            TournamentFormat::ROUND_ROBIN,
            TournamentFormat::SINGLE_ELIMINATION,
        ] as $format) {
            $tournament = Tournament::factory()->create(['format' => $format, 'status' => TournamentStatus::LIVE]);
            $stage = Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $format]);
            $alpha = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Alpha']);
            $bravo = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Bravo']);
            $charlie = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Charlie']);
            $delta = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Delta']);
            $echo = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Echo']);
            $matchNumber = 1;

            for ($index = 0; $index < 3; $index++) {
                $this->finishedMatch($tournament, $stage, $matchNumber++, $alpha, $delta, '100', '0');
                $this->finishedMatch($tournament, $stage, $matchNumber++, $charlie, $alpha, '1', '0');
            }
            $this->finishedMatch($tournament, $stage, $matchNumber++, $bravo, $delta, '1', '0');
            $this->finishedMatch($tournament, $stage, $matchNumber++, $echo, $delta, '10', '0');
            $this->finishedMatch($tournament, $stage, $matchNumber, $charlie, $echo, '1', '0');

            app(MatchStandingsService::class)->recompute($tournament);

            $standings = $tournament->standings()->orderBy('rank_number')->get();
            $this->assertSame($charlie->id, $standings[0]->participant_id);
            $this->assertSame($bravo->id, $standings[1]->participant_id);
            $this->assertSame($alpha->id, $standings[2]->participant_id);
            $this->assertSame($echo->id, $standings[3]->participant_id);
            $this->assertSame($delta->id, $standings[4]->participant_id);
            $this->assertSame(1, $standings[1]->wins);
            $this->assertSame(3, $standings[2]->wins);
            $this->assertSame(1, $standings[1]->points);
            $this->assertSame(0, $standings[2]->points);
            $this->assertSame('300.000000', $standings[2]->score_for);
            $this->assertSame('10.000000', $standings[3]->score_for);
        }
    }

    public function test_elimination_results_repair_legacy_ranks_and_show_only_relevant_score_columns(): void
    {
        [$tournament, $participants] = $this->tournamentWithCompletedMatches(TournamentFormat::SINGLE_ELIMINATION);
        $service = app(MatchStandingsService::class);
        $service->recompute($tournament);

        $tournament->standings()->where('participant_id', $participants['alpha']->id)->update([
            'rank_number' => 4,
            'score_against' => 99,
            'score_difference' => -97,
            'format_data' => json_encode(['ranking' => 'LEGACY_RULE']),
        ]);
        $tournament->standings()->where('participant_id', $participants['bravo']->id)->update([
            'rank_number' => 1,
            'format_data' => json_encode(['ranking' => 'LEGACY_RULE']),
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $response = $this->actingAs($admin)->get(route('tournaments.results', $tournament));

        $response->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-interval="1"', false)
            ->assertSee('data-refresh-target="[data-live-results]"', false)
            ->assertSee('<th>'.__('ui.score_for').'</th>', false)
            ->assertDontSee('<th>'.__('ui.score_against').'</th>', false)
            ->assertDontSee('<th>'.__('ui.difference').'</th>', false);
        $this->assertSame(1, $tournament->standings()->where('participant_id', $participants['alpha']->id)->value('rank_number'));
        $this->assertSame(2, $tournament->standings()->where('participant_id', $participants['bravo']->id)->value('rank_number'));
        $this->assertSame('99.000000', $tournament->standings()->where('participant_id', $participants['alpha']->id)->value('score_against'));
        $this->assertSame('-97.000000', $tournament->standings()->where('participant_id', $participants['alpha']->id)->value('score_difference'));
        $this->assertSame(
            MatchStandingsService::CALCULATION_VERSION,
            $tournament->standings()->where('participant_id', $participants['alpha']->id)->firstOrFail()->format_data['calculation_version'],
        );
    }

    public function test_double_elimination_ranks_by_bracket_progression_despite_byes_and_win_totals(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'status' => TournamentStatus::DRAFT,
            'double_elimination_config' => ['grand_final_matches' => 1],
        ]);
        Stage::factory()->create([
            'tournament_id' => $tournament->id,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
        ]);

        for ($seed = 1; $seed <= 6; $seed++) {
            Participant::factory()->create([
                'tournament_id' => $tournament->id,
                'team_name' => "Team {$seed}",
                'seed_number' => $seed,
            ]);
        }

        app(TournamentLifecycleService::class)->start($tournament);
        $preferredChampion = $tournament->participants()->where('seed_number', 1)->firstOrFail();
        $this->assertSame(
            2,
            $tournament->matches()
                ->where(fn ($query) => $query
                    ->where('participant_a_id', $preferredChampion->id)
                    ->orWhere('participant_b_id', $preferredChampion->id))
                ->min('round_number'),
            'The preferred champion must start in round two after receiving a bye.',
        );
        $this->playDoubleElimination($tournament, $preferredChampion);

        $standings = $tournament->standings()->with('participant')->orderBy('rank_number')->get();
        $champion = $standings->firstWhere('participant_id', $preferredChampion->id);
        $this->assertNotNull($champion);
        $this->assertSame([1, 2, 3, 4, 5, 6], $standings->pluck('rank_number')->all());
        $participantWithMoreWins = $standings->first(
            fn ($standing): bool => $standing->rank_number > 1 && $standing->wins > $champion->wins,
        );

        $this->assertSame(1, $champion->rank_number);
        $this->assertSame('CHAMPION', $champion->format_data['placement_status']);
        $this->assertSame(MatchStandingsService::DOUBLE_ELIMINATION_RANKING_RULE, $champion->format_data['ranking']);
        $this->assertNotNull($participantWithMoreWins, 'The fixture must include a lower-ranked team with more match wins than the champion who received a bye.');
        $this->assertGreaterThan($champion->rank_number, $participantWithMoreWins->rank_number);
        $this->assertSame([0], $standings->pluck('points')->unique()->values()->all());

        $runnerUp = $standings->firstWhere('rank_number', 2);
        $this->assertNotNull($runnerUp);
        $this->assertSame('RUNNER_UP', $runnerUp->format_data['placement_status']);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)
            ->get(route('tournaments.results', $tournament))
            ->assertOk()
            ->assertSee(__('ui.double_elimination_standings_rule'));
    }

    public function test_double_elimination_uses_standard_shared_placements_for_each_elimination_level(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'status' => TournamentStatus::DRAFT,
            'double_elimination_config' => ['grand_final_matches' => 1],
        ]);
        Stage::factory()->create([
            'tournament_id' => $tournament->id,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
        ]);

        for ($seed = 1; $seed <= 8; $seed++) {
            Participant::factory()->create([
                'tournament_id' => $tournament->id,
                'team_name' => "Team {$seed}",
                'seed_number' => $seed,
            ]);
        }

        app(TournamentLifecycleService::class)->start($tournament);
        $this->playDoubleElimination($tournament);

        $this->assertSame(
            [1, 2, 3, 4, 5, 5, 7, 7],
            $tournament->standings()->orderBy('rank_number')->orderBy('participant_id')->pluck('rank_number')->all(),
        );
    }

    public function test_grand_final_reset_keeps_both_finalists_active_until_the_deciding_match(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'status' => TournamentStatus::DRAFT,
            'double_elimination_config' => ['grand_final_matches' => 2],
        ]);
        Stage::factory()->create([
            'tournament_id' => $tournament->id,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
        ]);
        Participant::factory()->count(2)->sequence(
            ['seed_number' => 1, 'team_name' => 'Alpha'],
            ['seed_number' => 2, 'team_name' => 'Bravo'],
        )->create(['tournament_id' => $tournament->id]);

        app(TournamentLifecycleService::class)->start($tournament);
        $results = app(MatchResultService::class);
        $winnersFinal = $tournament->matches()->where('bracket_type', BracketType::WINNERS)->firstOrFail();
        $results->confirm($winnersFinal, 2, 1);
        $firstGrandFinal = $tournament->matches()->where('bracket_type', BracketType::GRAND_FINAL)->firstOrFail();
        $results->confirm($firstGrandFinal, 1, 2);

        $afterFirstFinal = $tournament->standings()->orderBy('participant_id')->get();
        $this->assertCount(2, $tournament->matches()->where('bracket_type', BracketType::GRAND_FINAL)->get());
        $this->assertSame([1], $afterFirstFinal->pluck('rank_number')->unique()->values()->all());
        $this->assertSame(['ACTIVE'], $afterFirstFinal->pluck('format_data')->pluck('placement_status')->unique()->values()->all());

        $reset = $tournament->matches()
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->orderByDesc('match_number')
            ->firstOrFail();
        $results->confirm($reset, 2, 1);

        $finalStandings = $tournament->standings()->orderBy('rank_number')->get();
        $this->assertSame([1, 2], $finalStandings->pluck('rank_number')->all());
        $this->assertSame(['CHAMPION', 'RUNNER_UP'], $finalStandings->pluck('format_data')->pluck('placement_status')->all());
    }

    public function test_confirming_single_and_double_elimination_scores_updates_standings_immediately(): void
    {
        foreach ([TournamentFormat::SINGLE_ELIMINATION, TournamentFormat::DOUBLE_ELIMINATION] as $format) {
            $tournament = Tournament::factory()->create([
                'format' => $format,
                'status' => TournamentStatus::LIVE,
            ]);
            $stage = Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $format]);
            $winner = Participant::factory()->create(['tournament_id' => $tournament->id]);
            $loser = Participant::factory()->create(['tournament_id' => $tournament->id]);
            $match = TournamentMatch::factory()->create([
                'tournament_id' => $tournament->id,
                'stage_id' => $stage->id,
                'match_number' => 1,
                'status' => MatchStatus::READY,
                'participant_a_id' => $winner->id,
                'participant_a_label' => $winner->team_name,
                'participant_b_id' => $loser->id,
                'participant_b_label' => $loser->team_name,
            ]);

            app(MatchResultService::class)->confirm($match, 3, 1);

            $firstPlace = $tournament->standings()->where('rank_number', 1)->firstOrFail();
            $this->assertSame($winner->id, $firstPlace->participant_id);
            $this->assertSame(1, $firstPlace->wins);
            $this->assertSame('3.000000', $firstPlace->score_for);
        }
    }

    /** @return array{Tournament, array{alpha: Participant, bravo: Participant, charlie: Participant, delta: Participant}} */
    private function tournamentWithCompletedMatches(TournamentFormat $format): array
    {
        $tournament = Tournament::factory()->create([
            'format' => $format,
            'status' => TournamentStatus::LIVE,
        ]);
        $stage = Stage::factory()->create([
            'tournament_id' => $tournament->id,
            'format' => $format,
        ]);
        $participants = [
            'alpha' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Alpha']),
            'bravo' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Bravo']),
            'charlie' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Charlie']),
            'delta' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Delta']),
        ];

        $this->finishedMatch($tournament, $stage, 1, $participants['alpha'], $participants['bravo'], '1', '0');
        $this->finishedMatch($tournament, $stage, 2, $participants['alpha'], $participants['charlie'], '1', '0');
        $this->finishedMatch($tournament, $stage, 3, $participants['bravo'], $participants['delta'], '101', '0');
        $this->finishedMatch($tournament, $stage, 4, $participants['charlie'], $participants['delta'], '10', '0');

        return [$tournament, $participants];
    }

    private function finishedMatch(
        Tournament $tournament,
        Stage $stage,
        int $number,
        Participant $participantA,
        Participant $participantB,
        string $scoreA,
        string $scoreB,
    ): void {
        TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'stage_id' => $stage->id,
            'match_number' => $number,
            'bracket_type' => $tournament->format === TournamentFormat::ROUND_ROBIN ? BracketType::ROUND_ROBIN : BracketType::WINNERS,
            'status' => MatchStatus::FINISHED,
            'participant_a_id' => $participantA->id,
            'participant_a_label' => $participantA->team_name,
            'participant_b_id' => $participantB->id,
            'participant_b_label' => $participantB->team_name,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'winner_id' => $participantA->id,
            'loser_id' => $participantB->id,
            'finished_at' => now(),
        ]);
    }

    private function playDoubleElimination(Tournament $tournament, ?Participant $preferredWinner = null): void
    {
        $results = app(MatchResultService::class);
        $maximumMatches = ($tournament->participants()->count() * 2) + 2;

        for ($played = 0; $played < $maximumMatches; $played++) {
            $match = $tournament->matches()
                ->whereIn('status', [MatchStatus::LIVE, MatchStatus::READY])
                ->whereNotNull('participant_a_id')
                ->whereNotNull('participant_b_id')
                ->orderBy('match_number')
                ->first();

            if (! $match instanceof TournamentMatch) {
                break;
            }

            $preferredWinnerIsB = $preferredWinner !== null
                && $match->participant_b_id === $preferredWinner->id;
            $results->confirm($match, $preferredWinnerIsB ? 0 : 1, $preferredWinnerIsB ? 1 : 0);
        }

        $this->assertFalse(
            $tournament->matches()->whereNotIn('status', [MatchStatus::FINISHED, MatchStatus::DQ])->exists(),
            'The generated double-elimination bracket did not finish.',
        );
    }
}
