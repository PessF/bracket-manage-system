<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
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
}
