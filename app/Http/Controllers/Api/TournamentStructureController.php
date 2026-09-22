<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\StageType;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\StageAdvancementRule;
use App\Models\StageGroup;
use App\Models\StageGroupParticipant;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TournamentStructureController extends Controller
{
    public function stages(Tournament $tournament): JsonResponse
    {
        $stages = $tournament->stages()
            ->with([
                'groups' => fn ($query) => $query->with(['participants.participant'])->orderBy('group_order'),
                'advancementRulesFrom',
            ])
            ->orderBy('stage_order')
            ->get();

        return $this->success($stages);
    }

    public function stage(Tournament $tournament, Stage $stage): JsonResponse
    {
        $this->assertStageOwner($tournament, $stage);

        return $this->success($stage->load([
            'groups' => fn ($query) => $query->with(['participants.participant'])->orderBy('group_order'),
            'advancementRulesFrom',
            'advancementRulesTo',
        ]));
    }

    public function groups(Tournament $tournament): JsonResponse
    {
        return $this->success($tournament->groups()
            ->with(['stage', 'participants.participant'])
            ->orderBy('stage_id')
            ->orderBy('group_order')
            ->get());
    }

    public function group(Tournament $tournament, StageGroup $group): JsonResponse
    {
        $this->assertGroupOwner($tournament, $group);

        return $this->success($group->load(['stage', 'participants.participant']));
    }

    public function groupStandings(Tournament $tournament, StageGroup $group): JsonResponse
    {
        $this->assertGroupOwner($tournament, $group);
        $assignments = $group->participants()->with('participant')->get();
        $rows = $assignments->mapWithKeys(fn (StageGroupParticipant $assignment): array => [
            (string) $assignment->participant_id => [
                'participant_id' => (string) $assignment->participant_id,
                'participant' => $assignment->participant,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'score_for' => '0.000000',
                'score_against' => '0.000000',
            ],
        ])->all();

        foreach ($tournament->matches()
            ->where('stage_group_id', $group->id)
            ->where('status', MatchStatus::FINISHED)
            ->get() as $match) {
            $participantAId = (string) $match->participant_a_id;
            $participantBId = (string) $match->participant_b_id;

            if (! isset($rows[$participantAId], $rows[$participantBId])) {
                continue;
            }

            $scoreA = (string) ($match->score_a ?? 0);
            $scoreB = (string) ($match->score_b ?? 0);
            $rows[$participantAId]['played']++;
            $rows[$participantBId]['played']++;
            $rows[$participantAId]['score_for'] = bcadd($rows[$participantAId]['score_for'], $scoreA, 6);
            $rows[$participantAId]['score_against'] = bcadd($rows[$participantAId]['score_against'], $scoreB, 6);
            $rows[$participantBId]['score_for'] = bcadd($rows[$participantBId]['score_for'], $scoreB, 6);
            $rows[$participantBId]['score_against'] = bcadd($rows[$participantBId]['score_against'], $scoreA, 6);
            $comparison = bccomp($scoreA, $scoreB, 6);

            if ($comparison > 0) {
                $rows[$participantAId]['wins']++;
                $rows[$participantBId]['losses']++;
            } elseif ($comparison < 0) {
                $rows[$participantBId]['wins']++;
                $rows[$participantAId]['losses']++;
            } else {
                $rows[$participantAId]['draws']++;
                $rows[$participantBId]['draws']++;
            }
        }

        $standings = array_values($rows);
        usort($standings, fn (array $a, array $b): int => $b['wins'] <=> $a['wins']
            ?: bccomp($b['score_for'], $a['score_for'], 6)
            ?: strcmp($a['participant_id'], $b['participant_id']));

        foreach ($standings as $index => &$standing) {
            $standing['rank_number'] = $index + 1;
            $standing['points'] = $standing['wins'] - $standing['losses'];
        }
        unset($standing);

        return $this->success($standings);
    }

    public function assignments(Tournament $tournament): JsonResponse
    {
        return $this->success($this->assignmentPayload($tournament));
    }

    public function updateAssignments(Request $request, Tournament $tournament): JsonResponse
    {
        if (! $this->structureEditable($tournament)) {
            return $this->error(__('ui.advanced_group_assignments_locked'), 422);
        }

        $stage = $this->groupStage($tournament);
        $groups = $stage->groups()->orderBy('group_order')->get();
        $groupIds = $groups->modelKeys();
        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['required', 'uuid', Rule::in($groupIds)],
        ]);

        $eligibleIds = $this->eligibleParticipants($tournament)->modelKeys();
        $submittedIds = array_map('strval', array_keys($data['assignments']));
        sort($eligibleIds);
        sort($submittedIds);

        if ($eligibleIds !== $submittedIds) {
            return $this->error(__('ui.advanced_group_assignments_incomplete'), 422);
        }

        foreach ($groups as $group) {
            $assignedCount = collect($data['assignments'])
                ->filter(fn (mixed $groupId): bool => (string) $groupId === (string) $group->id)
                ->count();

            if ($group->team_limit && $assignedCount > $group->team_limit) {
                return $this->error(__('ui.advanced_group_limit_exceeded', ['group' => $group->name]), 422);
            }
        }

        $this->persistAssignments($tournament, $stage, $data['assignments']);

        return $this->success($this->assignmentPayload($tournament));
    }

    public function randomizeAssignments(Tournament $tournament): JsonResponse
    {
        if (! $this->structureEditable($tournament)) {
            return $this->error(__('ui.advanced_group_assignments_locked'), 422);
        }

        $stage = $this->groupStage($tournament);
        $groups = $stage->groups()->orderBy('group_order')->get();
        $participantIds = $this->eligibleParticipants($tournament)->modelKeys();
        shuffle($participantIds);
        $assignments = [];
        $counts = $groups->mapWithKeys(fn (StageGroup $group): array => [(string) $group->id => 0])->all();

        while ($participantIds !== []) {
            $assigned = false;

            foreach ($groups as $group) {
                $groupId = (string) $group->id;

                if ($participantIds === [] || ($group->team_limit && $counts[$groupId] >= $group->team_limit)) {
                    continue;
                }

                $assignments[array_shift($participantIds)] = $groupId;
                $counts[$groupId]++;
                $assigned = true;
            }

            if (! $assigned) {
                return $this->error(__('ui.advanced_group_capacity_insufficient'), 422);
            }
        }

        $this->persistAssignments($tournament, $stage, $assignments);

        return $this->success($this->assignmentPayload($tournament));
    }

    public function advancementRules(Tournament $tournament): JsonResponse
    {
        return $this->success($tournament->advancementRules()
            ->with(['sourceStage', 'sourceGroup', 'targetStage'])
            ->orderBy('source_stage_id')
            ->orderBy('rule_order')
            ->get());
    }

    public function advancementRule(Tournament $tournament, StageAdvancementRule $rule): JsonResponse
    {
        abort_unless($rule->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));

        return $this->success($rule->load(['sourceStage', 'sourceGroup', 'targetStage']));
    }

    private function groupStage(Tournament $tournament): Stage
    {
        return $tournament->stages()
            ->where('stage_type', StageType::GROUP)
            ->orderBy('stage_order')
            ->firstOrFail();
    }

    /** @return Collection<int, Participant> */
    private function eligibleParticipants(Tournament $tournament): Collection
    {
        return $tournament->participants()
            ->whereIn('status', [ParticipantStatus::ACTIVE, ParticipantStatus::CHECKED_IN])
            ->orderBy('seed_number')
            ->orderBy('team_name')
            ->get();
    }

    /** @param array<string, string> $assignments */
    private function persistAssignments(Tournament $tournament, Stage $stage, array $assignments): void
    {
        DB::transaction(function () use ($tournament, $stage, $assignments): void {
            StageGroupParticipant::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage_id', $stage->id)
                ->delete();

            $slots = [];

            foreach ($assignments as $participantId => $groupId) {
                $slots[$groupId] = ($slots[$groupId] ?? 0) + 1;
                StageGroupParticipant::query()->create([
                    'tournament_id' => $tournament->id,
                    'stage_id' => $stage->id,
                    'group_id' => $groupId,
                    'participant_id' => $participantId,
                    'slot_number' => $slots[$groupId],
                    'source_created_at' => now(),
                ]);
            }

            $tournament->forceFill(['source_updated_at' => now(), 'synced_at' => now()])->save();
        }, 3);
    }

    /** @return array{stage: Stage, assignments: mixed} */
    private function assignmentPayload(Tournament $tournament): array
    {
        $stage = $this->groupStage($tournament);

        return [
            'stage' => $stage->load(['groups' => fn ($query) => $query->orderBy('group_order')]),
            'assignments' => StageGroupParticipant::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage_id', $stage->id)
                ->with(['group', 'participant'])
                ->orderBy('group_id')
                ->orderBy('slot_number')
                ->get(),
        ];
    }

    private function structureEditable(Tournament $tournament): bool
    {
        return in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true)
            && ! $tournament->matches()->exists();
    }

    private function assertStageOwner(Tournament $tournament, Stage $stage): void
    {
        abort_unless($stage->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));
    }

    private function assertGroupOwner(Tournament $tournament, StageGroup $group): void
    {
        abort_unless($group->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));
    }

    private function success(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['message' => $message]], $status);
    }
}
