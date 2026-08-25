<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeedingMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStructure;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tournament> */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'competition' => 'EasyKids Robotics Competition',
            'division' => fake()->randomElement(['Junior', 'Senior', 'Open']),
            'structure' => TournamentStructure::STANDARD,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'seeding_method' => SeedingMethod::MANUAL,
            'status' => TournamentStatus::DRAFT,
            'participant_count' => 0,
            'source_created_at' => now(),
            'source_updated_at' => now(),
            'synced_at' => now(),
        ];
    }
}
