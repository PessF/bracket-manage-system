<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_filters_render_recoverable_pages_and_api_validation_errors(): void
    {
        $tournament = Tournament::factory()->create();
        foreach (['/events?q[]=bad', '/events?page[]=bad', '/tournaments?q[]=bad', '/tournaments?status[]=LIVE', route('tournaments.bracket', $tournament).'?view[]=all'] as $url) {
            $this->get($url)->assertUnprocessable()->assertSee(__('ui.invalid_filters_help'))->assertDontSee('Array to string conversion');
        }
        foreach (['/api/tournaments?status[]=LIVE', '/api/tournaments?search[]=bad', '/api/events?per_page[]=bad'] as $url) {
            $this->getJson($url)->assertUnprocessable()->assertJsonPath('success', false)->assertJsonStructure(['error' => ['fields']]);
        }
    }

    public function test_infrastructure_errors_never_expose_exception_details_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/audit/server-error', fn () => throw new RuntimeException('SECRET backend path'));
        Route::get('/audit/database-error', fn () => throw new QueryException('sqlite', 'SECRET SQL', [], new RuntimeException('database unavailable')));
        Route::get('/api/audit/server-error', fn () => throw new RuntimeException('SECRET backend path'));
        $this->get('/audit/server-error')->assertStatus(500)->assertSee(__('ui.server_error_help'))->assertDontSee('SECRET');
        $this->get('/audit/database-error')->assertStatus(503)->assertSee(__('ui.server_error_help'))->assertDontSee('SECRET');
        $this->getJson('/api/audit/server-error')->assertStatus(500)->assertJsonPath('success', false)->assertDontSee('SECRET');
        foreach ([409, 419, 429] as $status) {
            Route::get('/audit/error-'.$status, fn () => abort($status));
            $this->get('/audit/error-'.$status)->assertStatus($status)->assertSee('class="card auth-card"', false);
        }
    }

    public function test_failed_ranking_save_is_retryable_and_does_not_expose_internal_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $tournament = Tournament::factory()->create(['format' => TournamentFormat::RANKING, 'status' => TournamentStatus::LIVE, 'ranking_config' => ['type' => 'RACING_ROBOT', 'attempts' => 2]]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $this->mock(RankingService::class)->shouldReceive('saveAttempt')->once()->andThrow(new RuntimeException('SECRET SQL'));
        $this->postJson(route('ranking.attempts.store', [$tournament, $participant]), ['attempt_number' => 1, 'attempt_value' => 12, 'is_valid' => true])
            ->assertStatus(500)->assertJsonPath('message', __('ui.request_failed'))->assertDontSee('SECRET');
        $this->assertDatabaseCount('external_ranking_attempts', 0);
    }

    public function test_admin_pages_and_empty_competitions_render_for_every_format(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $event = Event::factory()->create();
        $pages = ['/events', '/events/create', route('events.edit', $event), '/tournaments/create', '/admin/users', '/admin/api-token', '/api/docs'];
        foreach (TournamentFormat::cases() as $format) {
            $tournament = Tournament::factory()->create(['event_id' => $event->id, 'format' => $format, 'status' => TournamentStatus::LIVE]);
            foreach (['tournaments.overview', 'tournaments.settings', 'tournaments.results'] as $route) {
                $pages[] = route($route, $tournament);
            }
            if ($format !== TournamentFormat::RANKING) {
                $pages[] = route('tournaments.bracket', $tournament);
            }
        }
        $ranking = Tournament::factory()->create(['format' => TournamentFormat::RANKING, 'status' => TournamentStatus::LIVE, 'ranking_config' => ['type' => 'RACING_ROBOT', 'attempts' => 2]]);
        Participant::factory()->create(['tournament_id' => $ranking->id]);
        $pages[] = route('tournaments.results', $ranking);
        foreach ($pages as $index => $url) {
            $response = $this->get($url)->assertOk();
            // Optional browser audit fixtures use only this isolated in-memory database.
            if ($directory = getenv('AUDIT_HTML_DIR')) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                file_put_contents($directory.'/page-'.$index.'.html', $response->getContent());
            }
        }
    }

    public function test_invalid_legacy_schedule_does_not_break_the_bracket(): void
    {
        $tournament = Tournament::factory()->create(['bracket_schedule_start_time' => 'invalid', 'bracket_match_duration_minutes' => 10]);
        $this->get(route('tournaments.bracket', $tournament))->assertOk()->assertViewHas('estimatedStartTimes', []);
    }
}
