<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('external_tournaments')
            ->where('format', 'ROUND_ROBIN')
            ->update([
                'round_robin_config' => json_encode([
                    'ranking' => 'WINS_THEN_DRAWS_THEN_SCORE_DIFFERENCE',
                ], JSON_THROW_ON_ERROR),
            ]);
    }

    public function down(): void
    {
        DB::table('external_tournaments')
            ->where('format', 'ROUND_ROBIN')
            ->update([
                'round_robin_config' => json_encode([
                    'win_points' => 3,
                    'draw_points' => 1,
                    'loss_points' => 0,
                ], JSON_THROW_ON_ERROR),
            ]);
    }
};
