<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TournamentMatch> */
class TournamentMatchFactory extends Factory
{
    protected $model = TournamentMatch::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'stage_id' => Stage::factory(),
            'match_number' => fake()->unique()->numberBetween(1, 100000),
            'bracket_type' => BracketType::WINNERS,
            'round_number' => 1,
            'status' => MatchStatus::PENDING,
            'is_bye' => false,
            'participant_a_label' => 'TBD',
            'participant_b_label' => 'TBD',
            'synced_at' => now(),
        ];
    }
}
