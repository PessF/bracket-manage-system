<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Scoped API adapters share the existing competition validation and lifecycle. */
class EventCompetitionController extends Controller
{
    public function __construct(private readonly TournamentController $competitions) {}

    public function show(Request $request, Event $event, Tournament $tournament): JsonResponse
    {
        $this->assertBelongsToEvent($event, $tournament);

        return $this->competitions->show($request, $tournament);
    }

    public function bracket(Request $request, Event $event, Tournament $tournament): JsonResponse
    {
        $this->assertBelongsToEvent($event, $tournament);

        return $this->competitions->matches($request, $tournament);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $request->merge(['event_id' => $event->id]);

        return $this->competitions->store($request);
    }

    public function update(Request $request, Event $event, Tournament $tournament): JsonResponse
    {
        $this->assertBelongsToEvent($event, $tournament);
        // Moving a competition is explicit through the flat /tournaments API.
        $request->merge(['event_id' => $event->id]);

        return $this->competitions->update($request, $tournament);
    }

    public function destroy(Event $event, Tournament $tournament): JsonResponse
    {
        $this->assertBelongsToEvent($event, $tournament);

        return $this->competitions->destroy($tournament);
    }

    private function assertBelongsToEvent(Event $event, Tournament $tournament): void
    {
        abort_unless($tournament->event_id === $event->id, 404);
    }
}
