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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchStandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_win_loss_difference_ranks_before_cumulative_points_in_all_match_formats(): void
    {
        foreach ([
            TournamentFormat::ROUND_ROBIN,
            TournamentFormat::SINGLE_ELIMINATION,
            TournamentFormat::DOUBLE_ELIMINATION,
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
            TournamentFormat::DOUBLE_ELIMINATION,
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
        [$tournament, $participants] = $this->tournamentWithCompletedMatches(TournamentFormat::DOUBLE_ELIMINATION);
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
}
