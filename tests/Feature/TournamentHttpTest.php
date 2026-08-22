<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SeedingMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TournamentHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_competition_form_contains_dynamic_configuration_for_every_format(): void
    {
        $this->get(route('tournaments.create'))
            ->assertOk()
            ->assertSee('data-format-panel="RANKING"', false)
            ->assertSee('data-format-panel="ROUND_ROBIN"', false)
            ->assertSee('data-format-panel="SINGLE_ELIMINATION"', false)
            ->assertSee('data-format-panel="DOUBLE_ELIMINATION"', false)
            ->assertSee('updateFormatFields', false);
    }

    public function test_tournament_can_be_created_and_exposed_by_api(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->post('/tournaments', [
            'name' => 'HTTP Tournament', 'competition' => 'EasyKids', 'division' => 'Junior',
            'format' => 'ROUND_ROBIN', 'seeding_method' => 'REGISTRATION_ORDER',
            'win_points' => 3, 'draw_points' => 1, 'loss_points' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $tournament = Tournament::query()->firstOrFail();
        $response->assertRedirect(route('tournaments.show', $tournament));
        $this->get('/tournaments')->assertOk()->assertSee('HTTP Tournament');
        $this->getJson('/api/tournaments/'.$tournament->id)->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.name', 'HTTP Tournament');
        $this->assertDatabaseHas('external_stages', ['tournament_id' => $tournament->id]);
    }

    public function test_live_competition_and_participant_information_can_be_corrected_without_changing_bracket_settings(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tournament = Tournament::factory()->create([
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'seeding_method' => SeedingMethod::MANUAL,
            'status' => TournamentStatus::LIVE,
        ]);
        $participant = Participant::factory()->create([
            'tournament_id' => $tournament->id,
            'team_name' => 'Old Team Name',
            'seed_number' => 1,
        ]);

        $this->get(route('tournaments.settings', $tournament))
            ->assertOk()->assertSee('Competition settings')->assertSee('Delete competition');
        $this->put(route('tournaments.update', $tournament), [
            'name' => 'Corrected Competition Name',
            'competition' => 'EasyKids Championship',
            'division' => 'Open',
            'venue' => 'Main Arena',
        ])->assertSessionHasNoErrors()->assertRedirect(route('tournaments.settings', $tournament));

        $tournament->refresh();
        $this->assertSame('Corrected Competition Name', $tournament->name);
        $this->assertSame(TournamentFormat::DOUBLE_ELIMINATION, $tournament->format);
        $this->assertSame(SeedingMethod::MANUAL, $tournament->seeding_method);

        $this->put(route('participants.update', [$tournament, $participant]), [
            'team_name' => 'Corrected Team Name',
            'team_code' => 'CTN',
            'school' => 'New School',
            'coach_name' => 'New Coach',
        ])->assertSessionHasNoErrors();

        $participant->refresh();
        $this->assertSame('Corrected Team Name', $participant->team_name);
        $this->assertSame(1, $participant->seed_number);
    }

    public function test_competition_can_be_deleted_with_related_participants(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);
        $participant = Participant::factory()->create(['tournament_id' => $tournament->id]);

        $this->delete(route('tournaments.destroy', $tournament))
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseMissing('external_tournaments', ['id' => $tournament->id]);
        $this->assertDatabaseMissing('external_participants', ['id' => $participant->id]);
    }

    public function test_participants_can_be_imported_from_csv_with_members_and_duplicate_reporting(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);
        $csv = "Team Name,Team ID,School,Coach,Member 1,Member 2,Seed\n".
            "Alpha Robots,ALP,Alpha School,Coach A,Alice,Alex,1\n".
            "Alpha Robots,DUP,Duplicate School,Coach D,,,2\n".
            "Beta Bots,BET,Beta School,Coach B,Bob,,3\n";

        $response = $this->post(route('participants.import', $tournament), [
            'csv_file' => UploadedFile::fake()->createWithContent('participants.csv', $csv),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success')->assertSessionHas('import_errors');
        $this->assertSame(2, $tournament->participants()->count());
        $this->assertSame(3, $tournament->participants()->withCount('members')->get()->sum('members_count'));
        $this->assertSame(2, $tournament->refresh()->participant_count);
    }

    public function test_thai_language_mode_is_saved_in_the_session(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tournament = Tournament::factory()->create();

        $this->post(route('locale.update', 'th'))->assertRedirect();
        $this->get(route('tournaments.settings', $tournament))
            ->assertOk()
            ->assertSee('ตั้งค่าการแข่งขันและรูปแบบทัวร์นาเมนต์')
            ->assertSee('ลบการแข่งขัน');
    }
}
