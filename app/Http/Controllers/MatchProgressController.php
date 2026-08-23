<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchProgressService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class MatchProgressController extends Controller
{
    public function __construct(private readonly MatchProgressService $progress) {}

    public function store(Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404);

        try {
            $this->progress->markInProgress($match);

            return back()->with('success', __('ui.match_marked_in_progress', ['number' => $match->match_number]));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }
    }
}
