<?php

declare(strict_types=1);

use App\Enums\AdvancementRuleType;
use App\Enums\StageSourceType;
use App\Enums\StageType;
use App\Enums\TournamentStructure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->enum('structure', TournamentStructure::values())
                ->default(TournamentStructure::STANDARD->value)
                ->after('division');
            $table->json('advanced_config')->nullable()->after('completed_at');
            $table->index('structure', 'idx_external_tournaments_structure');
        });

        Schema::table('external_stages', function (Blueprint $table): void {
            $table->enum('stage_type', StageType::values())
                ->default(StageType::MAIN->value)
                ->after('stage_order');
            $table->enum('source_type', StageSourceType::values())
                ->default(StageSourceType::REGISTRATION->value)
                ->after('status');
            $table->foreignUuid('source_stage_id')->nullable()->after('source_type');
            $table->integer('team_limit')->nullable()->after('advance_count');
            $table->index(['tournament_id', 'stage_type'], 'idx_external_stages_type');
            $table->foreign('source_stage_id', 'fk_external_stages_source_stage')
                ->references('id')
                ->on('external_stages')
                ->nullOnDelete();
        });

        Schema::create('external_stage_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id');
            $table->foreignUuid('stage_id');
            $table->string('name', 120);
            $table->integer('group_order');
            $table->integer('team_limit')->nullable();
            $table->json('config')->nullable();
            $table->dateTime('source_created_at', 3);

            $table->unique(['stage_id', 'group_order'], 'uq_external_stage_group_order');
            $table->index('tournament_id', 'idx_external_stage_groups_tournament');
            $table->foreign('tournament_id', 'fk_external_stage_groups_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('stage_id', 'fk_external_stage_groups_stage')
                ->references('id')
                ->on('external_stages')
                ->cascadeOnDelete();
        });

        Schema::create('external_stage_group_participants', function (Blueprint $table): void {
            $table->foreignUuid('tournament_id');
            $table->foreignUuid('stage_id');
            $table->foreignUuid('group_id');
            $table->foreignUuid('participant_id');
            $table->integer('slot_number')->nullable();
            $table->dateTime('source_created_at', 3);

            $table->primary(['group_id', 'participant_id'], 'pk_external_stage_group_participants');
            $table->unique(['stage_id', 'participant_id'], 'uq_external_stage_participant');
            $table->index('tournament_id', 'idx_external_group_participants_tournament');
            $table->foreign('tournament_id', 'fk_external_group_participants_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('stage_id', 'fk_external_group_participants_stage')
                ->references('id')
                ->on('external_stages')
                ->cascadeOnDelete();
            $table->foreign('group_id', 'fk_external_group_participants_group')
                ->references('id')
                ->on('external_stage_groups')
                ->cascadeOnDelete();
            $table->foreign('participant_id', 'fk_external_group_participants_participant')
                ->references('id')
                ->on('external_participants')
                ->cascadeOnDelete();
        });

        Schema::create('external_stage_advancement_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id');
            $table->foreignUuid('source_stage_id');
            $table->foreignUuid('source_group_id')->nullable();
            $table->foreignUuid('target_stage_id');
            $table->integer('rule_order');
            $table->enum('rule_type', AdvancementRuleType::values());
            $table->integer('rank_from')->nullable();
            $table->integer('rank_to')->nullable();
            $table->integer('target_slot')->nullable();
            $table->json('config')->nullable();
            $table->dateTime('source_created_at', 3);

            $table->index('tournament_id', 'idx_external_advancement_tournament');
            $table->index(['source_stage_id', 'rule_order'], 'idx_external_advancement_source_order');
            $table->foreign('tournament_id', 'fk_external_advancement_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('source_stage_id', 'fk_external_advancement_source_stage')
                ->references('id')
                ->on('external_stages')
                ->cascadeOnDelete();
            $table->foreign('source_group_id', 'fk_external_advancement_source_group')
                ->references('id')
                ->on('external_stage_groups')
                ->nullOnDelete();
            $table->foreign('target_stage_id', 'fk_external_advancement_target_stage')
                ->references('id')
                ->on('external_stages')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_stage_advancement_rules');
        Schema::dropIfExists('external_stage_group_participants');
        Schema::dropIfExists('external_stage_groups');

        Schema::table('external_stages', function (Blueprint $table): void {
            $table->dropForeign('fk_external_stages_source_stage');
            $table->dropIndex('idx_external_stages_type');
            $table->dropColumn(['stage_type', 'source_type', 'source_stage_id', 'team_limit']);
        });

        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropIndex('idx_external_tournaments_structure');
            $table->dropColumn(['structure', 'advanced_config']);
        });
    }
};
