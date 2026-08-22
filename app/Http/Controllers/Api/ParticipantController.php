<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParticipantController extends Controller
{
    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        if (! $this->editable($tournament)) {
            return $this->error('The roster is locked after the tournament starts.', 422);
        }

        $data = $request->validate($this->rules());
        $participant = $tournament->participants()->create($data + [
            'status' => $data['status'] ?? ParticipantStatus::ACTIVE,
            'source_created_at' => now(),
            'synced_at' => now(),
        ]);
        $this->syncCount($tournament);

        return $this->success($participant, 201);
    }

    public function update(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->assertOwner($tournament, $participant);
        $rules = $this->rules(true);
        if (! $this->editable($tournament)) {
            unset($rules['seed_number'], $rules['status']);
        }
        $participant->fill($request->validate($rules) + ['synced_at' => now()])->save();

        return $this->success($participant->fresh());
    }

    public function destroy(Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->assertOwner($tournament, $participant);
        if (! $this->editable($tournament)) {
            return $this->error('The roster is locked after the tournament starts.', 422);
        }
        $participant->delete();
        $this->syncCount($tournament);

        return $this->success(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'team_name' => [$required, 'string', 'max:200'],
            'team_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'school' => ['sometimes', 'nullable', 'string', 'max:200'],
            'coach_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'seed_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', Rule::enum(ParticipantStatus::class)],
        ];
    }

    private function editable(Tournament $tournament): bool
    {
        return in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true);
    }

    private function assertOwner(Tournament $tournament, Participant $participant): void
    {
        abort_unless($participant->tournament_id === $tournament->id, 404);
    }

    private function syncCount(Tournament $tournament): void
    {
        $tournament->update(['participant_count' => $tournament->participants()->count(), 'source_updated_at' => now(), 'synced_at' => now()]);
    }

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['message' => $message]], $status);
    }
}
