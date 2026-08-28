<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerTournamentNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_tournament_entry_defaults_to_the_bracket_while_overview_remains_available(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);

        $this->get(route('tournaments.index'))
            ->assertOk()
            ->assertSee('class="card tournament-card" href="'.route('tournaments.bracket', $tournament).'"', false);

        $this->get(route('tournaments.show', $tournament))
            ->assertRedirect(route('tournaments.bracket', $tournament));

        $this->get(route('tournaments.bracket', $tournament))
            ->assertOk()
            ->assertSee('class="tabs viewer-control-tabs"', false)
            ->assertSee('tab-bracket active', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('tournaments.bracket', $tournament).'"', false)
            ->assertSee('href="'.route('tournaments.overview', $tournament).'"', false)
            ->assertSee(__('ui.overview_participants'))
            ->assertSee('all-tournaments-tab', false)
            ->assertSee('nav.viewer-control-tabs > .all-tournaments-tab', false);

        $this->get(route('tournaments.overview', $tournament))
            ->assertOk()
            ->assertSee('tab-overview active', false)
            ->assertSee('href="'.route('tournaments.overview', $tournament).'"', false);
    }

    public function test_admin_tournament_entry_still_opens_the_overview(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $tournament = Tournament::factory()->create();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('class="tabs admin-control-tabs"', false)
            ->assertSee('tab-overview active', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('tournaments.show', $tournament).'"', false);
    }

    public function test_opening_a_viewer_bracket_does_not_start_a_match(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $stage = Stage::factory()->create(['tournament_id' => $tournament->id]);
        $participantA = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $participantB = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $match = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'stage_id' => $stage->id,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'status' => MatchStatus::READY,
            'is_bye' => false,
        ]);

        $this->get(route('tournaments.bracket', $tournament))->assertOk();

        $this->assertSame(MatchStatus::READY, $match->refresh()->status);
        $this->assertNull($match->started_at);
    }
}
