<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RankingType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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

    public function test_racing_robot_uses_the_fastest_valid_lap_to_two_decimal_places(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['type' => RankingType::RACING_ROBOT->value, 'attempts' => 3, 'comparator' => 'BEST_TIME_LOWER'],
        ]);
        [$alpha, $beta] = Participant::factory()->count(2)->create(['tournament_id' => $tournament->id])->all();
        $service = app(RankingService::class);

        $service->saveAttempt($tournament, $alpha, 1, '14.28');
        $service->saveAttempt($tournament, $alpha, 2, '11.37');
        $service->saveAttempt($tournament, $alpha, 3, '10.00', false);
        $service->saveAttempt($tournament, $beta, 1, '12.05');

        $standings = $tournament->standings()->get()->keyBy('participant_id');
        $this->assertSame(1, $standings[$alpha->id]->rank_number);
        $this->assertSame('11.370000', $standings[$alpha->id]->best_value);
        $this->assertSame(2, $standings[$alpha->id]->format_data['best_attempt_number']);
        $this->assertSame(2, $standings[$beta->id]->rank_number);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)->get(route('tournaments.results', $tournament))
            ->assertOk()
            ->assertSee('data-ranking-saved-result', false)
            ->assertSee('data-ranking-edit-trigger', false)
            ->assertSee('data-attempt-value="11.37"', false)
            ->assertSee('data-ranking-edit-modal', false)
            ->assertSee('name="attempt_value"', false);
    }

    public function test_drone_mission_ranks_by_combined_score_then_lowest_time(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['type' => RankingType::DRONE_MISSION->value, 'attempts' => 2, 'comparator' => 'BEST_SCORE_HIGHER_THEN_TIME_LOWER'],
        ]);
        [$alpha, $beta, $gamma] = Participant::factory()->count(3)->create(['tournament_id' => $tournament->id])->all();
        $service = app(RankingService::class);

        $service->saveAttempt($tournament, $alpha, 1, null, true, 35, 25, '48.20');
        $service->saveAttempt($tournament, $alpha, 2, null, true, 40, 20, '44.50');
        $service->saveAttempt($tournament, $beta, 1, null, true, 30, 30, '39.25');
        $service->saveAttempt($tournament, $gamma, 1, null, true, 25, 30, '30.00');

        $standings = $tournament->standings()->get()->keyBy('participant_id');
        $this->assertSame(1, $standings[$beta->id]->rank_number);
        $this->assertSame(2, $standings[$alpha->id]->rank_number);
        $this->assertSame(3, $standings[$gamma->id]->rank_number);
        $this->assertSame('60.000000', $standings[$alpha->id]->best_value);
        $this->assertSame('40.00', $standings[$alpha->id]->format_data['manual_score']);
        $this->assertSame('20.00', $standings[$alpha->id]->format_data['auto_score']);
        $this->assertSame('44.50', $standings[$alpha->id]->format_data['attempt_time']);
    }

    public function test_drone_mission_rejects_manual_or_automatic_scores_above_fifty(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['type' => RankingType::DRONE_MISSION->value, 'attempts' => 1, 'comparator' => 'BEST_SCORE_HIGHER_THEN_TIME_LOWER'],
        ]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.drone_score_range'));

        app(RankingService::class)->saveAttempt($tournament, $participant, 1, null, true, '50.01', '49.00', '30.00');
    }

    public function test_drone_mission_http_entry_stores_and_displays_structured_lap_data(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['type' => RankingType::DRONE_MISSION->value, 'attempts' => 2, 'comparator' => 'BEST_SCORE_HIGHER_THEN_TIME_LOWER'],
        ]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Drone Alpha']);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->post(route('ranking.attempts.store', [$tournament, $participant]), [
            'attempt_number' => 1,
            'manual_score' => '42.50',
            'auto_score' => '17.50',
            'attempt_time' => '38.25',
            'is_valid' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $attempt = $participant->rankingAttempts()->firstOrFail();
        $this->assertSame('60.000000', $attempt->attempt_value);
        $this->assertSame('42.50', $attempt->manual_score);
        $this->assertSame('17.50', $attempt->auto_score);
        $this->assertSame('38.25', $attempt->attempt_time);

        $response = $this->get(route('tournaments.results', $tournament))
            ->assertOk()
            ->assertSee('name="manual_score"', false)
            ->assertSee('name="auto_score"', false)
            ->assertSee('name="attempt_time"', false)
            ->assertSee('data-ranking-round-selector', false)
            ->assertSee('name="attempt_number" value="2"', false)
            ->assertDontSee('<select name="attempt_number"', false)
            ->assertSee('data-ranking-saved-result', false)
            ->assertSee('data-ranking-edit-trigger', false)
            ->assertSee('data-ranking-edit-modal', false)
            ->assertSee('data-ranking-async-form', false)
            ->assertSee('data-manual-score="42.50"', false)
            ->assertSee('name="manual_score" value="" min="0" max="50"', false)
            ->assertSee('ranking-standings-wrap', false)
            ->assertSee(__('ui.swipe_ranking_rounds'))
            ->assertSee(__('ui.manual_score'))
            ->assertSee(__('ui.auto_score'))
            ->assertSee(__('ui.total_score'))
            ->assertSee(__('ui.time_minutes'))
            ->assertSee('38.25 '.__('ui.minutes_short'))
            ->assertSee(__('ui.drone_ranking_rule'))
            ->assertSee('60.00');

        $this->assertSame(1, substr_count($response->getContent(), '<select id="rankingRoundSelector"'));

        $this->postJson(route('ranking.attempts.store', [$tournament, $participant]), [
            'attempt_number' => 1,
            'manual_score' => '50.00',
            'auto_score' => '18.00',
            'attempt_time' => '32.10',
            'is_valid' => 1,
        ])->assertOk()
            ->assertJsonPath('attempt.attempt_number', 1)
            ->assertJsonPath('attempt.manual_score', '50.00')
            ->assertJsonPath('attempt.auto_score', '18.00')
            ->assertJsonPath('attempt.attempt_time', '32.10');

        $this->assertDatabaseHas('external_ranking_attempts', [
            'tournament_id' => $tournament->id,
            'participant_id' => $participant->id,
            'attempt_number' => 1,
            'attempt_value' => '68.000000',
            'manual_score' => '50.00',
            'auto_score' => '18.00',
            'attempt_time' => '32.10',
        ]);

        $this->postJson(route('ranking.attempts.store', [$tournament, $participant]), [
            'attempt_number' => 1,
            'manual_score' => '50.01',
            'auto_score' => '18.00',
            'attempt_time' => '32.10',
            'is_valid' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('manual_score');

        $this->assertSame('50.00', $attempt->refresh()->manual_score);
    }
}
