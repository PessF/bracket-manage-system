<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Participant> */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'team_name' => 'Team '.fake()->unique()->numerify('###'),
            'team_code' => strtoupper(fake()->unique()->lexify('???')),
            'school' => fake()->company(),
            'coach_name' => fake()->name(),
            'status' => ParticipantStatus::ACTIVE,
            'source_created_at' => now(),
            'synced_at' => now(),
        ];
    }
}
