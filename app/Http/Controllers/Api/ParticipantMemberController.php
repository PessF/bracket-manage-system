<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\ParticipantMember;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantMemberController extends Controller
{
    public function index(Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->assertParticipantOwner($tournament, $participant);

        return $this->success($participant->members()->orderBy('name')->get());
    }

    public function show(Tournament $tournament, Participant $participant, ParticipantMember $member): JsonResponse
    {
        $this->assertOwners($tournament, $participant, $member);

        return $this->success($member);
    }

    public function store(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->assertParticipantOwner($tournament, $participant);
        $member = $participant->members()->create($request->validate($this->rules()));
        $this->touch($tournament, $participant);

        return $this->success($member, 201);
    }

    public function update(Request $request, Tournament $tournament, Participant $participant, ParticipantMember $member): JsonResponse
    {
        $this->assertOwners($tournament, $participant, $member);
        $member->fill($request->validate($this->rules(! $request->isMethod('put'))))->save();
        $this->touch($tournament, $participant);

        return $this->success($member->fresh());
    }

    public function destroy(Tournament $tournament, Participant $participant, ParticipantMember $member): JsonResponse
    {
        $this->assertOwners($tournament, $participant, $member);
        $member->delete();
        $this->touch($tournament, $participant);

        return $this->success(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:200'],
            'role_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    private function assertOwners(Tournament $tournament, Participant $participant, ParticipantMember $member): void
    {
        $this->assertParticipantOwner($tournament, $participant);
        abort_unless($member->participant_id === $participant->id, 404, __('ui.resource_not_found'));
    }

    private function assertParticipantOwner(Tournament $tournament, Participant $participant): void
    {
        abort_unless($participant->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));
    }

    private function touch(Tournament $tournament, Participant $participant): void
    {
        $participant->forceFill(['synced_at' => now()])->save();
        $tournament->forceFill(['source_updated_at' => now(), 'synced_at' => now()])->save();
    }

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
