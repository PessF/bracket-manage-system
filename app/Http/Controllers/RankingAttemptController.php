<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Tournament;
use App\Services\RankingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RankingAttemptController extends Controller
{
    public function __construct(private readonly RankingService $ranking) {}

    public function store(Request $request, Tournament $tournament, Participant $participant): RedirectResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404);
        $data = $request->validate(['attempt_number' => ['required', 'integer', 'between:1,20'], 'attempt_value' => ['nullable', 'numeric', 'min:0'], 'is_valid' => ['nullable', 'boolean']]);
        try {
            $this->ranking->saveAttempt($tournament, $participant, (int) $data['attempt_number'], $data['attempt_value'] ?? null, $request->boolean('is_valid'));

            return back()->with('success', "Attempt {$data['attempt_number']} saved.");
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }
    }
}
