<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\RankingType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\RankingAttemptController;
use App\Models\Participant;
use App\Models\RankingAttempt;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\MatchProgressService;
use App\Services\MatchStandingsService;
use App\Services\ParticipantCsvImportService;
use App\Services\RankingService;
use App\Services\TournamentLifecycleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StabilityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_standings_queries_remain_bounded_as_the_field_grows(): void
    {
        foreach ([TournamentFormat::RANKING, TournamentFormat::ROUND_ROBIN] as $format) {
            $tournament = Tournament::factory()->create(['format' => $format]);
            Participant::factory()->count(10)->create(['tournament_id' => $tournament->id]);
            $service = app($format === TournamentFormat::RANKING ? RankingService::class : MatchStandingsService::class);
            $small = $this->queryCount(fn () => $service->recompute($tournament));
            Participant::factory()->count(90)->create(['tournament_id' => $tournament->id]);
            $large = $this->queryCount(fn () => $service->recompute($tournament));
            $this->assertSame($small, $large);
            $this->assertLessThanOrEqual(4, $large);
            $this->assertSame(100, $tournament->standings()->count());
            $this->assertIsArray($tournament->standings()->firstOrFail()->format_data);
        }
    }

    public function test_standings_upserts_cross_batch_boundaries_without_losing_rows(): void
    {
        $tournament = Tournament::factory()->create(['format' => TournamentFormat::RANKING]);
        Participant::factory()->count(205)->create(['tournament_id' => $tournament->id]);
        $service = app(RankingService::class);
        $service->recompute($tournament);
        $service->recompute($tournament);
        $this->assertSame(205, $tournament->standings()->count());
    }

    public function test_incomplete_legacy_drone_attempts_do_not_crash_or_outrank_complete_attempts(): void
    {
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::RANKING,
            'status' => TournamentStatus::LIVE,
            'ranking_config' => ['type' => RankingType::DRONE_MISSION->value, 'attempts' => 3],
        ]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);
        RankingAttempt::query()->create([
            'tournament_id' => $tournament->id, 'participant_id' => $participant->id,
            'attempt_number' => 1, 'attempt_value' => 100, 'is_valid' => true,
        ]);
        $service = app(RankingService::class);
        $service->recompute($tournament);
        $this->assertSame(0, $participant->standing()->firstOrFail()->rank_number);
        $service->saveAttempt($tournament, $participant, 2, null, true, 20, 30, 10);
        $standing = $participant->standing()->firstOrFail();
        $this->assertSame('50.000000', $standing->best_value);
        $this->assertSame(2, $standing->format_data['best_attempt_number']);
    }

    public function test_attempt_deletion_checks_current_status_instead_of_a_stale_bound_model(): void
    {
        $tournament = Tournament::factory()->create(['format' => TournamentFormat::RANKING, 'status' => TournamentStatus::LIVE]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);
        app(RankingService::class)->saveAttempt($tournament, $participant, 1, 10);
        Tournament::query()->whereKey($tournament->id)->update(['status' => TournamentStatus::COMPLETED]);
        $response = app(RankingAttemptController::class)->destroy($tournament, $participant, 1);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('external_ranking_attempts', 1);
    }

    public function test_match_progress_does_not_start_a_match_after_the_tournament_stops(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $participants = Participant::factory()->count(2)->create(['tournament_id' => $tournament->id]);
        $match = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id, 'status' => MatchStatus::READY,
            'participant_a_id' => $participants[0]->id, 'participant_b_id' => $participants[1]->id,
        ]);
        Tournament::query()->whereKey($tournament->id)->update(['status' => TournamentStatus::ARCHIVED]);
        $this->assertNull(app(MatchProgressService::class)->startNextReadyMatch($tournament));
        $this->assertSame(MatchStatus::READY, $match->refresh()->status);
    }

    public function test_prepared_web_rosters_reject_structure_changes_but_allow_identity_corrections(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $tournament = Tournament::factory()->create(['format' => TournamentFormat::SINGLE_ELIMINATION]);
        Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $tournament->format]);
        $participants = Participant::factory()->count(2)->create(['tournament_id' => $tournament->id]);
        app(TournamentLifecycleService::class)->prepareBracket($tournament);
        $participant = $participants[0];
        $matches = $tournament->matches()->get()->toArray();
        $this->post(route('participants.store', $tournament), ['team_name' => 'Late arrival'])->assertSessionHasErrors();
        $this->post(route('participants.bulk-store', $tournament), ['bulk_participants' => 'Late arrival'])->assertSessionHasErrors();
        $this->delete(route('participants.destroy', [$tournament, $participant]))->assertSessionHasErrors();
        $this->delete(route('participants.destroy-all', $tournament))->assertSessionHasErrors();
        $seed = $participant->refresh()->seed_number;
        $this->put(route('participants.update', [$tournament, $participant]), ['team_name' => 'Corrected name', 'seed_number' => 99])->assertRedirect();
        $this->assertSame($seed, $participant->refresh()->seed_number);
        $this->assertSame('Corrected name', $participant->team_name);
        $this->assertSame(2, $tournament->participants()->count());
        $this->assertSame($matches, $tournament->matches()->get()->toArray());
    }

    public function test_nullable_participant_status_has_safe_create_and_update_defaults(): void
    {
        $token = str_repeat('a', 64);
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $token)]);
        $this->actingAs($admin);
        $tournament = Tournament::factory()->create();
        $this->post(route('participants.store', $tournament), ['team_name' => 'Nullable', 'status' => null])->assertSessionHasNoErrors();
        $participant = $tournament->participants()->firstOrFail();
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status);
        $this->put(route('participants.update', [$tournament, $participant]), ['team_name' => 'Updated', 'status' => null])->assertSessionHasNoErrors();
        $this->withToken($token)->patchJson('/api/tournaments/'.$tournament->id.'/participants/'.$participant->id, ['status' => null])->assertOk();
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->refresh()->status);
    }

    public function test_blank_csv_header_returns_a_domain_error_instead_of_a_type_error(): void
    {
        $tournament = Tournament::factory()->create();
        $this->expectException(DomainException::class);
        app(ParticipantCsvImportService::class)->import($tournament, UploadedFile::fake()->createWithContent('blank.csv', "\nAlpha\n"));
    }

    public function test_csv_import_rechecks_roster_state_after_parsing(): void
    {
        $tournament = Tournament::factory()->create();
        Tournament::query()->whereKey($tournament->id)->update(['status' => TournamentStatus::LIVE]);
        try {
            app(ParticipantCsvImportService::class)->import($tournament, UploadedFile::fake()->createWithContent('teams.csv', "Team Name\nAlpha\n"));
            $this->fail('A stale roster must not be imported.');
        } catch (DomainException) {
            $this->assertSame(0, $tournament->participants()->count());
        }
    }

    private function queryCount(callable $operation): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
