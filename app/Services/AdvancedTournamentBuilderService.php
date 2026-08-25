<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StageSourceType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStructure;
use App\Models\Stage;
use App\Models\StageAdvancementRule;
use App\Models\StageGroup;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

class AdvancedTournamentBuilderService
{
    /**
     * @param  list<array{name:string,order:int,team_limit?:int|null,config?:array<string,mixed>|null}>  $groups
     */
    public function createGroupStage(
        Tournament $tournament,
        string $name,
        int $order,
        TournamentFormat $format,
        array $groups,
        ?int $advanceCount = null,
        ?int $teamLimit = null,
        array $config = [],
    ): Stage {
        return DB::transaction(function () use ($tournament, $name, $order, $format, $groups, $advanceCount, $teamLimit, $config): Stage {
            $now = now();
            $tournament->forceFill([
                'structure' => TournamentStructure::ADVANCED,
                'advanced_config' => array_replace_recursive($tournament->advanced_config ?? [], [
                    'enabled' => true,
                    'version' => 1,
                ]),
                'source_updated_at' => $now,
                'synced_at' => $now,
            ])->save();

            $stage = Stage::query()->create([
                'tournament_id' => $tournament->id,
                'name' => $name,
                'stage_order' => $order,
                'stage_type' => StageType::GROUP,
                'format' => $format,
                'status' => StageStatus::PENDING,
                'source_type' => StageSourceType::REGISTRATION,
                'advance_count' => $advanceCount,
                'team_limit' => $teamLimit,
                'config' => $config,
                'source_created_at' => $now,
            ]);

            foreach ($groups as $index => $group) {
                StageGroup::query()->create([
                    'tournament_id' => $tournament->id,
                    'stage_id' => $stage->id,
                    'name' => $group['name'],
                    'group_order' => $group['order'] ?? ($index + 1),
                    'team_limit' => $group['team_limit'] ?? null,
                    'config' => $group['config'] ?? null,
                    'source_created_at' => $now,
                ]);
            }

            return $stage->refresh();
        });
    }

    public function createPlayoffStage(
        Tournament $tournament,
        string $name,
        int $order,
        TournamentFormat $format,
        Stage $sourceStage,
        ?int $teamLimit = null,
        array $config = [],
    ): Stage {
        return DB::transaction(function () use ($tournament, $name, $order, $format, $sourceStage, $teamLimit, $config): Stage {
            $now = now();
            $tournament->forceFill([
                'structure' => TournamentStructure::ADVANCED,
                'advanced_config' => array_replace_recursive($tournament->advanced_config ?? [], [
                    'enabled' => true,
                    'version' => 1,
                ]),
                'source_updated_at' => $now,
                'synced_at' => $now,
            ])->save();

            return Stage::query()->create([
                'tournament_id' => $tournament->id,
                'name' => $name,
                'stage_order' => $order,
                'stage_type' => StageType::PLAYOFF,
                'format' => $format,
                'status' => StageStatus::PENDING,
                'source_type' => StageSourceType::PREVIOUS_STAGE,
                'source_stage_id' => $sourceStage->id,
                'team_limit' => $teamLimit,
                'config' => $config,
                'source_created_at' => $now,
            ]);
        });
    }

    public function addAdvancementRule(StageAdvancementRule|array $rule): StageAdvancementRule
    {
        if ($rule instanceof StageAdvancementRule) {
            return $rule;
        }

        return StageAdvancementRule::query()->create($rule + [
            'source_created_at' => now(),
        ]);
    }
}
