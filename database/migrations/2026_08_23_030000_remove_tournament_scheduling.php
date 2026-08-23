<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('external_matches', 'scheduled_at')) {
            Schema::table('external_matches', function (Blueprint $table): void {
                if (Schema::hasIndex('external_matches', 'idx_external_matches_schedule')) {
                    $table->dropIndex('idx_external_matches_schedule');
                }

                $table->dropColumn('scheduled_at');
            });
        }

        $columns = collect([
            'schedule_start_time',
            'match_duration_minutes',
            'schedule_timezone',
            'schedule_delay_minutes',
        ])->filter(fn (string $column): bool => Schema::hasColumn('external_tournaments', $column))->all();

        if ($columns !== []) {
            Schema::table('external_tournaments', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->time('schedule_start_time')->nullable()->after('competition_date');
            $table->unsignedInteger('match_duration_minutes')->nullable()->after('schedule_start_time');
            $table->string('schedule_timezone', 64)->nullable()->after('match_duration_minutes');
            $table->integer('schedule_delay_minutes')->default(0)->after('schedule_timezone');
        });

        Schema::table('external_matches', function (Blueprint $table): void {
            $table->dateTime('scheduled_at', 3)->nullable()->after('loser_next_slot');
            $table->index(['tournament_id', 'scheduled_at'], 'idx_external_matches_schedule');
        });
    }
};
