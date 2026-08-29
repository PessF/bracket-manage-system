<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SeedingMethod;
use App\Enums\StageSourceType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentStructure;
use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchStandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Tournament::query()->withCount(['participants', 'matches'])
            ->when(! ($request->user()?->isAdmin() ?? false), fn ($query) => $query->where('status', TournamentStatus::LIVE))
            ->when(($request->user()?->isAdmin() ?? false) && $request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('format'), fn ($query) => $query->where('format', $request->string('format')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $search)->orWhere('competition', 'like', $search));
            })
            ->orderByDesc('source_created_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return $this->success($data);
    }

    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        $this->ensureVisible($request, $tournament);
        $tournament->load([
            'stages',
            'participants' => fn ($query) => $query->orderBy('seed_number')->orderBy('team_name'),
            'matches' => fn ($query) => $query->with(['participantA', 'participantB', 'winner'])->orderBy('match_number'),
            'standings.participant',
        ]);

        return $this->success($tournament);
    }

    public function participants(Request $request, Tournament $tournament): JsonResponse
    {
        $this->ensureVisible($request, $tournament);

        return $this->success($tournament->participants()->with('members')->orderBy('seed_number')->orderBy('team_name')->get());
    }

    public function participant(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->ensureVisible($request, $tournament);
        abort_unless($participant->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));

        return $this->success($participant->load(['members', 'standing', 'rankingAttempts']));
    }

    public function matches(Request $request, Tournament $tournament): JsonResponse
    {
        $this->ensureVisible($request, $tournament);

        return $this->success($tournament->matches()->with(['participantA', 'participantB', 'winner'])->orderBy('match_number')->get());
    }

    public function match(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        $this->ensureVisible($request, $tournament);
        abort_unless($match->tournament_id === $tournament->id, 404, __('ui.resource_not_found'));

        return $this->success($match->load(['participantA', 'participantB', 'winner', 'loser']));
    }

    public function standings(Request $request, Tournament $tournament): JsonResponse
    {
        $this->ensureVisible($request, $tournament);

        return $this->success($tournament->standings()->with('participant')->orderBy('rank_number')->get());
    }

    public function standing(Request $request, Tournament $tournament, Participant $participant): JsonResponse
    {
        $this->ensureVisible($request, $tournament);
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
        $tournament = DB::transaction(function () use ($data): Tournament {
            $now = now();
            $tournament = Tournament::query()->create($data + [
                'status' => TournamentStatus::DRAFT,
                'structure' => TournamentStructure::STANDARD,
                'participant_count' => 0,
                'ranking_config' => $this->rankingConfig($data),
                'round_robin_config' => $this->roundRobinConfig($data),
                'double_elimination_config' => $this->doubleEliminationConfig($data),
                'source_created_at' => $now,
                'source_updated_at' => $now,
                'synced_at' => $now,
            ]);
            Stage::query()->create([
                'tournament_id' => $tournament->id,
                'name' => 'Main Stage',
                'stage_order' => 1,
                'stage_type' => StageType::MAIN,
                'format' => $tournament->format,
                'status' => StageStatus::PENDING,
                'source_type' => StageSourceType::REGISTRATION,
                'source_created_at' => $now,
            ]);

            return $tournament;
        });

        return $this->success($tournament->load('stages'), 201);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $data = $request->validate($this->rules(! $request->isMethod('put')));
        $structural = ['format', 'seeding_method', 'ranking_attempts', 'ranking_comparator', 'grand_final_matches'];
        $hasStructuralChanges = collect($structural)->contains(fn (string $key): bool => $request->exists($key));

        if ($hasStructuralChanges && ! $this->editable($tournament)) {
            return $this->error(__('ui.structure_locked'), 422);
        }

        if ($hasStructuralChanges) {
            $configuration = [
                'format' => $data['format'] ?? $tournament->format->value,
                'seeding_method' => $data['seeding_method'] ?? $tournament->seeding_method->value,
                'ranking_attempts' => $data['ranking_attempts'] ?? ($tournament->ranking_config['attempts'] ?? 2),
                'ranking_comparator' => $data['ranking_comparator'] ?? ($tournament->ranking_config['comparator'] ?? 'BEST_SCORE_HIGHER'),
                'grand_final_matches' => $data['grand_final_matches'] ?? ($tournament->double_elimination_config['grand_final_matches'] ?? 2),
            ];
            $data['ranking_config'] = $this->rankingConfig($configuration);
            $data['round_robin_config'] = $this->roundRobinConfig($configuration);
            $data['double_elimination_config'] = $this->doubleEliminationConfig($configuration);
        }

        unset($data['ranking_attempts'], $data['ranking_comparator'], $data['grand_final_matches']);
        $tournament->fill($data + ['source_updated_at' => now(), 'synced_at' => now()])->save();
        if ($hasStructuralChanges) {
            $tournament->stages()->update(['format' => $tournament->format]);
        }

        return $this->success($tournament->fresh(['stages']));
    }

    public function destroy(Tournament $tournament): JsonResponse
    {
        $tournament->delete();

        return $this->success(['deleted' => true]);
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
            'name' => [$required, 'string', 'max:200'],
            'competition' => [$required, 'string', 'max:200'],
            'division' => [$required, 'string', 'max:200'],
            'competition_date' => ['sometimes', 'nullable', 'date'],
            'bracket_schedule_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'bracket_match_duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,240'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'format' => [$required, Rule::enum(TournamentFormat::class)],
            'seeding_method' => [$required, Rule::enum(SeedingMethod::class)],
            'ranking_attempts' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'ranking_comparator' => ['sometimes', 'nullable', Rule::in(['BEST_SCORE_HIGHER', 'BEST_TIME_LOWER'])],
            'grand_final_matches' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
        ];
    }

    /** @param array<string, mixed> $data */
    private function rankingConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::RANKING->value
            ? ['attempts' => (int) ($data['ranking_attempts'] ?? 2), 'comparator' => $data['ranking_comparator'] ?? 'BEST_SCORE_HIGHER']
            : null;
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

    private function ensureVisible(Request $request, Tournament $tournament): void
    {
        abort_unless(
            ($request->user()?->isAdmin() ?? false) || $tournament->status === TournamentStatus::LIVE,
            404,
            __('ui.resource_not_found'),
        );
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
