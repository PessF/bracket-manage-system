<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_assigns_its_competition_to_an_event(): void
    {
        $this->seed();
        $competition = Tournament::firstOrFail();
        $this->assertSame('EasyKids Robotics Championship', $competition->event->name);
        $this->assertSame(14, $competition->matches()->count());
    }

    public function test_public_and_admin_browse_the_same_event_scoped_competitions(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $competition = Tournament::factory()->create(['event_id' => $event->id]);
        $outside = Tournament::factory()->create(['event_id' => $other->id]);
        $this->get('/')->assertRedirect('/events');
        $this->get('/events')->assertOk()->assertSee($event->name)->assertDontSee(route('events.create'));
        $this->get(route('events.show', $event))->assertOk()->assertSee($competition->name)->assertDontSee($outside->name);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $this->get(route('events.show', $event))->assertOk()->assertSee($competition->name)->assertDontSee($outside->name)
            ->assertSee(route('events.edit', $event));
    }

    public function test_event_web_crud_requires_admin_and_preserves_competitions(): void
    {
        $event = Event::factory()->create();
        $this->post('/events', ['name' => 'New event'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => UserRole::VIEWER]));
        $this->put(route('events.update', $event), ['name' => 'Changed'])->assertForbidden();
        $this->delete(route('events.destroy', $event))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $this->post('/events', ['name' => 'New event'])->assertRedirect();
        $this->put(route('events.update', $event), ['name' => 'Changed'])->assertRedirect(route('events.show', $event));
        $competition = Tournament::factory()->create(['event_id' => $event->id]);
        $this->delete(route('events.destroy', $event))->assertStatus(409);
        $this->assertModelExists($competition);
        $this->delete(route('tournaments.destroy', $competition))->assertRedirect(route('events.show', $event));
        $this->delete(route('events.destroy', $event))->assertRedirect('/events');
        $this->assertModelMissing($event);
    }

    public function test_public_api_is_scoped_and_writes_require_admin_tokens(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $competition = Tournament::factory()->create(['event_id' => $event->id, 'status' => TournamentStatus::DRAFT]);
        $base = '/api/events/'.$event->id.'/competitions';
        $this->getJson('/api/events')->assertOk();
        $this->getJson($base)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson($base.'/'.$competition->id)->assertOk()->assertJsonPath('data.event_id', $event->id);
        $this->getJson($base.'/'.$competition->id.'/bracket')->assertOk();
        $this->getJson('/api/events/'.$other->id.'/competitions/'.$competition->id)->assertNotFound();
        $this->postJson('/api/events', ['name' => 'Unauthorized'])->assertUnauthorized();
        $this->deleteJson($base.'/'.$competition->id)->assertUnauthorized();
        $token = str_repeat('e', 40);
        User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $token)]);
        $this->withToken($token)->postJson('/api/events', ['name' => 'API event'])->assertCreated();
        $this->withToken($token)->postJson($base, [
            'event_id' => $other->id,
            'name' => 'Scoped create', 'competition' => 'Robotics', 'division' => 'Open',
            'format' => 'SINGLE_ELIMINATION', 'seeding_method' => 'REGISTRATION_ORDER',
        ])->assertCreated()->assertJsonPath('data.event_id', $event->id);
        $created = Tournament::where('name', 'Scoped create')->firstOrFail();
        $this->withToken($token)->deleteJson($base.'/'.$created->id)->assertOk();
        $this->withToken($token)->patchJson('/api/events/'.$event->id, ['name' => 'Updated'])->assertOk();
        $this->withToken($token)->deleteJson('/api/events/'.$event->id)->assertStatus(409);
        $this->withToken($token)->patchJson($base.'/'.$competition->id, ['name' => 'Updated competition'])->assertOk();
        $this->withToken($token)->deleteJson('/api/events/'.$other->id.'/competitions/'.$competition->id)->assertNotFound();
        $this->withToken($token)->deleteJson($base.'/'.$competition->id)->assertOk();
        $this->withToken($token)->deleteJson('/api/events/'.$event->id)->assertOk();
    }

    public function test_legacy_creates_receive_an_event_and_invalid_event_ids_are_rejected(): void
    {
        $competition = Tournament::factory()->create();
        $this->assertSame('Existing competitions', $competition->event->name);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $this->post('/tournaments', ['event_id' => 'bad'])->assertSessionHasErrors('event_id');
        $event = Event::factory()->create(['starts_on' => '2026-09-22']);
        $this->patch(route('events.update', $event), ['ends_on' => '2026-09-21'])->assertSessionHasErrors('ends_on');
    }
}
