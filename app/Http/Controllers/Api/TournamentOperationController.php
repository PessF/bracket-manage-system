<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Enums\RankingType;
use App\Enums\TournamentStatus;
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

    public function transition(Request $request, Tournament $tournament): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:LIVE,COMPLETED,ARCHIVED'],
        ]);

        return match ($data['status']) {
            TournamentStatus::LIVE->value => $this->start($tournament),
            TournamentStatus::COMPLETED->value => $this->complete($tournament),
            TournamentStatus::ARCHIVED->value => $this->archive($tournament),
        };
    }

    public function result(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404);
        $data = $request->validate([
            'score_a' => ['required', 'numeric', 'min:0'],
            'score_b' => ['required', 'numeric', 'min:0'],
        ]);

        if ($request->isMethod('put') && $match->status === MatchStatus::FINISHED
            && $this->sameScore($match->score_a, $data['score_a'])
            && $this->sameScore($match->score_b, $data['score_b'])) {
            return response()->json(['success' => true, 'data' => $match->fresh(['winner', 'loser'])]);
        }

        return $this->execute(fn () => $this->results->confirm($match, $data['score_a'], $data['score_b']));
    }

    public function attempt(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404);
        $rules = [
            'attempt_number' => ['required', 'integer', 'between:1,20'],
            'is_valid' => ['required', 'boolean'],
        ];

        $type = RankingType::tryFrom((string) ($tournament->ranking_config['type'] ?? ''));

        if ($type === RankingType::RACING_ROBOT) {
            $rules['attempt_value'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        } elseif ($type === RankingType::DRONE_MISSION) {
            $rules['manual_score'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
            $rules['auto_score'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
            $rules['attempt_time'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        } else {
            $rules['attempt_value'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        }

        $data = $request->validate($rules);

        return $this->execute(fn () => $this->ranking->saveAttempt(
            $tournament,
            $participant,
            (int) $data['attempt_number'],
            $data['attempt_value'] ?? null,
            (bool) $data['is_valid'],
            $data['manual_score'] ?? null,
            $data['auto_score'] ?? null,
            $data['attempt_time'] ?? null,
        ));
    }

    public function attemptAt(Request $request, Tournament $tournament, Participant $participant, int $attemptNumber): JsonResponse
    {
        $request->merge(['attempt_number' => $attemptNumber]);

        return $this->attempt($request, $tournament, $participant);
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

    private function sameScore(mixed $stored, mixed $submitted): bool
    {
        return $stored !== null
            && number_format((float) $stored, 6, '.', '') === number_format((float) $submitted, 6, '.', '');
    }
}
