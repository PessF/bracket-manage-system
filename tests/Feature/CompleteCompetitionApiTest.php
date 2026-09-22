<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\UserRole;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteCompetitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_competition_can_be_created_and_operated_end_to_end(): void
    {
        $token = $this->adminToken();
        $tournamentId = $this->withToken($token)->postJson('/api/tournaments', [
            'name' => 'Advanced API Cup',
            'competition' => 'EasyKids',
            'division' => 'Open',
            'structure' => 'ADVANCED',
            'format' => 'ROUND_ROBIN',
            'seeding_method' => 'REGISTRATION_ORDER',
            'advanced_group_count' => 2,
            'advanced_group_limits' => [2, 2],
            'advanced_group_format' => 'ROUND_ROBIN',
            'advanced_qualifiers_per_group' => 1,
            'advanced_playoff_format' => 'SINGLE_ELIMINATION',
            'advanced_third_place' => false,
        ])->assertCreated()
            ->assertJsonPath('data.structure', 'ADVANCED')
            ->assertJsonPath('data.format', 'SINGLE_ELIMINATION')
            ->assertJsonCount(2, 'data.stages')
            ->json('data.id');

        $bulk = $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/participants/bulk", [
            'participants' => [
                ['team_name' => 'Alpha'],
                ['team_name' => 'Bravo'],
                ['team_name' => 'Charlie'],
                ['team_name' => 'Delta'],
            ],
        ])->assertCreated()->assertJsonCount(4, 'data');
        $participantIds = collect($bulk->json('data'))->pluck('id')->all();

        $groups = $this->withToken($token)->getJson("/api/tournaments/{$tournamentId}/groups")
            ->assertOk()->assertJsonCount(2, 'data')->json('data');
        $rules = $this->withToken($token)->getJson("/api/tournaments/{$tournamentId}/advancement-rules")
            ->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame('TOP_N', $rules[0]['rule_type']);

        $assignments = [
            $participantIds[0] => $groups[0]['id'],
            $participantIds[1] => $groups[0]['id'],
            $participantIds[2] => $groups[1]['id'],
            $participantIds[3] => $groups[1]['id'],
        ];
        $this->withToken($token)->putJson("/api/tournaments/{$tournamentId}/group-assignments", [
            'assignments' => $assignments,
        ])->assertOk()->assertJsonCount(4, 'data.assignments');

        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/prepare-bracket")
            ->assertOk()->assertJsonPath('data.status', 'READY');
        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/start")
            ->assertOk()->assertJsonPath('data.status', 'LIVE');

        $tournament = Tournament::query()->findOrFail($tournamentId);
        $groupStageId = $tournament->stages()->where('stage_type', 'GROUP')->value('id');
        $groupMatches = $tournament->matches()->where('stage_id', $groupStageId)->orderBy('match_number')->get();
        $this->assertCount(2, $groupMatches);

        foreach ($groupMatches as $match) {
            $this->withToken($token)->putJson("/api/tournaments/{$tournamentId}/matches/{$match->id}/result", [
                'score_a' => 2,
                'score_b' => 1,
            ])->assertOk()->assertJsonPath('data.status', 'FINISHED');
        }

        foreach ($groups as $group) {
            $this->withToken($token)->getJson("/api/tournaments/{$tournamentId}/groups/{$group['id']}/standings")
                ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.rank_number', 1);
        }

        $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/playoff")
            ->assertOk()->assertJsonPath('data.status', 'LIVE');
        $playoffStageId = $tournament->stages()->where('stage_type', 'PLAYOFF')->value('id');
        $playoffMatches = $tournament->matches()->where('stage_id', $playoffStageId)->orderBy('match_number')->get();
        $this->assertCount(1, $playoffMatches);

        foreach ($playoffMatches as $match) {
            $this->withToken($token)->putJson("/api/tournaments/{$tournamentId}/matches/{$match->id}/result", [
                'score_a' => 1,
                'score_b' => 1,
            ])->assertUnprocessable();
            $this->withToken($token)->putJson("/api/tournaments/{$tournamentId}/matches/{$match->id}/result", [
                'score_a' => 3,
                'score_b' => 0,
            ])->assertOk();
        }

        $this->withToken($token)->patchJson("/api/tournaments/{$tournamentId}/status", ['status' => 'COMPLETED'])
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_each_standard_match_format_can_be_completed_through_the_api(): void
    {
        $token = $this->adminToken();

        foreach (['ROUND_ROBIN', 'SINGLE_ELIMINATION', 'DOUBLE_ELIMINATION'] as $format) {
            $tournamentId = $this->createTournament($token, $format);
            $this->addParticipants($token, $tournamentId, 4);
            $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/start")->assertOk();
            $tournament = Tournament::query()->findOrFail($tournamentId);
            $safety = 0;

            while ($tournament->matches()->whereNotIn('status', [MatchStatus::FINISHED, MatchStatus::DQ])->exists()) {
                $match = $tournament->matches()
                    ->whereIn('status', [MatchStatus::LIVE, MatchStatus::READY])
                    ->whereNotNull('participant_a_id')
                    ->whereNotNull('participant_b_id')
                    ->orderBy('match_number')
                    ->firstOrFail();

                $this->withToken($token)->putJson("/api/tournaments/{$tournamentId}/matches/{$match->id}/result", [
                    'score_a' => 1,
                    'score_b' => 0,
                ])->assertOk();

                $this->assertLessThan(100, ++$safety, "{$format} did not finish");
            }

            $this->withToken($token)->patchJson("/api/tournaments/{$tournamentId}/status", ['status' => 'COMPLETED'])
                ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        }
    }

    public function test_both_ranking_types_support_attempt_crud_and_standings(): void
    {
        $token = $this->adminToken();

        $racingId = $this->createTournament($token, 'RANKING', [
            'ranking_type' => 'RACING_ROBOT',
            'ranking_attempts' => 1,
        ]);
        $racingParticipants = $this->addParticipants($token, $racingId, 2);
        $this->withToken($token)->postJson("/api/tournaments/{$racingId}/start")->assertOk();

        foreach ($racingParticipants as $index => $participantId) {
            $this->withToken($token)->putJson("/api/tournaments/{$racingId}/participants/{$participantId}/attempts/1", [
                'attempt_value' => 10 + $index,
                'is_valid' => true,
            ])->assertOk();
        }

        $this->withToken($token)->getJson("/api/tournaments/{$racingId}/participants/{$racingParticipants[0]}/attempts")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson("/api/tournaments/{$racingId}/participants/{$racingParticipants[0]}/attempts/1")
            ->assertOk()->assertJsonPath('data.attempt_value', '10.000000');
        $this->withToken($token)->deleteJson("/api/tournaments/{$racingId}/participants/{$racingParticipants[0]}/attempts/1")
            ->assertOk()->assertJsonPath('data.deleted', true);
        $this->withToken($token)->putJson("/api/tournaments/{$racingId}/participants/{$racingParticipants[0]}/attempts/1", [
            'attempt_value' => 9.5,
            'is_valid' => true,
        ])->assertOk();
        $this->withToken($token)->getJson("/api/tournaments/{$racingId}/standings")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->patchJson("/api/tournaments/{$racingId}/status", ['status' => 'COMPLETED'])
            ->assertOk();

        $droneId = $this->createTournament($token, 'RANKING', [
            'ranking_type' => 'DRONE_MISSION',
            'ranking_attempts' => 1,
        ]);
        $droneParticipants = $this->addParticipants($token, $droneId, 2);
        $this->withToken($token)->postJson("/api/tournaments/{$droneId}/start")->assertOk();

        foreach ($droneParticipants as $index => $participantId) {
            $this->withToken($token)->putJson("/api/tournaments/{$droneId}/participants/{$participantId}/attempts/1", [
                'manual_score' => 40 + $index,
                'auto_score' => 45,
                'attempt_time' => 75 + $index,
                'is_valid' => true,
            ])->assertOk()->assertJsonPath('data.attempt_value', sprintf('%d.000000', 85 + $index));
        }

        $this->withToken($token)->patchJson("/api/tournaments/{$droneId}/status", ['status' => 'COMPLETED'])
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_operational_and_topology_resources_enforce_tournament_ownership(): void
    {
        $token = $this->adminToken();
        $firstId = $this->createTournament($token, 'SINGLE_ELIMINATION');
        $secondId = $this->createTournament($token, 'SINGLE_ELIMINATION');
        $stageId = Tournament::query()->findOrFail($secondId)->stages()->value('id');

        $this->withToken($token)->getJson("/api/tournaments/{$firstId}/stages/{$stageId}")
            ->assertNotFound()->assertJsonPath('success', false);

        $this->addParticipants($token, $firstId, 3);
        $this->withToken($token)->postJson("/api/tournaments/{$firstId}/randomize-participants")
            ->assertOk()->assertJsonPath('data.seeding_method', 'MANUAL');
        $this->withToken($token)->postJson("/api/tournaments/{$firstId}/prepare-bracket")
            ->assertOk()->assertJsonPath('data.status', 'READY');
        $this->withToken($token)->postJson("/api/tournaments/{$firstId}/participants", ['team_name' => 'Too Late'])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/tournaments/{$firstId}", ['format' => 'ROUND_ROBIN'])
            ->assertUnprocessable();
        $this->withToken($token)->postJson("/api/tournaments/{$firstId}/reset-bracket")
            ->assertOk()->assertJsonPath('data.status', 'READY');
    }

    public function test_capabilities_members_and_filtered_collections_are_available(): void
    {
        $this->getJson('/api/capabilities')
            ->assertOk()
            ->assertJsonPath('data.authentication.scope', 'administrator')
            ->assertJsonPath('data.documentation.thai_manual', url('/api/manual/th'))
            ->assertJsonPath('data.structures.1', 'ADVANCED')
            ->assertJsonFragment(['DOUBLE_ELIMINATION']);

        $token = $this->adminToken();
        $tournamentId = $this->createTournament($token, 'ROUND_ROBIN');
        $secondTournamentId = $this->createTournament($token, 'SINGLE_ELIMINATION');
        $this->withToken($token)->patchJson('/api/tournaments/display-order', [
            'order' => [$secondTournamentId, $tournamentId],
        ])->assertOk()->assertJsonPath('data.order.0', $secondTournamentId);
        $this->withToken($token)->getJson('/api/tournaments?per_page=100')
            ->assertOk()->assertJsonPath('data.data.0.id', $secondTournamentId);
        $participants = $this->addParticipants($token, $tournamentId, 3);
        $participantId = $participants[0];
        $memberId = $this->withToken($token)->postJson("/api/tournaments/{$tournamentId}/participants/{$participantId}/members", [
            'name' => 'Student One',
            'role_name' => 'Driver',
        ])->assertCreated()->json('data.id');

        $this->withToken($token)->getJson("/api/tournaments/{$tournamentId}/participants/{$participantId}/members/{$memberId}")
            ->assertOk()->assertJsonPath('data.role_name', 'Driver');
        $this->withToken($token)->patchJson("/api/tournaments/{$tournamentId}/participants/{$participantId}/members/{$memberId}", [
            'role_name' => 'Programmer',
        ])->assertOk()->assertJsonPath('data.role_name', 'Programmer');
        $this->withToken($token)->getJson("/api/tournaments/{$tournamentId}/participants?search=Team%201&per_page=1")
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonCount(1, 'data.data');
        $this->withToken($token)->deleteJson("/api/tournaments/{$tournamentId}/participants/{$participantId}/members/{$memberId}")
            ->assertOk()->assertJsonPath('data.deleted', true);
    }

    public function test_thai_manual_documents_advanced_scoring_and_ranking_workflows(): void
    {
        $manual = file_get_contents(base_path('docs/API_MANUAL_TH.md'));

        $this->assertIsString($manual);
        $this->assertStringContainsString('คู่มือ REST API ระบบจัดการแข่งขัน EasyKids', $manual);
        $this->assertStringContainsString('/group-assignments', $manual);
        $this->assertStringContainsString('/matches/MATCH_UUID/result', $manual);
        $this->assertStringContainsString('DRONE_MISSION', $manual);
        $this->assertStringContainsString('/api/capabilities', $manual);

        $this->get('/api/docs')
            ->assertOk()
            ->assertSee('/api/tournaments/{id}/groups/{group}/standings')
            ->assertSee('advanced_group_count');
        $this->get('/api/manual/th')
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
            ->assertSee('คู่มือ REST API ระบบจัดการแข่งขัน EasyKids');
    }

    /** @param array<string, mixed> $extra */
    private function createTournament(string $token, string $format, array $extra = []): string
    {
        return $this->withToken($token)->postJson('/api/tournaments', array_merge([
            'name' => $format.' API',
            'competition' => 'EasyKids',
            'division' => 'Open',
            'structure' => 'STANDARD',
            'format' => $format,
            'seeding_method' => 'REGISTRATION_ORDER',
        ], $extra))->assertCreated()->json('data.id');
    }

    /** @return list<string> */
    private function addParticipants(string $token, string $tournamentId, int $count): array
    {
        $rows = collect(range(1, $count))->map(fn (int $number): array => [
            'team_name' => "Team {$number}",
        ])->all();

        return collect($this->withToken($token)
            ->postJson("/api/tournaments/{$tournamentId}/participants/bulk", ['participants' => $rows])
            ->assertCreated()->json('data'))
            ->pluck('id')
            ->all();
    }

    private function adminToken(): string
    {
        $token = bin2hex(random_bytes(32));
        User::factory()->create([
            'role' => UserRole::ADMIN,
            'api_token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }
}
