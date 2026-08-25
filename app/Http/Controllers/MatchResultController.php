<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Enums\TournamentStructure;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class MatchResultController extends Controller
{
    public function __construct(private readonly MatchResultService $results) {}

    public function store(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404);
        $wasFinished = $match->status === MatchStatus::FINISHED;

        try {
            $data = $request->validate([
                'score_a' => ['required', 'numeric', 'min:0'],
                'score_b' => ['required', 'numeric', 'min:0'],
                'score_modal_match' => ['nullable', 'string'],
            ]);
            $hadPlayoffTeams = $tournament->structure === TournamentStructure::ADVANCED
                && $this->playoffHasTeams($tournament);
            $this->results->confirm($match, $data['score_a'], $data['score_b']);
            $createdPlayoff = $tournament->structure === TournamentStructure::ADVANCED
                && ! $hadPlayoffTeams
                && $this->playoffHasTeams($tournament);

            return back()->with('success', $createdPlayoff
                ? __('ui.match_confirmed_playoff_created', ['number' => $match->match_number])
                : __($wasFinished ? 'ui.match_score_corrected' : 'ui.match_confirmed', ['number' => $match->match_number]));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->validator)->withInput();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage())->withInput();
        }
    }

    private function playoffHasTeams(Tournament $tournament): bool
    {
        return $tournament->stages()
            ->where('stage_type', StageType::PLAYOFF)
            ->whereHas('matches', function ($query): void {
                $query->whereNotNull('participant_a_id')->orWhereNotNull('participant_b_id');
            })
            ->exists();
    }
}
