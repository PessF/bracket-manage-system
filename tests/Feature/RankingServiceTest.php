<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_valid_best_attempts_and_standard_competition_ranking(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['attempts' => 3, 'comparator' => 'BEST_SCORE_HIGHER'],
        ]);
        [$a, $b, $c] = Participant::factory()->count(3)->create(['tournament_id' => $tournament->id])->all();
        $service = app(RankingService::class);
        $service->saveAttempt($tournament, $a, 1, 10, true);
        $service->saveAttempt($tournament, $a, 2, 99, false);
        $service->saveAttempt($tournament, $b, 1, 10, true);
        $service->saveAttempt($tournament, $c, 1, 8, true);

        $standings = $tournament->standings()->get()->keyBy('participant_id');
        $this->assertSame(1, $standings[$a->id]->rank_number);
        $this->assertSame(1, $standings[$b->id]->rank_number);
        $this->assertSame(3, $standings[$c->id]->rank_number);
        $this->assertSame('10.000000', $standings[$a->id]->best_value);
    }

    public function test_admin_results_display_ranking_participants_in_current_rank_order(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['attempts' => 1, 'comparator' => 'BEST_SCORE_HIGHER'],
        ]);
        $lower = Participant::factory()->create([
            'tournament_id' => $tournament->id,
            'team_name' => 'Lower Score',
            'seed_number' => 1,
        ]);
        $higher = Participant::factory()->create([
            'tournament_id' => $tournament->id,
            'team_name' => 'Higher Score',
            'seed_number' => 2,
        ]);
        $service = app(RankingService::class);
        $service->saveAttempt($tournament, $lower, 1, 2, true);
        $service->saveAttempt($tournament, $higher, 1, 9, true);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->get(route('tournaments.results', $tournament))
            ->assertOk()
            ->assertSeeInOrder(['Higher Score', 'Lower Score']);
    }
}
