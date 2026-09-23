<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_and_api_search_members_and_teams_with_event_and_status_isolation(): void
    {
        $event = Event::factory()->create();
        $matching = Tournament::factory()->create(['event_id' => $event->id, 'status' => TournamentStatus::DRAFT]);
        $other = Tournament::factory()->create(['event_id' => $event->id]);
        $outside = Tournament::factory()->create();
        foreach ([$matching, $outside] as $tournament) {
            $team = Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => 'Orbit 100%_!']);
            $team->members()->create(['name' => 'Alice สมใจ']);
            $team->members()->create(['name' => 'Alice Two']);
        }
        foreach (['orbit', ' ALICE ', 'สมใจ', '%_!'] as $term) {
            $query = ['participant' => $term];
            $this->get(route('events.show', $event).'?'.http_build_query($query))
                ->assertOk()->assertViewHas('tournaments', fn ($rows) => $rows->pluck('id')->all() === [$matching->id]);
            $this->getJson('/api/events/'.$event->id.'/competitions?'.http_build_query($query))
                ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $matching->id);
        }
        $this->getJson('/api/events/'.$event->id.'/competitions?participant=Alice&status=COMPLETED')
            ->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/events/'.$event->id.'/competitions?participant=unknown')
            ->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/events/'.$event->id.'/competitions?participant=%20%20')
            ->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/tournaments?participant=Orbit')->assertOk()->assertJsonPath('data.total', 2);
    }

    public function test_literal_wildcards_pagination_and_validation(): void
    {
        $event = Event::factory()->create();
        foreach (['100%', '100%', '1000'] as $name) {
            $tournament = Tournament::factory()->create(['event_id' => $event->id]);
            Participant::factory()->create(['tournament_id' => $tournament->id, 'team_name' => $name]);
        }
        $url = '/api/events/'.$event->id.'/competitions';
        $response = $this->getJson($url.'?participant=100%25&per_page=1')->assertOk()->assertJsonPath('data.total', 2);
        $this->assertStringContainsString('participant=100%25', $response->json('data.next_page_url'));
        $this->getJson($url.'?participant[]=bad')->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['participant']]]);
        $this->getJson($url.'?participant='.str_repeat('a', 101))->assertUnprocessable();
    }

    public function test_bracket_exposes_escaped_member_names_for_client_search(): void
    {
        $this->seed();
        $tournament = Tournament::firstOrFail();
        $participant = $tournament->participants()->firstOrFail();
        $participant->members()->create(['name' => '<Alice> สมใจ']);
        $this->get(route('tournaments.bracket', $tournament))->assertOk()
            ->assertSee('data-bracket-search-input', false)
            ->assertSee('data-participant-search', false)
            ->assertSee('&lt;Alice&gt;', false)->assertDontSee('<Alice>', false);
    }
}
