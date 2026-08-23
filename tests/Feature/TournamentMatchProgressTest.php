<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use App\Services\MatchProgressService;
use App\Services\TournamentLifecycleService;
use DomainException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TournamentMatchProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournaments_start_without_scheduling_configuration(): void
    {
        foreach ([
            TournamentFormat::SINGLE_ELIMINATION,
            TournamentFormat::DOUBLE_ELIMINATION,
            TournamentFormat::ROUND_ROBIN,
        ] as $format) {
            $tournament = $this->draft($format, 4);

            app(TournamentLifecycleService::class)->start($tournament);

            $this->assertSame(TournamentStatus::LIVE, $tournament->refresh()->status);
            $this->assertTrue($tournament->matches()->exists());
        }

        $this->assertFalse(Schema::hasColumn('external_tournaments', 'schedule_start_time'));
        $this->assertFalse(Schema::hasColumn('external_tournaments', 'match_duration_minutes'));
        $this->assertFalse(Schema::hasColumn('external_tournaments', 'schedule_timezone'));
        $this->assertFalse(Schema::hasColumn('external_tournaments', 'schedule_delay_minutes'));
        $this->assertFalse(Schema::hasColumn('external_matches', 'scheduled_at'));
    }

    public function test_only_one_playable_match_can_be_in_progress(): void
    {
        $tournament = $this->draft(TournamentFormat::ROUND_ROBIN, 3);
        app(TournamentLifecycleService::class)->start($tournament);
        $matches = $tournament->matches()->orderBy('match_number')->get();

        $current = app(MatchProgressService::class)->markInProgress($matches[0]);

        $this->assertSame(MatchStatus::LIVE, $current->status);
        $this->assertNotNull($current->started_at);
        $this->assertSame(MatchStatus::READY, $matches[1]->refresh()->status);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(__('ui.another_match_in_progress', ['number' => 1]));
        app(MatchProgressService::class)->markInProgress($matches[1]);
    }

    public function test_admin_and_viewer_brackets_show_the_current_match_without_timing_ui(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $tournament = $this->draft(TournamentFormat::SINGLE_ELIMINATION, 2);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('ui.start_tournament'))
            ->assertDontSee('data-schedule-planner', false)
            ->assertDontSee('schedule_start_time', false)
            ->assertDontSee('match_duration_minutes', false)
            ->assertDontSee('schedule_timezone', false);

        $this->post(route('tournaments.start', $tournament))->assertSessionHasNoErrors();
        $match = $tournament->matches()->where('is_bye', false)->firstOrFail();
        $this->post(route('matches.progress.store', [$tournament, $match]))
            ->assertSessionHasNoErrors();

        $this->get(route('tournaments.bracket', $tournament))
            ->assertOk()
            ->assertSee(__('ui.red_side'))
            ->assertSee(__('ui.blue_side'))
            ->assertSee(__('ui.current_match'))
            ->assertSee('class="bracket-current"', false)
            ->assertSee('width:36px', false)
            ->assertSee('height:36px', false)
            ->assertDontSee('data-scheduled-time', false)
            ->assertDontSee('class="bracket-time"', false);

        $this->get(route('public.tournaments.show', ['tournament' => $tournament->public_token]))
            ->assertOk()
            ->assertSee(__('ui.current_match'))
            ->assertSee('class="bracket-current"', false)
            ->assertDontSee('data-score-modal-trigger', false)
            ->assertDontSee('data-scheduled-time', false);
    }

    private function draft(TournamentFormat $format, int $participantCount): Tournament
    {
        $tournament = Tournament::factory()->create(['format' => $format]);
        Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $format]);
        Participant::factory()->count($participantCount)
            ->sequence(fn ($sequence): array => ['seed_number' => $sequence->index + 1])
            ->create(['tournament_id' => $tournament->id]);

        return $tournament;
    }
}
