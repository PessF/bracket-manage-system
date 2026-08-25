<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StageStatus;
use App\Enums\StageSourceType;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Models\Stage;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stage> */
class StageFactory extends Factory
{
    protected $model = Stage::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'name' => 'Main Stage',
            'stage_order' => 1,
            'stage_type' => StageType::MAIN,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'status' => StageStatus::PENDING,
            'source_type' => StageSourceType::REGISTRATION,
            'source_created_at' => now(),
        ];
    }
}
