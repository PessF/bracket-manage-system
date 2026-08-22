<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Services\TournamentLifecycleService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Tournament::query()->where('name', 'EasyKids 8-Team Double Elimination Demo')->exists()) {
            return;
        }

        $now = now();
        $tournament = Tournament::query()->create([
            'name' => 'EasyKids 8-Team Double Elimination Demo',
            'competition' => 'EasyKids Robotics Championship',
            'division' => 'Junior Open',
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'seeding_method' => SeedingMethod::MANUAL,
            'status' => TournamentStatus::READY,
            'participant_count' => 8,
            'competition_date' => now()->addWeek(),
            'venue' => 'EasyKids Arena',
            'notes' => 'Seeded demo tournament for local propagation testing.',
            'source_created_at' => $now,
            'source_updated_at' => $now,
            'synced_at' => $now,
        ]);

        Stage::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Main Bracket',
            'stage_order' => 1,
            'format' => TournamentFormat::DOUBLE_ELIMINATION,
            'status' => StageStatus::PENDING,
            'source_created_at' => $now,
        ]);

        $teams = [
            ['Robo Tigers', 'RBT'], ['Circuit Breakers', 'CBK'], ['Byte Benders', 'BYT'], ['Gear Guardians', 'GEA'],
            ['Code Comets', 'CMT'], ['Mecha Monkeys', 'MKM'], ['Sensor Squad', 'SNS'], ['Pixel Pioneers', 'PXL'],
        ];

        foreach ($teams as $index => [$name, $code]) {
            Participant::query()->create([
                'tournament_id' => $tournament->id,
                'team_name' => $name,
                'team_code' => $code,
                'school' => 'EasyKids Academy '.($index + 1),
                'coach_name' => 'Coach '.chr(65 + $index),
                'seed_number' => $index + 1,
                'status' => 'ACTIVE',
                'source_created_at' => $now->copy()->addSeconds($index),
                'synced_at' => $now,
            ]);
        }

        app(TournamentLifecycleService::class)->start($tournament);
    }
}
