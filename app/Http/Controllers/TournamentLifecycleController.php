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

    public function start(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->start($tournament), 'Tournament started and bracket generated.');
    }

    public function complete(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->complete($tournament), 'Tournament completed.');
    }

    public function archive(Tournament $tournament): RedirectResponse
    {
        return $this->execute(fn () => $this->lifecycle->archive($tournament), 'Tournament archived.');
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
