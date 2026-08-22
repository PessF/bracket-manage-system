<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
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
