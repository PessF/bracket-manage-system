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
            $table->json('double_elimination_config')->nullable()->after('round_robin_config');
        });
    }

    public function down(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropColumn('double_elimination_config');
        });
    }
};
