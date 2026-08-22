<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_viewer_lists_only_show_live_competitions(): void
    {
        $live = Tournament::factory()->create(['name' => 'Live Event', 'status' => TournamentStatus::LIVE]);
        $draft = Tournament::factory()->create(['name' => 'Secret Draft', 'status' => TournamentStatus::DRAFT]);
        $completed = Tournament::factory()->create(['name' => 'Past Event', 'status' => TournamentStatus::COMPLETED]);

        $this->get(route('tournaments.index'))
            ->assertOk()->assertSee($live->name)->assertDontSee($draft->name)->assertDontSee($completed->name);

        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->actingAs($viewer)->get(route('tournaments.index'))
            ->assertOk()->assertSee($live->name)->assertDontSee($draft->name)->assertDontSee($completed->name);
    }

    public function test_non_live_competitions_are_hidden_from_viewers_but_available_to_admins(): void
    {
        $draft = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);

        $this->get(route('tournaments.show', $draft))->assertNotFound();

        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->actingAs($viewer)->get(route('tournaments.show', $draft))->assertNotFound();

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
            ->assertSee(route('public.tournaments.bracket', ['tournament' => $live->public_token]))
            ->assertDontSee(route('tournaments.settings', $live));

        $this->actingAs($admin)->get($shareUrl)
            ->assertOk()
            ->assertDontSee(route('tournaments.settings', $live))
            ->assertDontSee(route('participants.store', $live));
    }

    public function test_share_link_is_unavailable_when_competition_is_not_live(): void
    {
        $completed = Tournament::factory()->create(['status' => TournamentStatus::COMPLETED]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->get($completed->publicShareUrl())->assertNotFound();
    }

    public function test_each_competition_has_a_unique_private_share_token(): void
    {
        $first = Tournament::factory()->create();
        $second = Tournament::factory()->create();

        $this->assertNotNull($first->public_token);
        $this->assertNotSame($first->public_token, $second->public_token);
        $this->assertArrayNotHasKey('public_token', $first->toArray());
    }

    public function test_public_api_only_reads_live_competitions_while_admin_token_reads_all(): void
    {
        $live = Tournament::factory()->create(['name' => 'API Live', 'status' => TournamentStatus::LIVE]);
        $draft = Tournament::factory()->create(['name' => 'API Draft', 'status' => TournamentStatus::DRAFT]);

        $this->getJson('/api/tournaments')
            ->assertOk()->assertJsonFragment(['name' => $live->name])->assertJsonMissing(['name' => $draft->name]);
        $this->getJson('/api/tournaments/'.$draft->id)->assertNotFound();

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
