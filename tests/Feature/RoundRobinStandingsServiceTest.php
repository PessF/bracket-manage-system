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
use App\Services\RoundRobinStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundRobinStandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_difference_is_exact_and_ranked_from_highest_to_lowest(): void
    {
        [$tournament, $participants] = $this->roundRobinWithCompletedMatches();

        app(RoundRobinStandingsService::class)->recompute($tournament);

        $standings = $tournament->standings()->orderBy('rank_number')->get();

        $this->assertSame($participants['bravo']->id, $standings[0]->participant_id);
        $this->assertSame('7.000000', $standings[0]->score_difference);
        $this->assertSame($participants['alpha']->id, $standings[1]->participant_id);
        $this->assertSame('0.000000', $standings[1]->score_difference);
        $this->assertSame($participants['charlie']->id, $standings[2]->participant_id);
        $this->assertSame('-7.000000', $standings[2]->score_difference);
        $this->assertSame(
            RoundRobinStandingsService::CALCULATION_VERSION,
            $standings[0]->format_data['calculation_version'],
        );
    }

    public function test_results_page_repairs_legacy_ranks_and_live_refreshes_without_a_schema_change(): void
    {
        [$tournament, $participants] = $this->roundRobinWithCompletedMatches();
        $service = app(RoundRobinStandingsService::class);
        $service->recompute($tournament);

        $tournament->standings()->where('participant_id', $participants['bravo']->id)->update([
            'rank_number' => 3,
            'format_data' => json_encode(['ranking' => 'WINS_THEN_DRAWS_THEN_SCORE_DIFFERENCE']),
        ]);
        $tournament->standings()->where('participant_id', $participants['charlie']->id)->update([
            'rank_number' => 1,
            'format_data' => json_encode(['ranking' => 'WINS_THEN_DRAWS_THEN_SCORE_DIFFERENCE']),
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $response = $this->actingAs($admin)->get(route('tournaments.results', $tournament));

        $response->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-interval="5"', false)
            ->assertSee('data-refresh-target="[data-live-results]"', false);
        $this->assertSame(1, $tournament->standings()->where('participant_id', $participants['bravo']->id)->value('rank_number'));
        $this->assertSame(3, $tournament->standings()->where('participant_id', $participants['charlie']->id)->value('rank_number'));
        $this->assertSame(
            RoundRobinStandingsService::CALCULATION_VERSION,
            $tournament->standings()->where('participant_id', $participants['bravo']->id)->firstOrFail()->format_data['calculation_version'],
        );
    }

    /** @return array{Tournament, array{alpha: Participant, bravo: Participant, charlie: Participant}} */
    private function roundRobinWithCompletedMatches(): array
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::ROUND_ROBIN,
            'status' => TournamentStatus::LIVE,
        ]);
        $stage = Stage::factory()->create([
            'tournament_id' => $tournament->id,
            'format' => TournamentFormat::ROUND_ROBIN,
        ]);
        $participants = [
            'alpha' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Alpha']),
            'bravo' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Bravo']),
            'charlie' => Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Charlie']),
        ];

        $this->finishedMatch($tournament, $stage, 1, $participants['alpha'], $participants['bravo'], '4', '1');
        $this->finishedMatch($tournament, $stage, 2, $participants['bravo'], $participants['charlie'], '10', '0');
        $this->finishedMatch($tournament, $stage, 3, $participants['charlie'], $participants['alpha'], '8', '5');

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
            'bracket_type' => BracketType::ROUND_ROBIN,
            'status' => MatchStatus::FINISHED,
            'participant_a_id' => $participantA->id,
            'participant_a_label' => $participantA->team_name,
            'participant_b_id' => $participantB->id,
            'participant_b_label' => $participantB->team_name,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'winner_id' => bccomp($scoreA, $scoreB, 6) > 0 ? $participantA->id : $participantB->id,
            'loser_id' => bccomp($scoreA, $scoreB, 6) > 0 ? $participantB->id : $participantA->id,
            'finished_at' => now(),
        ]);
    }
}
