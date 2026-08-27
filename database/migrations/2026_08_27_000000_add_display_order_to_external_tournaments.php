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
            $table->unsignedInteger('display_order')->nullable()->after('status');
            $table->index('display_order', 'idx_external_tournaments_display_order');
        });
    }

    public function down(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropIndex('idx_external_tournaments_display_order');
            $table->dropColumn('display_order');
        });
    }
};