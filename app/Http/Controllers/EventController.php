<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'page' => ['nullable', 'integer', 'min:1']]);

        $events = Event::query()->withCount('competitions')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('starts_on')->orderBy('name')->orderBy('id')
            ->paginate(12)->withQueryString();

        return view('events.index', compact('events'));
    }

    public function show(Request $request, Event $event): View
    {
        return app(TournamentController::class)->index($request, $event);
    }

    public function create(): View
    {
        return view('events.form', ['event' => new Event]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = Event::create($request->validated());

        return redirect()->route('events.show', $event)->with('success', __('events.saved'));
    }

    public function edit(Event $event): View
    {
        return view('events.form', compact('event'));
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('events.show', $event)->with('success', __('events.saved'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        // The foreign key also enforces this restriction against concurrent writes.
        abort_if($event->competitions()->exists(), 409, __('events.not_empty'));
        $event->delete();

        return redirect()->route('events.index')->with('success', __('events.deleted'));
    }
}
