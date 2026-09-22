<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite PRAGMA foreign_keys cannot change inside a transaction. Its ALTER
    // implementation rebuilds tables, which otherwise cascades into match data.
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => $this->migrateUp());
        } else {
            $this->migrateUp();
        }
    }

    private function migrateUp(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->foreignUuid('event_id')->nullable()->constrained('events')->restrictOnDelete();
        });

        // Preserve every existing competition, its identity, and all match results.
        if (DB::table('external_tournaments')->exists()) {
            $eventId = '00000000-0000-4000-8000-000000000001';
            DB::table('events')->insert([
                'id' => $eventId,
                'name' => 'Existing competitions',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('external_tournaments')->update(['event_id' => $eventId]);
        }

        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable(false)->change();
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => $this->migrateDown());
        } else {
            $this->migrateDown();
        }
    }

    private function migrateDown(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['event_id', 'status']);
            $table->dropColumn('event_id');
        });
        Schema::dropIfExists('events');
    }
};
