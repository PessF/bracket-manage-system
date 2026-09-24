<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AdvancementRuleType;
use App\Enums\RankingType;
use App\Enums\SeedingMethod;
use App\Enums\StageSourceType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentStructure;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\AdvancedTournamentBuilderService;
use App\Services\MatchStandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function __construct(private readonly AdvancedTournamentBuilderService $advancedBuilder) {}

    public function index(Request $request, ?Event $event = null): JsonResponse
    {
        $request->validate([
            'participant' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:200'],
            'division' => ['nullable', 'string', 'max:200'],
            'event_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(TournamentStatus::class)],
            'format' => ['nullable', Rule::enum(TournamentFormat::class)],
            'structure' => ['nullable', Rule::enum(TournamentStructure::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $data = Tournament::query()->withCount(['participants', 'matches'])
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->string('event_id')))
            ->withParticipantSearch((string) $request->input('participant', ''))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('format'), fn ($query) => $query->where('format', $request->string('format')))
            ->when($request->filled('structure'), fn ($query) => $query->where('structure', $request->string('structure')))
            ->when($request->filled('division'), fn ($query) => $query->where('division', $request->string('division')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $search)->orWhere('competition', 'like', $search));
            })
            ->orderByRaw('display_order IS NULL')
            ->orderBy('display_order')
            ->orderByDesc('source_created_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))->withQueryString();

        return $this->success($data);
    }

    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        $tournament->load([
            'stages' => fn ($query) => $query->with([
                'groups' => fn ($groupQuery) => $groupQuery->with(['participants.participant'])->orderBy('group_order'),
                'advancementRulesFrom',
            ])->orderBy('stage_order'),
            'participants' => fn ($query) => $query->orderBy('seed_number')->orderBy('team_name'),
            'matches' => fn ($query) => $query->with(['participantA', 'participantB', 'winner'])->orderBy('match_number'),
            'standings.participant',
        ]);

        return $this->success($tournament);
    }

    public function participants(Request $request, Tournament $tournament): JsonResponse
    {
        $query = $tournament->participants()->with('members')
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $builder->where(fn ($nested) => $nested
                    ->where('team_name', 'like', $search)
                    ->orWhere('team_code', 'like', $search)
                    ->orWhere('school', 'like', $search));
            })
            ->orderBy('seed_number')
            ->orderBy('team_name');

        return $this->success($this->listResult($request, $query));
    }

    public function participant(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));

        return $this->success($participant->load(['members', 'standing', 'rankingAttempts']));
    }

    public function matches(Request $request, Tournament $tournament): JsonResponse
    {
        $query = $tournament->matches()->with(['stage', 'stageGroup', 'participantA', 'participantB', 'winner'])
            ->when($request->filled('stage_id'), fn ($builder) => $builder->where('stage_id', $request->string('stage_id')))
            ->when($request->filled('group_id'), fn ($builder) => $builder->where('stage_group_id', $request->string('group_id')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')))
            ->when($request->filled('bracket_type'), fn ($builder) => $builder->where('bracket_type', $request->string('bracket_type')))
            ->when($request->filled('round'), fn ($builder) => $builder->where('round_number', $request->integer('round')))
            ->orderBy('match_number');

        return $this->success($this->listResult($request, $query));
    }

    public function match(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        abort_unless($match->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));

        return $this->success($match->load(['participantA', 'participantB', 'winner', 'loser']));
    }

    public function standings(Request $request, Tournament $tournament): JsonResponse
    {

        return $this->success($this->listResult(
            $request,
            $tournament->standings()->with('participant')->orderByRaw('CASE WHEN rank_number = 0 THEN 1 ELSE 0 END')->orderBy('rank_number'),
        ));
    }

    public function standing(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));
        $standing = Standing::query()
            ->where('tournament_id', $tournament->id)
            ->where('participant_id', $participant->id)
            ->with('participant')
            ->firstOrFail();

        return $this->success($standing);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        if (($data['structure'] ?? TournamentStructure::STANDARD->value) === TournamentStructure::ADVANCED->value) {
            $data['format'] = $data['advanced_playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value;
        }
        $tournament = DB::transaction(function () use ($data): Tournament {
            $now = now();
            $isAdvanced = ($data['structure'] ?? TournamentStructure::STANDARD->value) === TournamentStructure::ADVANCED->value;
            $tournament = Tournament::query()->create($data + [
                'status' => TournamentStatus::DRAFT,
                'structure' => $isAdvanced ? TournamentStructure::ADVANCED : TournamentStructure::STANDARD,
                'participant_count' => 0,
                'advanced_config' => $isAdvanced ? $this->advancedConfig($data) : null,
                'ranking_config' => $this->rankingConfig($data),
                'round_robin_config' => $this->roundRobinConfig($data),
                'double_elimination_config' => $this->doubleEliminationConfig($data),
                'source_created_at' => $now,
                'source_updated_at' => $now,
                'synced_at' => $now,
            ]);
            $isAdvanced
                ? $this->createAdvancedBlueprint($tournament, $data)
                : $this->createStandardStage($tournament);

            return $tournament;
        });

        return $this->success($this->loadStructure($tournament), 201);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $data = $request->validate($this->rules(! $request->isMethod('put')));
        $structural = [
            'structure', 'format', 'seeding_method', 'ranking_attempts', 'ranking_type',
            'ranking_comparator', 'grand_final_matches', 'advanced_group_count',
            'advanced_group_limits', 'advanced_group_format', 'advanced_qualifiers_per_group',
            'advanced_playoff_format', 'advanced_third_place',
        ];
        $hasStructuralChanges = collect($structural)->contains(fn (string $key): bool => $request->exists($key));

        if ($hasStructuralChanges && (! $this->editable($tournament) || $tournament->matches()->exists())) {
            return $this->error(__('ui.structure_locked'), 422);
        }

        if ($hasStructuralChanges) {
            $configuration = [
                'format' => $data['format'] ?? $tournament->format->value,
                'structure' => $data['structure'] ?? $tournament->structure->value,
                'seeding_method' => $data['seeding_method'] ?? $tournament->seeding_method->value,
                'ranking_attempts' => $data['ranking_attempts'] ?? ($tournament->ranking_config['attempts'] ?? 2),
                'ranking_type' => $data['ranking_type'] ?? ($tournament->ranking_config['type'] ?? null),
                'ranking_comparator' => $data['ranking_comparator'] ?? ($tournament->ranking_config['comparator'] ?? 'BEST_SCORE_HIGHER'),
                'grand_final_matches' => $data['grand_final_matches'] ?? ($tournament->double_elimination_config['grand_final_matches'] ?? 2),
                'advanced_group_count' => $data['advanced_group_count'] ?? ($tournament->advanced_config['group_count'] ?? 2),
                'advanced_group_limits' => $data['advanced_group_limits'] ?? ($tournament->advanced_config['group_limits'] ?? []),
                'advanced_group_format' => $data['advanced_group_format'] ?? ($tournament->advanced_config['group_format'] ?? TournamentFormat::ROUND_ROBIN->value),
                'advanced_qualifiers_per_group' => $data['advanced_qualifiers_per_group'] ?? ($tournament->advanced_config['qualifiers_per_group'] ?? 1),
                'advanced_playoff_format' => $data['advanced_playoff_format'] ?? ($tournament->advanced_config['playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value),
                'advanced_third_place' => $data['advanced_third_place'] ?? ($tournament->advanced_config['third_place'] ?? false),
            ];
            if ($configuration['structure'] === TournamentStructure::ADVANCED->value) {
                $configuration['format'] = $configuration['advanced_playoff_format'];
                $data['format'] = $configuration['format'];
            }
            $data['advanced_config'] = $configuration['structure'] === TournamentStructure::ADVANCED->value
                ? $this->advancedConfig($configuration)
                : null;
            $data['ranking_config'] = $this->rankingConfig($configuration);
            $data['round_robin_config'] = $this->roundRobinConfig($configuration);
            $data['double_elimination_config'] = $this->doubleEliminationConfig($configuration);
        }

        unset(
            $data['ranking_attempts'], $data['ranking_type'], $data['ranking_comparator'], $data['grand_final_matches'],
            $data['advanced_group_count'], $data['advanced_group_limits'], $data['advanced_group_format'],
            $data['advanced_qualifiers_per_group'], $data['advanced_playoff_format'], $data['advanced_third_place'],
        );
        $tournament->fill($data + ['source_updated_at' => now(), 'synced_at' => now()])->save();
        if ($hasStructuralChanges && ! $tournament->matches()->exists()) {
            $tournament->stages()->delete();
            $tournament->structure === TournamentStructure::ADVANCED
                ? $this->createAdvancedBlueprint($tournament, $configuration)
                : $this->createStandardStage($tournament);
        }

        return $this->success($this->loadStructure($tournament->fresh()));
    }

    public function destroy(Tournament $tournament): JsonResponse
    {
        $tournament->delete();

        return $this->success(['deleted' => true]);
    }

    public function updateDisplayOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'uuid', 'distinct'],
        ]);
        $ids = array_values($data['order']);
        abort_unless(Tournament::query()->whereIn('id', $ids)->count() === count($ids), 422, __('ui.resource_not_found'));

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                Tournament::query()->whereKey($id)->update([
                    'display_order' => $index + 1,
                    'source_updated_at' => now(),
                    'synced_at' => now(),
                ]);
            }
        }, 3);

        return $this->success(['order' => $ids]);
    }

    public function updateShareLink(Request $request, Tournament $tournament): JsonResponse
    {
        $request->merge([
            'share_slug' => Str::lower(trim((string) $request->input('share_slug'))),
        ]);
        $data = $request->validate([
            'share_slug' => [
                'required',
                'string',
                'min:4',
                'max:36',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])$/',
                Rule::unique('external_tournaments', 'public_token')->ignore($tournament->id),
            ],
        ]);

        $tournament->forceFill([
            'public_token' => $data['share_slug'],
            'source_updated_at' => now(),
            'synced_at' => now(),
        ])->save();

        return $this->success(['viewer_url' => $tournament->publicShareUrl()]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'event_id' => ['sometimes', 'required', 'uuid', 'exists:events,id'],
            'name' => [$required, 'string', 'max:200'],
            'competition' => [$required, 'string', 'max:200'],
            'division' => [$required, 'string', 'max:200'],
            'competition_date' => ['sometimes', 'nullable', 'date'],
            'bracket_schedule_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'bracket_match_duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,240'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'structure' => ['sometimes', Rule::enum(TournamentStructure::class)],
            'format' => [$required, Rule::enum(TournamentFormat::class)],
            'seeding_method' => [$required, Rule::enum(SeedingMethod::class)],
            'ranking_attempts' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'ranking_type' => ['sometimes', 'nullable', Rule::enum(RankingType::class)],
            'ranking_comparator' => ['sometimes', 'nullable', Rule::in(['BEST_SCORE_HIGHER', 'BEST_TIME_LOWER'])],
            'grand_final_matches' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'advanced_group_count' => ['sometimes', 'nullable', 'integer', 'between:1,16'],
            'advanced_group_limits' => ['sometimes', 'nullable', 'array', 'max:16'],
            'advanced_group_limits.*' => ['nullable', 'integer', 'between:1,64'],
            'advanced_group_format' => ['sometimes', 'nullable', Rule::in([
                TournamentFormat::ROUND_ROBIN->value,
                TournamentFormat::SINGLE_ELIMINATION->value,
                TournamentFormat::DOUBLE_ELIMINATION->value,
            ])],
            'advanced_qualifiers_per_group' => ['sometimes', 'nullable', 'integer', 'between:1,16'],
            'advanced_playoff_format' => ['sometimes', 'nullable', Rule::in([
                TournamentFormat::SINGLE_ELIMINATION->value,
                TournamentFormat::DOUBLE_ELIMINATION->value,
            ])],
            'advanced_third_place' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function createAdvancedBlueprint(Tournament $tournament, array $data): void
    {
        $groupCount = (int) ($data['advanced_group_count'] ?? 2);
        $groupLimits = $this->advancedGroupLimits($data, $groupCount, $tournament->participants()->count());
        $qualifiers = (int) ($data['advanced_qualifiers_per_group'] ?? 1);
        $groups = collect(range(1, $groupCount))->map(fn (int $order): array => [
            'name' => 'Group '.chr(64 + $order),
            'order' => $order,
            'team_limit' => $groupLimits[$order - 1] ?? null,
        ])->all();

        $groupStage = $this->advancedBuilder->createGroupStage(
            $tournament,
            'Group Stage',
            1,
            TournamentFormat::from($data['advanced_group_format'] ?? TournamentFormat::ROUND_ROBIN->value),
            $groups,
            $qualifiers,
            array_sum(array_filter($groupLimits)) ?: null,
            ['qualifiers_per_group' => $qualifiers, 'group_limits' => $groupLimits],
        );

        $playoffStage = $this->advancedBuilder->createPlayoffStage(
            $tournament,
            'Playoff Stage',
            2,
            TournamentFormat::from($data['advanced_playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value),
            $groupStage,
            $groupCount * $qualifiers,
            ['third_place' => (bool) ($data['advanced_third_place'] ?? false)],
        );

        foreach ($groupStage->groups()->orderBy('group_order')->get() as $group) {
            $this->advancedBuilder->addAdvancementRule([
                'tournament_id' => $tournament->id,
                'source_stage_id' => $groupStage->id,
                'target_stage_id' => $playoffStage->id,
                'source_group_id' => $group->id,
                'rule_order' => $group->group_order,
                'rule_type' => AdvancementRuleType::TOP_N,
                'rank_from' => 1,
                'rank_to' => $qualifiers,
                'target_slot' => ($group->group_order - 1) * $qualifiers + 1,
                'config' => ['description' => 'Top '.$qualifiers.' from '.$group->name],
            ]);
        }
    }

    private function createStandardStage(Tournament $tournament): void
    {
        Stage::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Main Stage',
            'stage_order' => 1,
            'stage_type' => StageType::MAIN,
            'format' => $tournament->format,
            'status' => StageStatus::PENDING,
            'source_type' => StageSourceType::REGISTRATION,
            'source_created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function advancedConfig(array $data): array
    {
        $groupCount = (int) ($data['advanced_group_count'] ?? 2);

        return [
            'enabled' => true,
            'version' => 1,
            'group_count' => $groupCount,
            'group_limits' => $this->advancedGroupLimits($data, $groupCount),
            'group_format' => $data['advanced_group_format'] ?? TournamentFormat::ROUND_ROBIN->value,
            'qualifiers_per_group' => (int) ($data['advanced_qualifiers_per_group'] ?? 1),
            'playoff_format' => $data['advanced_playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value,
            'third_place' => (bool) ($data['advanced_third_place'] ?? false),
        ];
    }

    /** @param array<string, mixed> $data
     * @return list<int|null>
     */
    private function advancedGroupLimits(array $data, int $groupCount, int $participantCount = 0): array
    {
        $submitted = collect($data['advanced_group_limits'] ?? [])
            ->map(fn (mixed $limit): ?int => filled($limit) ? (int) $limit : null)
            ->values()
            ->all();

        if (count(array_filter($submitted)) > 0) {
            return array_pad(array_slice($submitted, 0, $groupCount), $groupCount, null);
        }

        if ($participantCount < 1) {
            return array_fill(0, $groupCount, null);
        }

        $base = intdiv($participantCount, $groupCount);
        $remainder = $participantCount % $groupCount;

        return collect(range(1, $groupCount))
            ->map(fn (int $order): int => $base + ($order <= $remainder ? 1 : 0))
            ->all();
    }

    private function loadStructure(Tournament $tournament): Tournament
    {
        return $tournament->load([
            'stages' => fn ($query) => $query->with([
                'groups' => fn ($groupQuery) => $groupQuery->orderBy('group_order'),
                'advancementRulesFrom',
            ])->orderBy('stage_order'),
        ]);
    }

    private function listResult(Request $request, mixed $query): mixed
    {
        if (! $request->filled('per_page')) {
            return $query->get();
        }

        return $query->paginate(min(100, max(1, $request->integer('per_page', 20))));
    }

    /** @param array<string, mixed> $data */
    private function rankingConfig(array $data): ?array
    {
        if ($data['format'] !== TournamentFormat::RANKING->value) {
            return null;
        }

        $type = RankingType::tryFrom((string) ($data['ranking_type'] ?? ''))
            ?? match ($data['ranking_comparator'] ?? null) {
                'BEST_SCORE_HIGHER' => RankingType::DRONE_MISSION,
                default => RankingType::RACING_ROBOT,
            };

        return [
            'type' => $type->value,
            'attempts' => (int) ($data['ranking_attempts'] ?? 2),
            'comparator' => $type === RankingType::RACING_ROBOT ? 'BEST_TIME_LOWER' : 'BEST_SCORE_HIGHER_THEN_TIME_LOWER',
        ];
    }

    /** @param array<string, mixed> $data */
    private function roundRobinConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::ROUND_ROBIN->value
            ? ['ranking' => MatchStandingsService::RANKING_RULE]
            : null;
    }

    /** @param array<string, mixed> $data */
    private function doubleEliminationConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::DOUBLE_ELIMINATION->value
            ? ['grand_final_matches' => (int) ($data['grand_final_matches'] ?? 2)]
            : null;
    }

    private function editable(Tournament $tournament): bool
    {
        return in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true);
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
