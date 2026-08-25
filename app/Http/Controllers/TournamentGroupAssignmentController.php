<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StageType;
use App\Enums\TournamentStatus;
use App\Models\StageGroupParticipant;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentGroupAssignmentController extends Controller
{
    public function edit(Tournament $tournament): View
    {
        $groupStage = $tournament->stages()
            ->where('stage_type', StageType::GROUP)
            ->with(['groups' => fn ($query) => $query->with(['participants.participant'])->orderBy('group_order')])
            ->orderBy('stage_order')
            ->firstOrFail();

        $participants = $tournament->participants()->orderBy('seed_number')->orderBy('team_name')->get();
        $assignments = StageGroupParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage_id', $groupStage->id)
            ->pluck('group_id', 'participant_id');
        $locked = ! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true) || $tournament->matches()->exists();

        return view('tournaments.groups', compact('tournament', 'groupStage', 'participants', 'assignments', 'locked'));
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        if (! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true) || $tournament->matches()->exists()) {
            return back()->withErrors(__('ui.advanced_group_assignments_locked'));
        }

        $groupStage = $tournament->stages()
            ->where('stage_type', StageType::GROUP)
            ->with('groups')
            ->orderBy('stage_order')
            ->firstOrFail();

        $participantIds = $tournament->participants()->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $groupIds = $groupStage->groups->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*' => ['nullable', Rule::in($groupIds)],
        ]);

        $submittedGroups = collect($participantIds)->mapWithKeys(fn (string $participantId): array => [
            $participantId => $data['groups'][$participantId] ?? null,
        ]);

        if ($submittedGroups->contains(fn ($groupId): bool => blank($groupId))) {
            return back()->withErrors(__('ui.advanced_group_assignments_incomplete'))->withInput();
        }

        foreach ($groupStage->groups as $group) {
            if ($group->team_limit && $submittedGroups->filter(fn ($groupId): bool => (string) $groupId === (string) $group->id)->count() > $group->team_limit) {
                return back()->withErrors(__('ui.advanced_group_limit_exceeded', ['group' => $group->name]))->withInput();
            }
        }

        DB::transaction(function () use ($data, $groupStage, $participantIds, $tournament): void {
            StageGroupParticipant::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage_id', $groupStage->id)
                ->delete();

            $slotNumbers = [];
            foreach ($participantIds as $participantId) {
                $groupId = $data['groups'][$participantId] ?? null;
                if (! $groupId) {
                    continue;
                }

                $slotNumbers[$groupId] = ($slotNumbers[$groupId] ?? 0) + 1;
                StageGroupParticipant::query()->create([
                    'tournament_id' => $tournament->id,
                    'stage_id' => $groupStage->id,
                    'group_id' => $groupId,
                    'participant_id' => $participantId,
                    'slot_number' => $slotNumbers[$groupId],
                    'source_created_at' => now(),
                ]);
            }
        });

        return redirect()->route('tournaments.groups.edit', $tournament)->with('success', __('ui.advanced_group_assignments_saved'));
    }
}
