<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_breadcrumbs_render_when_the_event_relation_is_missing(): void
    {
        $tournament = Tournament::factory()->create();
        $tournament->setRelation('event', null);
        $tournament->event_id = null;

        $this->view('tournaments._tabs', compact('tournament'))
            ->assertSee($tournament->name)
            ->assertSee(route('events.index'), false);
    }

    public function test_completed_bracket_preserves_rank_when_a_podium_participant_is_missing(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::COMPLETED]);
        $this->view('tournaments.bracket', [
            'tournament' => $tournament,
            'matches' => collect(),
            'podium' => collect([['rank' => 1, 'participant' => null, 'source' => null]]),
            'bracketViewGroups' => collect(),
            'activeBracketView' => 'all',
            'estimatedStartTimes' => [],
        ])->assertSee('podium-card rank-1', false)->assertSee(__('ui.participant_unavailable'));
    }

    public function test_ranking_results_preserve_scores_when_a_leader_participant_is_missing(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::COMPLETED, 'format' => TournamentFormat::RANKING]);
        $leader = (new Standing(['rank_number' => 1, 'best_value' => 12.34]))->setRelation('participant', null);
        $this->view('tournaments.results', [
            'tournament' => $tournament,
            'standings' => collect([$leader]),
            'participants' => collect(),
        ])->assertSee(__('ui.participant_unavailable'))->assertSee('12.34');
    }
}
