<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AdvancementRuleType;
use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\RankingType;
use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentStructure;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'version' => '1.0',
                'documentation' => [
                    'html' => url('/api/docs'),
                    'thai_manual' => url('/api/manual/th'),
                ],
                'authentication' => [
                    'type' => 'bearer',
                    'scope' => 'administrator',
                    'granular_scopes' => false,
                    'public_reads' => true,
                    'writes_require_admin' => true,
                ],
                'hierarchy' => ['events', 'competitions', 'brackets'],
                'formats' => TournamentFormat::values(),
                'structures' => TournamentStructure::values(),
                'ranking_types' => RankingType::values(),
                'seeding_methods' => SeedingMethod::values(),
                'tournament_statuses' => TournamentStatus::values(),
                'participant_statuses' => ParticipantStatus::values(),
                'stage_types' => StageType::values(),
                'stage_statuses' => StageStatus::values(),
                'match_statuses' => MatchStatus::values(),
                'bracket_types' => BracketType::values(),
                'advancement_rule_types' => AdvancementRuleType::values(),
                'limits' => [
                    'per_page_max' => 100,
                    'ranking_attempts_max' => 20,
                    'csv_rows_max' => 1000,
                    'bulk_participants_max' => 1000,
                    'advanced_groups_max' => 16,
                ],
            ],
        ]);
    }
}
