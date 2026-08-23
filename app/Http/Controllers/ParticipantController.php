<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        if (! $this->editable($tournament)) {
            return $this->returnToAddParticipant($tournament)->withErrors(__('ui.roster_locked'));
        }

        try {
            $data = $this->validated($request);
        } catch (ValidationException $exception) {
            return $this->returnToAddParticipant($tournament)
                ->withErrors($exception->validator)
                ->withInput();
        }

        $tournament->participants()->create($data + [
            'status' => $data['status'] ?? ParticipantStatus::ACTIVE,
            'source_created_at' => now(), 'synced_at' => now(),
        ]);
        $this->syncCount($tournament);

        return $this->returnToAddParticipant($tournament)->with('success', __('ui.participant_added'));
    }

    public function update(Request $request, Tournament $tournament, Participant $participant): RedirectResponse
    {
        $this->assertOwner($tournament, $participant);
        $data = $this->editable($tournament)
            ? $this->validated($request)
            : $this->validatedIdentity($request);
        $participant->fill($data + ['synced_at' => now()])->save();

        return back()->with('success', __('ui.participant_updated'));
    }

    public function destroy(Tournament $tournament, Participant $participant): RedirectResponse
    {
        $this->assertOwner($tournament, $participant);
        if (! $this->editable($tournament)) {
            return back()->withErrors(__('ui.roster_locked'));
        }
        $participant->delete();
        $this->syncCount($tournament);

        return back()->with('success', __('ui.participant_removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'team_name' => ['required', 'string', 'max:200'], 'team_code' => ['nullable', 'string', 'max:100'],
            'school' => ['nullable', 'string', 'max:200'], 'coach_name' => ['nullable', 'string', 'max:200'],
            'seed_number' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', Rule::enum(ParticipantStatus::class)],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedIdentity(Request $request): array
    {
        return $request->validate([
            'team_name' => ['required', 'string', 'max:200'],
            'team_code' => ['nullable', 'string', 'max:100'],
            'school' => ['nullable', 'string', 'max:200'],
            'coach_name' => ['nullable', 'string', 'max:200'],
        ]);
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

    private function returnToAddParticipant(Tournament $tournament): RedirectResponse
    {
        return redirect()->to(route('tournaments.show', $tournament).'#add-participant');
    }
}
