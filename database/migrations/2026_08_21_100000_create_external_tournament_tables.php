<?php

declare(strict_types=1);

use App\Enums\BracketType;
use App\Enums\MatchOutcome;
use App\Enums\MatchSlot;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\SyncStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_tournaments', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('competition', 200);
            $table->string('division', 200);
            $table->enum('format', TournamentFormat::values());
            $table->enum('seeding_method', SeedingMethod::values());
            $table->enum('status', TournamentStatus::values());
            $table->unsignedInteger('participant_count')->default(0);
            $table->dateTime('competition_date', 3)->nullable();
            $table->string('venue', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('ranking_config')->nullable();
            $table->json('round_robin_config')->nullable();
            $table->dateTime('locked_at', 3)->nullable();
            $table->dateTime('started_at', 3)->nullable();
            $table->dateTime('completed_at', 3)->nullable();
            $table->dateTime('source_created_at', 3);
            $table->dateTime('source_updated_at', 3);
            $table->dateTime('synced_at', 3)->useCurrent();

            $table->index('status', 'idx_external_tournaments_status');
            $table->index('format', 'idx_external_tournaments_format');
            $table->index('competition_date', 'idx_external_tournaments_date');
        });

        Schema::create('external_stages', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id');
            $table->string('name', 200);
            $table->integer('stage_order');
            $table->enum('format', TournamentFormat::values());
            $table->enum('status', StageStatus::values());
            $table->integer('advance_count')->nullable();
            $table->json('config')->nullable();
            $table->dateTime('source_created_at', 3);

            $table->unique(
                ['tournament_id', 'stage_order'],
                'uq_external_stage_order',
            );
            $table->foreign('tournament_id', 'fk_external_stages_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
        });

        Schema::create('external_participants', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id');
            $table->string('team_name', 200);
            $table->string('team_code', 100)->nullable();
            $table->string('school', 200)->nullable();
            $table->string('coach_name', 200)->nullable();
            $table->integer('seed_number')->nullable();
            $table->enum('status', ParticipantStatus::values());
            $table->dateTime('source_created_at', 3);
            $table->dateTime('synced_at', 3)->useCurrent();

            $table->index('tournament_id', 'idx_external_participants_tournament');
            $table->index(
                ['tournament_id', 'seed_number'],
                'idx_external_participants_seed',
            );
            $table->foreign('tournament_id', 'fk_external_participants_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
        });

        Schema::create('external_participant_members', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->uuid('id')->primary();
            $table->foreignUuid('participant_id');
            $table->string('name', 200);
            $table->string('role_name', 100)->nullable();

            $table->index('participant_id', 'idx_external_members_participant');
            $table->foreign('participant_id', 'fk_external_members_participant')
                ->references('id')
                ->on('external_participants')
                ->cascadeOnDelete();
        });

        Schema::create('external_matches', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id');
            $table->foreignUuid('stage_id');
            $table->integer('match_number');
            $table->enum('bracket_type', BracketType::values());
            $table->integer('round_number');
            $table->enum('status', MatchStatus::values());
            $table->boolean('is_bye')->default(false);
            $table->foreignUuid('participant_a_id')->nullable();
            $table->string('participant_a_label', 255);
            $table->foreignUuid('participant_a_source_match_id')->nullable();
            $table->enum('participant_a_source_outcome', MatchOutcome::values())->nullable();
            $table->foreignUuid('participant_b_id')->nullable();
            $table->string('participant_b_label', 255);
            $table->foreignUuid('participant_b_source_match_id')->nullable();
            $table->enum('participant_b_source_outcome', MatchOutcome::values())->nullable();
            $table->decimal('score_a', 18, 6)->nullable();
            $table->decimal('score_b', 18, 6)->nullable();
            $table->foreignUuid('winner_id')->nullable();
            $table->foreignUuid('loser_id')->nullable();
            $table->foreignUuid('winner_next_match_id')->nullable();
            $table->enum('winner_next_slot', MatchSlot::values())->nullable();
            $table->foreignUuid('loser_next_match_id')->nullable();
            $table->enum('loser_next_slot', MatchSlot::values())->nullable();
            $table->dateTime('started_at', 3)->nullable();
            $table->dateTime('finished_at', 3)->nullable();
            $table->dateTime('synced_at', 3)->useCurrent();

            $table->unique(
                ['tournament_id', 'match_number'],
                'uq_external_match_number',
            );
            $table->index('tournament_id', 'idx_external_matches_tournament');
            $table->index('stage_id', 'idx_external_matches_stage');
            $table->index('status', 'idx_external_matches_status');
            $table->foreign('tournament_id', 'fk_external_matches_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('stage_id', 'fk_external_matches_stage')
                ->references('id')
                ->on('external_stages')
                ->cascadeOnDelete();
            $table->foreign('participant_a_id', 'fk_external_matches_participant_a')
                ->references('id')
                ->on('external_participants')
                ->nullOnDelete();
            $table->foreign('participant_b_id', 'fk_external_matches_participant_b')
                ->references('id')
                ->on('external_participants')
                ->nullOnDelete();
            $table->foreign('winner_id', 'fk_external_matches_winner')
                ->references('id')
                ->on('external_participants')
                ->nullOnDelete();
            $table->foreign('loser_id', 'fk_external_matches_loser')
                ->references('id')
                ->on('external_participants')
                ->nullOnDelete();
        });

        Schema::create('external_ranking_attempts', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->foreignUuid('tournament_id');
            $table->foreignUuid('participant_id');
            $table->integer('attempt_number');
            $table->decimal('attempt_value', 18, 6)->nullable();
            $table->boolean('is_valid')->default(true);
            $table->dateTime('synced_at', 3)->useCurrent();

            $table->primary(['tournament_id', 'participant_id', 'attempt_number']);
            $table->foreign('tournament_id', 'fk_external_attempts_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('participant_id', 'fk_external_attempts_participant')
                ->references('id')
                ->on('external_participants')
                ->cascadeOnDelete();
        });

        Schema::create('external_standings', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->foreignUuid('tournament_id');
            $table->foreignUuid('participant_id');
            $table->integer('rank_number')->nullable();
            $table->decimal('best_value', 18, 6)->nullable();
            $table->integer('played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);
            $table->decimal('score_for', 18, 6)->default(0);
            $table->decimal('score_against', 18, 6)->default(0);
            $table->decimal('score_difference', 18, 6)->default(0);
            $table->integer('points')->default(0);
            $table->json('format_data')->nullable();
            $table->dateTime('synced_at', 3)->useCurrent();

            $table->primary(['tournament_id', 'participant_id']);
            $table->index(
                ['tournament_id', 'rank_number'],
                'idx_external_standings_rank',
            );
            $table->foreign('tournament_id', 'fk_external_standings_tournament')
                ->references('id')
                ->on('external_tournaments')
                ->cascadeOnDelete();
            $table->foreign('participant_id', 'fk_external_standings_participant')
                ->references('id')
                ->on('external_participants')
                ->cascadeOnDelete();
        });

        Schema::create('external_sync_log', function (Blueprint $table): void {
            $this->configureTable($table);

            $table->id();
            $table->foreignUuid('tournament_id')->nullable();
            $table->enum('status', SyncStatus::values());
            $table->text('message')->nullable();
            $table->dateTime('started_at', 3)->useCurrent();
            $table->dateTime('finished_at', 3)->nullable();

            $table->index('tournament_id', 'idx_external_sync_tournament');
            $table->index('started_at', 'idx_external_sync_started');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_sync_log');
        Schema::dropIfExists('external_standings');
        Schema::dropIfExists('external_ranking_attempts');
        Schema::dropIfExists('external_matches');
        Schema::dropIfExists('external_participant_members');
        Schema::dropIfExists('external_participants');
        Schema::dropIfExists('external_stages');
        Schema::dropIfExists('external_tournaments');
    }

    private function configureTable(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';
    }
};
