<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class MatchResultController extends Controller
{
    public function __construct(private readonly MatchResultService $results) {}

    public function store(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404);
        $wasFinished = $match->status === MatchStatus::FINISHED;
        $data = $request->validate(['score_a' => ['required', 'numeric', 'min:0'], 'score_b' => ['required', 'numeric', 'min:0']]);
        try {
            $this->results->confirm($match, $data['score_a'], $data['score_b']);

            return back()->with('success', __($wasFinished ? 'ui.match_score_corrected' : 'ui.match_confirmed', ['number' => $match->match_number]));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }
    }
}
