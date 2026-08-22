<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Participant;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_requires_a_complete_resource_and_patch_accepts_partial_data(): void
    {
        [$token, $tournament] = $this->adminContext();

        $this->withToken($token)->putJson('/api/tournaments/'.$tournament->id, ['name' => 'Only name'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields']]);
        $this->withToken($token)->patchJson('/api/tournaments/'.$tournament->id, ['name' => 'Patched name'])
            ->assertOk()->assertJsonPath('data.name', 'Patched name');
        $this->withToken($token)->patchJson('/api/tournaments/'.$tournament->id, [
            'format' => 'DOUBLE_ELIMINATION',
            'grand_final_matches' => 1,
        ])->assertOk()->assertJsonPath('data.double_elimination_config.grand_final_matches', 1);

        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);
        $this->withToken($token)->putJson("/api/tournaments/{$tournament->id}/participants/{$participant->id}", [])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/tournaments/{$tournament->id}/participants/{$participant->id}", ['school' => 'EasyKids'])
            ->assertOk()->assertJsonPath('data.school', 'EasyKids');
    }

    public function test_nested_resources_reject_ids_from_another_competition(): void
    {
        [$token, $tournament] = $this->adminContext();
        $another = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);
        $foreignParticipant = Participant::factory()->create(['tournament_id' => $another->id]);

        $this->withToken($token)
            ->getJson("/api/tournaments/{$tournament->id}/participants/{$foreignParticipant->id}")
            ->assertNotFound()->assertJsonPath('error.message', __('ui.resource_not_found'));
        $this->withToken($token)
            ->patchJson("/api/tournaments/{$tournament->id}/participants/{$foreignParticipant->id}", ['team_name' => 'Wrong'])
            ->assertNotFound();
    }

    public function test_individual_standing_is_available_for_a_live_competition(): void
    {
        $token = str_repeat('s', 64);
        User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $token)]);
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);
        Standing::query()->create([
            'tournament_id' => $tournament->id,
            'participant_id' => $participant->id,
            'rank_number' => 1,
            'points' => 3,
            'synced_at' => now(),
        ]);

        $this->withToken($token)->getJson("/api/tournaments/{$tournament->id}/standings/{$participant->id}")
            ->assertOk()->assertJsonPath('data.rank_number', 1)->assertJsonPath('data.participant.id', $participant->id);
    }

    public function test_thai_manual_documents_rest_resources_and_examples(): void
    {
        $this->get(route('api.docs'))
            ->assertOk()
            ->assertSee('คู่มือ REST API ภาษาไทย')
            ->assertSee('/api/tournaments/{id}/participants/{participant}')
            ->assertSee('/api/tournaments/{id}/share-link')
            ->assertSee('PATCH /status')
            ->assertSee('Accept-Language: th-TH');
    }

    /** @return array{string, Tournament} */
    private function adminContext(): array
    {
        $token = str_repeat('r', 64);
        User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $token)]);
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);

        return [$token, $tournament];
    }
}
