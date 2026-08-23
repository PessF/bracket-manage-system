<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_visitors_need_a_share_link_and_cannot_open_admin_pages(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);

        $this->get(route('tournaments.index'))
            ->assertOk()
            ->assertDontSee($tournament->name)
            ->assertSee(__('ui.share_link_required'));
        $this->get(route('tournaments.show', $tournament))->assertNotFound();
        $this->get($tournament->publicShareUrl())->assertOk()->assertSee($tournament->name);
        $this->get(route('tournaments.create'))->assertRedirect(route('login'));
        $this->post(route('tournaments.start', $tournament))->assertRedirect(route('login'));
    }

    public function test_viewer_accounts_are_read_only_and_administrators_can_manage(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($viewer)->get(route('tournaments.index'))->assertOk()->assertDontSee(route('tournaments.create'));
        $this->actingAs($viewer)->get(route('tournaments.create'))->assertForbidden()->assertSee('ต้องใช้สิทธิ์ผู้ดูแลระบบ');
        $this->actingAs($admin)->get(route('tournaments.create'))->assertOk();
        $this->actingAs($admin)->get(route('tournaments.index'))
            ->assertOk()
            ->assertSee('หน้าจัดการสำหรับผู้ดูแล')
            ->assertSee(route('tournaments.create'))
            ->assertSee('data-theme="dark"', false)
            ->assertDontSee('data-theme-toggle', false);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee($viewer->email);
    }

    public function test_login_regenerates_the_session_and_rejects_bad_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('SecurePassword123!'),
            'role' => UserRole::ADMIN,
        ]);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => strtoupper($admin->email), 'password' => 'SecurePassword123!'])
            ->assertRedirect(route('tournaments.index'))
            ->assertSessionHas('success', 'เข้าสู่ระบบในฐานะผู้ดูแลแล้ว สามารถจัดการการแข่งขันได้ทุกรายการ');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_one_time_setup_requires_the_server_token_and_creates_an_admin(): void
    {
        config(['access.setup_token' => 'private-setup-token']);
        $payload = [
            'setup_token' => 'wrong',
            'name' => 'First Admin',
            'email' => 'first@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $this->post(route('admin.setup.store'), $payload)->assertSessionHasErrors('setup_token');
        $payload['setup_token'] = 'private-setup-token';
        $this->post(route('admin.setup.store'), $payload)->assertRedirect(route('tournaments.index'));

        $admin = User::query()->where('email', 'first@example.com')->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.setup'))->assertRedirect(route('login'));
    }

    public function test_api_competition_resources_require_an_admin_token(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $this->getJson('/api/tournaments/'.$tournament->id)->assertUnauthorized()->assertJsonPath('success', false);

        $payload = [
            'name' => 'API Tournament',
            'competition' => 'EasyKids',
            'division' => 'Junior',
            'format' => 'ROUND_ROBIN',
            'seeding_method' => 'REGISTRATION_ORDER',
        ];
        $this->postJson('/api/tournaments', $payload)->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/tournaments/not-a-real-id')->assertUnauthorized()->assertJsonPath('success', false);

        $viewerToken = str_repeat('v', 64);
        User::factory()->create(['role' => UserRole::VIEWER, 'api_token_hash' => hash('sha256', $viewerToken)]);
        $this->withToken($viewerToken)->postJson('/api/tournaments', $payload)->assertForbidden();

        $adminToken = str_repeat('a', 64);
        User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $adminToken)]);
        $this->withToken($adminToken)->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->withToken($adminToken)->getJson('/api/tournaments/not-a-real-id')
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->withToken($adminToken)->postJson('/api/tournaments', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API Tournament');
        $this->assertDatabaseHas('external_tournaments', ['name' => 'API Tournament']);
        $this->withToken($adminToken)->postJson('/api/tournaments', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error' => ['message', 'fields']]);
    }
}
