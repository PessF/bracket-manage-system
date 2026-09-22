<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_viewer_lists_show_all_competitions(): void
    {
        $live = Tournament::factory()->create(['name' => 'Live Event', 'status' => TournamentStatus::LIVE]);
        $draft = Tournament::factory()->create(['name' => 'Secret Draft', 'status' => TournamentStatus::DRAFT]);
        $completed = Tournament::factory()->create(['name' => 'Past Event', 'status' => TournamentStatus::COMPLETED]);

        $this->get(route('tournaments.index'))
            ->assertOk()->assertSee($live->name)->assertSee($draft->name)->assertSee($completed->name)
            ->assertDontSee(route('tournaments.create'));

        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->actingAs($viewer)->get(route('tournaments.index'))
            ->assertOk()->assertSee($live->name)->assertSee($draft->name)->assertSee($completed->name)
            ->assertDontSee(route('tournaments.create'));
    }

    public function test_all_competition_states_are_available_to_viewers_and_admins(): void
    {
        $draft = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);
        $live = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);

        $this->get(route('tournaments.show', $draft))->assertRedirect(route('tournaments.bracket', $draft));
        $this->get(route('tournaments.show', $live))->assertRedirect(route('tournaments.bracket', $live));

        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->actingAs($viewer)->get(route('tournaments.show', $draft))->assertRedirect(route('tournaments.bracket', $draft));
        $this->actingAs($viewer)->get(route('tournaments.show', $live))->assertRedirect(route('tournaments.bracket', $live));

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)->get(route('tournaments.show', $draft))->assertOk();
    }

    public function test_share_link_opens_one_live_competition_and_stays_read_only(): void
    {
        $live = Tournament::factory()->create(['name' => 'Shared Live Event', 'status' => TournamentStatus::LIVE]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $shareUrl = $live->publicShareUrl();

        $this->get($shareUrl)
            ->assertOk()
            ->assertSee($live->name)
            ->assertSee('class="viewer-shell"', false)
            ->assertSee('class="viewer-event-head"', false)
            ->assertDontSee('class="tabs"', false)
            ->assertDontSee(__('ui.overview_participants'))
            ->assertDontSee(__('ui.match_list'))
            ->assertDontSee(route('tournaments.settings', $live));

        $this->actingAs($admin)->get($shareUrl)
            ->assertOk()
            ->assertDontSee(route('tournaments.settings', $live))
            ->assertDontSee(route('participants.store', $live));
    }

    public function test_minimal_viewer_is_localized_and_dark_only(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $live = Tournament::factory()->create(['name' => 'Mobile Final', 'status' => TournamentStatus::LIVE]);

        $this->post(route('locale.update', 'en'))->assertRedirect();
        $this->get($live->publicShareUrl())
            ->assertOk()
            ->assertSee(__('ui.viewer_bracket_help'))
            ->assertSee('data-theme="easykids"', false)
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('data-light-label', false)
            ->assertDontSee(__('ui.all_tournaments'))
            ->assertDontSee(__('ui.login'));
    }

    public function test_share_link_remains_available_for_completed_competitions(): void
    {
        $completed = Tournament::factory()->create(['status' => TournamentStatus::COMPLETED]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->get($completed->publicShareUrl())->assertOk();
    }

    public function test_each_competition_has_a_unique_private_share_token(): void
    {
        $first = Tournament::factory()->create();
        $second = Tournament::factory()->create();

        $this->assertNotNull($first->public_token);
        $this->assertNotSame($first->public_token, $second->public_token);
        $this->assertArrayNotHasKey('public_token', $first->toArray());
    }

    public function test_admin_can_open_a_competition_even_when_share_token_is_not_ready(): void
    {
        $draft = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);
        $draft->forceFill(['public_token' => null])->save();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->get(route('tournaments.show', $draft))
            ->assertOk()
            ->assertSee($draft->name)
            ->assertSee(__('ui.share_link_not_ready'))
            ->assertSee('php artisan migrate --force');
    }

    public function test_admin_can_replace_the_private_token_with_a_short_viewer_link(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $live = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $oldUrl = $live->publicShareUrl();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->patch(route('tournaments.share-link.update', $live), [
            'share_slug' => 'easykids-final-26',
        ])->assertSessionHasNoErrors()->assertRedirect(route('tournaments.show', $live));

        $this->assertSame('easykids-final-26', $live->refresh()->public_token);
        $this->get($oldUrl)->assertNotFound();
        $this->get('/view/easykids-final-26')->assertOk()->assertSee($live->name);

        $other = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $this->actingAs($admin)->patch(route('tournaments.share-link.update', $other), [
            'share_slug' => 'easykids-final-26',
        ])->assertSessionHasErrors('share_slug');
        $this->actingAs($admin)->patch(route('tournaments.share-link.update', $other), [
            'share_slug' => 'ab',
        ])->assertSessionHasErrors('share_slug');

        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->actingAs($viewer)->patch(route('tournaments.share-link.update', $live), [
            'share_slug' => 'viewer-cannot-change-this',
        ])->assertForbidden();
    }

    public function test_competition_api_public_and_admin_reads_include_all_states(): void
    {
        $live = Tournament::factory()->create(['name' => 'API Live', 'status' => TournamentStatus::LIVE]);
        $draft = Tournament::factory()->create(['name' => 'API Draft', 'status' => TournamentStatus::DRAFT]);

        $this->getJson('/api/tournaments')
            ->assertOk()->assertJsonFragment(['name' => $live->name])->assertJsonFragment(['name' => $draft->name]);
        $this->getJson('/api/tournaments/'.$draft->id)->assertOk();

        $token = str_repeat('z', 64);
        User::factory()->create([
            'role' => UserRole::ADMIN,
            'api_token_hash' => hash('sha256', $token),
        ]);

        $this->withToken($token)->getJson('/api/tournaments')
            ->assertOk()->assertJsonFragment(['name' => $live->name])->assertJsonFragment(['name' => $draft->name]);
        $this->withToken($token)->getJson('/api/tournaments/'.$draft->id)->assertOk();
    }
}
