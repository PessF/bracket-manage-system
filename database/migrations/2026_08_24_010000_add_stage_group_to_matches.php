<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_matches', function (Blueprint $table): void {
            $table->foreignUuid('stage_group_id')->nullable()->after('stage_id');
            $table->index('stage_group_id', 'idx_external_matches_stage_group');
            $table->foreign('stage_group_id', 'fk_external_matches_stage_group')
                ->references('id')
                ->on('external_stage_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_matches', function (Blueprint $table): void {
            $table->dropForeign('fk_external_matches_stage_group');
            $table->dropIndex('idx_external_matches_stage_group');
            $table->dropColumn('stage_group_id');
        });
    }
};
