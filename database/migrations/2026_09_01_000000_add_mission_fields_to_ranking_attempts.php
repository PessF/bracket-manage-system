<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_ranking_attempts', function (Blueprint $table): void {
            $table->decimal('manual_score', 18, 2)->nullable()->after('attempt_value');
            $table->decimal('auto_score', 18, 2)->nullable()->after('manual_score');
            $table->decimal('attempt_time', 18, 2)->nullable()->after('auto_score');
        });
    }

    public function down(): void
    {
        Schema::table('external_ranking_attempts', function (Blueprint $table): void {
            $table->dropColumn(['manual_score', 'auto_score', 'attempt_time']);
        });
    }
};
