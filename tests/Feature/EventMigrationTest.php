<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise DDL outside a transaction. This in-memory connection is
        // discarded with the application; older migrations need not roll back.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_migration_backfills_existing_competitions_without_changing_their_data(): void
    {
        $competition = Tournament::factory()->create();
        $participant = Participant::factory()->create(['tournament_id' => $competition->id]);
        $before = DB::table('external_tournaments')->where('id', $competition->id)->first();
        $migration = require database_path('migrations/2026_09_22_000000_add_events_to_competitions.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('external_tournaments', 'event_id'));
        $migration->up();
        $after = DB::table('external_tournaments')->where('id', $competition->id)->first();
        unset($before->event_id, $after->event_id);
        $this->assertEquals($before, $after);
        $this->assertModelExists($participant);
        $this->assertSame(1, Event::count());
        $this->assertSame('Existing competitions', $competition->fresh()->event->name);
    }
}
