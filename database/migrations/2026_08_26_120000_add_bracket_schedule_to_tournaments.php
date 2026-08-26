<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->time('bracket_schedule_start_time')->nullable()->after('competition_date');
            $table->unsignedSmallInteger('bracket_match_duration_minutes')->nullable()->after('bracket_schedule_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropColumn(['bracket_schedule_start_time', 'bracket_match_duration_minutes']);
        });
    }
};