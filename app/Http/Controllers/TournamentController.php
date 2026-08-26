<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SeedingMethod;
use App\Enums\AdvancementRuleType;
use App\Enums\StageStatus;
use App\Enums\StageSourceType;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStructure;
use App\Enums\TournamentStatus;
use App\Models\Stage;
use App\Models\Tournament;
use App\Services\AdvancedTournamentBuilderService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user()?->isAdmin() ?? false;
        $canBrowseTournaments = true;

        try {
            $tournaments = Tournament::query()->withCount(['participants', 'matches'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->orderByDesc('source_created_at')->paginate(12)->withQueryString();
        } catch (QueryException $exception) {
            throw_unless(app()->isLocal(), $exception);

            $tournaments = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 12,
                currentPage: LengthAwarePaginator::resolveCurrentPage(),
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        }

        return view('tournaments.index', compact('tournaments', 'canBrowseTournaments'));
    }

    public function create(): View
    {
        return view('tournaments.form', ['tournament' => new Tournament]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $tournament = DB::transaction(function () use ($data): Tournament {
            $now = now();
            $isAdvanced = ($data['structure'] ?? TournamentStructure::STANDARD->value) === TournamentStructure::ADVANCED->value;
            $tournament = Tournament::query()->create($data + [
                'status' => TournamentStatus::DRAFT, 'participant_count' => 0,
                'structure' => $isAdvanced ? TournamentStructure::ADVANCED : TournamentStructure::STANDARD,
                'advanced_config' => $isAdvanced ? $this->advancedConfig($data) : null,
                'ranking_config' => $this->rankingConfig($data),
                'round_robin_config' => $this->roundRobinConfig($data),
                'double_elimination_config' => $this->doubleEliminationConfig($data),
                'source_created_at' => $now, 'source_updated_at' => $now, 'synced_at' => $now,
            ]);
            $isAdvanced ? $this->createAdvancedBlueprint($tournament, $data) : $this->createStandardStage($tournament);

            return $tournament;
        });

        return redirect()->route('tournaments.show', $tournament)->with('success', __('ui.tournament_created'));
    }

    public function show(Tournament $tournament): View
    {
        $tournament->loadCount(['participants', 'matches', 'rankingAttempts'])
            ->load(['participants' => fn ($query) => $query->orderBy('seed_number')->orderBy('team_name')]);

        return view('tournaments.show', compact('tournament'));
    }

    public function edit(Tournament $tournament): View
    {
        return view('tournaments.form', compact('tournament'));
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $this->validatedMetadata($request);

        if ($this->editable($tournament)) {
            $configuration = $this->validatedConfiguration($request);
            $data = array_merge($data, $configuration, [
                'advanced_config' => ($configuration['structure'] ?? TournamentStructure::STANDARD->value) === TournamentStructure::ADVANCED->value ? $this->advancedConfig($configuration) : null,
                'ranking_config' => $this->rankingConfig($configuration),
                'round_robin_config' => $this->roundRobinConfig($configuration),
                'double_elimination_config' => $this->doubleEliminationConfig($configuration),
            ]);
        }

        $tournament->fill($data + ['source_updated_at' => now(), 'synced_at' => now()])->save();

        if ($this->editable($tournament) && ! $tournament->matches()->exists()) {
            $tournament->stages()->delete();
            $tournament->structure === TournamentStructure::ADVANCED
                ? $this->createAdvancedBlueprint($tournament, $configuration)
                : $this->createStandardStage($tournament);
        }

        return redirect()->route('tournaments.settings', $tournament)->with('success', __('ui.settings_saved'));
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $tournament->delete();

        return redirect()->route('tournaments.index')->with('success', __('ui.tournament_deleted'));
    }

    public function updateShareLink(Request $request, Tournament $tournament): RedirectResponse
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

        return redirect()->route('tournaments.show', $tournament)->with('success', __('ui.share_link_updated'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return array_merge($this->validatedMetadata($request), $this->validatedConfiguration($request));
    }

    /** @return array<string, mixed> */
    private function validatedMetadata(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'], 'competition' => ['required', 'string', 'max:200'],
            'division' => ['required', 'string', 'max:200'], 'competition_date' => ['nullable', 'date'],
            'bracket_schedule_start_time' => ['nullable', 'date_format:H:i'],
            'bracket_match_duration_minutes' => ['nullable', 'integer', 'between:1,240'],
            'venue' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedConfiguration(Request $request): array
    {
        return $request->validate([
            'structure' => ['required', Rule::enum(TournamentStructure::class)],
            'format' => ['required', Rule::enum(TournamentFormat::class)],
            'seeding_method' => ['required', Rule::enum(SeedingMethod::class)],
            'ranking_attempts' => ['nullable', 'integer', 'between:1,20'],
            'ranking_comparator' => ['nullable', Rule::in(['BEST_SCORE_HIGHER', 'BEST_TIME_LOWER'])],
            'grand_final_matches' => ['nullable', 'integer', Rule::in([1, 2])],
            'advanced_group_count' => ['nullable', 'integer', 'between:1,16'],
            'advanced_group_limits' => ['nullable', 'array'],
            'advanced_group_limits.*' => ['nullable', 'integer', 'between:1,64'],
            'advanced_group_format' => ['nullable', Rule::enum(TournamentFormat::class)],
            'advanced_qualifiers_per_group' => ['nullable', 'integer', 'between:1,16'],
            'advanced_playoff_format' => ['nullable', Rule::enum(TournamentFormat::class)],
            'advanced_third_place' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createAdvancedBlueprint(Tournament $tournament, array $data): void
    {
        $builder = app(AdvancedTournamentBuilderService::class);
        $groupCount = (int) ($data['advanced_group_count'] ?? 2);
        $groupLimits = $this->advancedGroupLimits($data, $groupCount, $tournament->participants()->count());
        $qualifiers = (int) ($data['advanced_qualifiers_per_group'] ?? 1);
        $groups = collect(range(1, $groupCount))->map(fn (int $order): array => [
            'name' => 'Group '.chr(64 + $order),
            'order' => $order,
            'team_limit' => $groupLimits[$order - 1] ?? null,
        ])->all();

        $groupStage = $builder->createGroupStage(
            $tournament,
            'Group Stage',
            1,
            TournamentFormat::from($data['advanced_group_format'] ?? TournamentFormat::ROUND_ROBIN->value),
            $groups,
            $qualifiers,
            array_sum(array_filter($groupLimits)) ?: null,
            ['qualifiers_per_group' => $qualifiers, 'group_limits' => $groupLimits],
        );

        $playoffStage = $builder->createPlayoffStage(
            $tournament,
            'Playoff Stage',
            2,
            TournamentFormat::from($data['advanced_playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value),
            $groupStage,
            $groupCount * $qualifiers,
            ['third_place' => (bool) ($data['advanced_third_place'] ?? false)],
        );

        foreach ($groupStage->groups as $group) {
            $builder->addAdvancementRule([
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
        return [
            'enabled' => true,
            'version' => 1,
            'group_count' => (int) ($data['advanced_group_count'] ?? 2),
            'group_limits' => $this->advancedGroupLimits($data, (int) ($data['advanced_group_count'] ?? 2)),
            'group_format' => $data['advanced_group_format'] ?? TournamentFormat::ROUND_ROBIN->value,
            'qualifiers_per_group' => (int) ($data['advanced_qualifiers_per_group'] ?? 1),
            'playoff_format' => $data['advanced_playoff_format'] ?? TournamentFormat::SINGLE_ELIMINATION->value,
            'third_place' => (bool) ($data['advanced_third_place'] ?? false),
        ];
    }

    /** @param array<string, mixed> $data */
    private function advancedGroupLimits(array $data, int $groupCount, int $participantCount = 0): array
    {
        $submitted = collect($data['advanced_group_limits'] ?? [])
            ->map(fn ($limit) => filled($limit) ? (int) $limit : null)
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

    /** @param array<string, mixed> $data */
    private function rankingConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::RANKING->value
            ? ['attempts' => (int) ($data['ranking_attempts'] ?? 2), 'comparator' => $data['ranking_comparator'] ?? 'BEST_SCORE_HIGHER'] : null;
    }

    /** @param array<string, mixed> $data */
    private function roundRobinConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::ROUND_ROBIN->value
            ? ['ranking' => 'WINS_THEN_DRAWS_THEN_SCORE_DIFFERENCE']
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
}
