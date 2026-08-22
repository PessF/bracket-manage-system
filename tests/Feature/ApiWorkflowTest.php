<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_api_can_run_a_complete_single_elimination_tournament(): void
    {
        $token = str_repeat('t', 64);
        User::factory()->create([
            'role' => UserRole::ADMIN,
            'api_token_hash' => hash('sha256', $token),
        ]);

        $tournamentId = $this->withToken($token)->postJson('/api/tournaments', [
            'name' => 'API Workflow',
            'competition' => 'EasyKids',
            'division' => 'Junior',
            'format' => 'SINGLE_ELIMINATION',
            'seeding_method' => 'REGISTRATION_ORDER',
        ])->assertCreated()->json('data.id');

        $participantA = $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/participants", [
            'team_name' => 'Alpha',
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/participants", [
            'team_name' => 'Beta',
        ])->assertCreated();

        $this->withToken($token)->patchJson("/api/tournaments/{$tournamentId}/participants/{$participantA}", [
            'team_name' => 'Alpha Updated',
        ])->assertOk()->assertJsonPath('data.team_name', 'Alpha Updated');

        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/start")
            ->assertOk()->assertJsonPath('data.status', 'LIVE');
        $matchId = $this->getJson("/api/tournaments/{$tournamentId}/matches")
            ->assertOk()->json('data.0.id');

        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/matches/{$matchId}/result", [
            'score_a' => 3,
            'score_b' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'FINISHED');
        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/complete")
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/archive")
            ->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
    }
}
