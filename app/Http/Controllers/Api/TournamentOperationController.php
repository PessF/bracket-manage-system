<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchResultService;
use App\Services\RankingService;
use App\Services\TournamentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TournamentOperationController extends Controller
{
    public function __construct(
        private readonly TournamentLifecycleService $lifecycle,
        private readonly MatchResultService $results,
        private readonly RankingService $ranking,
    ) {}

    public function start(Tournament $tournament): JsonResponse
    {
        return $this->execute(fn () => $this->lifecycle->start($tournament));
    }

    public function complete(Tournament $tournament): JsonResponse
    {
        return $this->execute(fn () => $this->lifecycle->complete($tournament));
    }

    public function archive(Tournament $tournament): JsonResponse
    {
        return $this->execute(fn () => $this->lifecycle->archive($tournament));
    }

    public function result(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404);
        $data = $request->validate([
            'score_a' => ['required', 'numeric', 'min:0'],
            'score_b' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->execute(fn () => $this->results->confirm($match, $data['score_a'], $data['score_b']));
    }

    public function attempt(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404);
        $data = $request->validate([
            'attempt_number' => ['required', 'integer', 'between:1,20'],
            'attempt_value' => ['nullable', 'numeric', 'min:0'],
            'is_valid' => ['required', 'boolean'],
        ]);

        return $this->execute(fn () => $this->ranking->saveAttempt(
            $tournament,
            $participant,
            (int) $data['attempt_number'],
            $data['attempt_value'] ?? null,
            (bool) $data['is_valid'],
        ));
    }

    private function execute(callable $callback): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $callback()]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'error' => ['message' => $exception->getMessage()]], 422);
        }
    }
}
