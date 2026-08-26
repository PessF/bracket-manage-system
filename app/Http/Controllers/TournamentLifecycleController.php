<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentLifecycleService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class TournamentLifecycleController extends Controller
{
    public function __construct(private readonly TournamentLifecycleService $lifecycle) {}

    public function prepareBracket(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->prepareBracket($tournament), __('ui.bracket_prepared'));
    }

    public function randomizeParticipants(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->randomizeParticipants($tournament), __('ui.participants_randomized'));
    }

    public function start(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->start($tournament), __('ui.tournament_started'));
    }

    public function complete(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->complete($tournament), __('ui.tournament_completed'));
    }

    public function resetBracket(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->resetBracket($tournament), __('ui.bracket_reset'));
    }

    public function createPlayoff(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->createAdvancedPlayoff($tournament), __('ui.advanced_playoff_created'));
    }

    public function archive(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->archive($tournament), __('ui.tournament_archived'));
    }

    private function execute(callable $callback, string $message): RedirectResponse
    {
        try {
            $callback();

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }
    }
}
