<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Services\BracketGenerator;
use App\Services\MatchResultService;
use App\Services\TournamentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_elimination_size_can_progress_to_completion_including_reset_finals(): void
    {
        foreach ([TournamentFormat::SINGLE_ELIMINATION, TournamentFormat::DOUBLE_ELIMINATION] as $format) {
            foreach ([2, 3, 5, 6, 8, 13, 22, 32] as $count) {
                $tournament = Tournament::factory()->create(['format' => $format, 'double_elimination_config' => ['grand_final_matches' => 2]]);
                Stage::factory()->create(['tournament_id' => $tournament->id, 'format' => $format]);
                Participant::factory()->count($count)->sequence(fn ($sequence) => ['seed_number' => $sequence->index + 1])
                    ->create(['tournament_id' => $tournament->id]);
                app(TournamentLifecycleService::class)->start($tournament);
                $played = 0;
                while ($tournament->matches()->whereNotIn('status', [MatchStatus::FINISHED, MatchStatus::DQ])->exists()) {
                    $next = $tournament->matches()->whereIn('status', [MatchStatus::READY, MatchStatus::LIVE])->orderBy('match_number')->first();
                    $this->assertNotNull($next, "{$format->value}, {$count} participants stalled after {$played} matches");
                    $this->assertNotSame($next->participant_a_id, $next->participant_b_id);
                    // Side B winning the first grand final exercises the reset.
                    app(MatchResultService::class)->confirm($next, 0, 1);
                    $this->assertLessThanOrEqual(2 * $count, ++$played);
                }
                $this->assertSame($format === TournamentFormat::SINGLE_ELIMINATION ? $count - 1 : 2 * $count - 1, $played);
                app(TournamentLifecycleService::class)->complete($tournament);
            }
        }
    }

    public function test_single_elimination_placeholder_byes_keep_their_advancement_edges(): void
    {
        $drafts = app(BracketGenerator::class)->generatePlaceholder(TournamentFormat::SINGLE_ELIMINATION, ['A1', 'B1', 'C1', 'D1', 'E1', 'F1']);
        $this->assertCount(7, $drafts);
        foreach ($drafts as $draft) {
            if ($draft['is_bye']) {
                $this->assertNotNull($draft['winner_next_key']);
                $this->assertNotNull($draft['winner_next_slot']);
            }
        }
    }

    public function test_three_entrants_do_not_create_an_impossible_bronze_match(): void
    {
        $drafts = app(BracketGenerator::class)->generatePlaceholder(TournamentFormat::SINGLE_ELIMINATION, ['A1', 'B1', 'C1'], true);
        $this->assertCount(3, $drafts);
        $this->assertNotContains('POTHIRD_PLACE', array_column($drafts, 'key'));
    }
}
