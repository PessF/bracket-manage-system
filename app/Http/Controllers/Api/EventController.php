<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1']]);

        return response()->json(['success' => true, 'data' => Event::query()
            ->withCount('competitions')->orderByDesc('starts_on')->orderBy('name')->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))]);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $event->loadCount('competitions')]);
    }

    public function competitions(Request $request, Event $event): JsonResponse
    {
        return app(TournamentController::class)->index($request, $event);
    }

    public function store(EventRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Event::create($request->validated())], 201);
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $event->update($request->validated());

        return $this->show($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        abort_if($event->competitions()->exists(), 409, __('events.not_empty'));
        $event->delete();

        return response()->json(['success' => true, 'data' => null]);
    }
}
