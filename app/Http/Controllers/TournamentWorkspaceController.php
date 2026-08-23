<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BracketType;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TournamentWorkspaceController extends Controller
{
    public function bracket(Tournament $tournament): View
    {
        $matches = $tournament->matches()->with([
            'participantA',
            'participantB',
            'winnerNextMatch',
            'loserNextMatch',
        ])->orderBy('match_number')->get()
            ->groupBy(fn ($match): string => $match->bracket_type->value);

        return view('tournaments.bracket', compact('tournament', 'matches'));
    }

    public function matches(Tournament $tournament): View
    {
        $matches = $tournament->matches()->with(['participantA', 'participantB', 'winner'])->orderBy('match_number')->get();
        $grandFinalRounds = $matches
            ->where('bracket_type', BracketType::GRAND_FINAL)
            ->values()
            ->mapWithKeys(fn ($match, int $index): array => [$match->id => $index + 1]);

        return view('tournaments.matches', compact('tournament', 'matches', 'grandFinalRounds'));
    }

    public function adminMatches(Tournament $tournament): RedirectResponse
    {
        return redirect()->route('tournaments.bracket', $tournament);
    }

    public function results(Tournament $tournament): View
    {
        $standings = $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number')->get();
        $participants = $tournament->participants()->with(['rankingAttempts' => fn ($query) => $query->orderBy('attempt_number')])->orderBy('seed_number')->get();

        return view('tournaments.results', compact('tournament', 'standings', 'participants'));
    }
}
