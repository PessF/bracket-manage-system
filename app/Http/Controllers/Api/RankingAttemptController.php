<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\RankingAttempt;
use App\Models\Tournament;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RankingAttemptController extends Controller
{
    public function __construct(private readonly RankingService $ranking) {}

    public function index(Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->assertParticipantOwner($tournament, $participant);

        return $this->success($participant->rankingAttempts()->orderBy('attempt_number')->get());
    }

    public function show(Tournament $tournament, Participant $participant, int $attemptNumber): JsonResponse
    {
        $this->assertParticipantOwner($tournament, $participant);

        return $this->success($this->findAttempt($tournament, $participant, $attemptNumber));
    }

    public function destroy(Tournament $tournament, Participant $participant, int $attemptNumber): JsonResponse
    {
        $this->assertParticipantOwner($tournament, $participant);

        if ($tournament->format !== TournamentFormat::RANKING || $tournament->status !== TournamentStatus::LIVE) {
            return response()->json([
                'success' => false,
                'error' => ['message' => __('ui.ranking_live_only')],
            ], 422);
        }

        DB::transaction(function () use ($tournament, $participant, $attemptNumber): void {
            $this->findAttempt($tournament, $participant, $attemptNumber)->delete();
            $this->ranking->recompute($tournament);
            $tournament->forceFill(['source_updated_at' => now(), 'synced_at' => now()])->save();
        }, 3);

        return $this->success(['deleted' => true]);
    }

    private function findAttempt(Tournament $tournament, Participant $participant, int $attemptNumber): RankingAttempt
    {
        return RankingAttempt::query()
            ->where('tournament_id', $tournament->id)
            ->where('participant_id', $participant->id)
            ->where('attempt_number', $attemptNumber)
            ->firstOrFail();
    }

    private function assertParticipantOwner(Tournament $tournament, Participant $participant): void
    {
        abort_unless($participant->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));
    }

    private function success(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
