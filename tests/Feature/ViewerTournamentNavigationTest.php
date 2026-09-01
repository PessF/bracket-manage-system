<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\RankingType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\RankingService;
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
            ->assertSee('href="'.route('tournaments.show', $tournament).'"', false)
            ->assertSeeInOrder([
                'href="'.route('tournaments.index').'"',
                'href="'.route('tournaments.show', $tournament).'"',
                'href="'.route('tournaments.bracket', $tournament).'"',
                'href="'.route('tournaments.results', $tournament).'"',
            ], false);
    }

    public function test_ranking_viewers_open_the_live_rankings_instead_of_an_empty_bracket(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => [
                'type' => RankingType::RACING_ROBOT->value,
                'attempts' => 2,
                'comparator' => 'BEST_TIME_LOWER',
            ],
        ]);
        $participant = Participant::factory()->create([
            'tournament_id' => $tournament->id,
            'team_name' => 'Fast Bot',
        ]);
        app(RankingService::class)->saveAttempt($tournament, $participant, 1, '12.34');
        $publicParameter = ['tournament' => $tournament->public_token];

        $this->get(route('tournaments.show', $tournament))
            ->assertRedirect(route('tournaments.results', $tournament));
        $this->get(route('public.tournaments.show', $publicParameter))
            ->assertRedirect(route('public.tournaments.results', $publicParameter));
        $this->get(route('public.tournaments.bracket', $publicParameter))
            ->assertRedirect(route('public.tournaments.results', $publicParameter));

        $this->get(route('public.tournaments.results', $publicParameter))
            ->assertOk()
            ->assertSee(__('ui.live_rankings'))
            ->assertSee('Fast Bot')
            ->assertSee('12.34 s')
            ->assertDontSee('<nav class="viewer-only-nav"', false);
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

    public function test_live_pages_detect_database_changes_without_manual_refresh(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $stage = Stage::factory()->create(['tournament_id' => $tournament->id]);
        $participantA = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $participantB = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $publicRouteParameter = ['tournament' => $tournament->public_token];

        $this->get(route('public.tournaments.bracket', $publicRouteParameter))
            ->assertOk()
            ->assertSee('data-live-bracket', false)
            ->assertSee('data-refresh-target="[data-live-bracket]"', false)
            ->assertSee('data-live-state-url="'.route('public.tournaments.live-state', $publicRouteParameter).'"', false);
        $this->get(route('public.tournaments.results', $publicRouteParameter))
            ->assertOk()
            ->assertSee('data-refresh-target="[data-live-results]"', false);
        $this->get(route('public.tournaments.matches', $publicRouteParameter))
            ->assertOk()
            ->assertSee('data-refresh-target="[data-live-matches]"', false);
        $this->get(route('tournaments.overview', $tournament))
            ->assertOk()
            ->assertSee('data-live-state-url="'.route('tournaments.live-state', $tournament).'"', false);

        $firstVersion = $this->getJson(route('public.tournaments.live-state', $publicRouteParameter))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->json('version');

        $match = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'stage_id' => $stage->id,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'status' => MatchStatus::READY,
            'is_bye' => false,
        ]);

        $secondVersion = $this->getJson(route('public.tournaments.live-state', $publicRouteParameter))
            ->assertOk()
            ->json('version');

        $this->assertNotSame($firstVersion, $secondVersion);

        $match->forceFill(['score_a' => '2', 'score_b' => '1', 'synced_at' => now()->addSecond()])->save();
        $thirdVersion = $this->getJson(route('public.tournaments.live-state', $publicRouteParameter))
            ->assertOk()
            ->json('version');

        $this->assertNotSame($secondVersion, $thirdVersion);
    }
}
