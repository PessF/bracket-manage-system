<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Stage;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user()?->isAdmin() ?? false;
        $tournaments = Tournament::query()->withCount(['participants', 'matches'])
            ->when(! $isAdmin, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($isAdmin && $request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('source_created_at')->paginate(12)->withQueryString();

        return view('tournaments.index', compact('tournaments'));
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
            $tournament = Tournament::query()->create($data + [
                'status' => TournamentStatus::DRAFT, 'participant_count' => 0,
                'ranking_config' => $this->rankingConfig($data),
                'round_robin_config' => $this->roundRobinConfig($data),
                'double_elimination_config' => $this->doubleEliminationConfig($data),
                'source_created_at' => $now, 'source_updated_at' => $now, 'synced_at' => $now,
            ]);
            Stage::query()->create([
                'tournament_id' => $tournament->id, 'name' => 'Main Stage', 'stage_order' => 1,
                'format' => $tournament->format, 'status' => StageStatus::PENDING, 'source_created_at' => $now,
            ]);

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
                'ranking_config' => $this->rankingConfig($configuration),
                'round_robin_config' => $this->roundRobinConfig($configuration),
                'double_elimination_config' => $this->doubleEliminationConfig($configuration),
            ]);
        }

        $tournament->fill($data + ['source_updated_at' => now(), 'synced_at' => now()])->save();

        if ($this->editable($tournament)) {
            $tournament->stages()->update(['format' => $tournament->format]);
        }

        return redirect()->route('tournaments.settings', $tournament)->with('success', __('ui.settings_saved'));
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $tournament->delete();

        return redirect()->route('tournaments.index')->with('success', __('ui.tournament_deleted'));
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
            'venue' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedConfiguration(Request $request): array
    {
        return $request->validate([
            'format' => ['required', Rule::enum(TournamentFormat::class)],
            'seeding_method' => ['required', Rule::enum(SeedingMethod::class)],
            'ranking_attempts' => ['nullable', 'integer', 'between:1,20'],
            'ranking_comparator' => ['nullable', Rule::in(['BEST_SCORE_HIGHER', 'BEST_TIME_LOWER'])],
            'win_points' => ['nullable', 'integer', 'between:0,100'],
            'draw_points' => ['nullable', 'integer', 'between:0,100'],
            'loss_points' => ['nullable', 'integer', 'between:0,100'],
            'grand_final_matches' => ['nullable', 'integer', Rule::in([1, 2])],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function rankingConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::RANKING->value
            ? ['attempts' => (int) ($data['ranking_attempts'] ?? 3), 'comparator' => $data['ranking_comparator'] ?? 'BEST_SCORE_HIGHER'] : null;
    }

    /** @param array<string, mixed> $data */
    private function roundRobinConfig(array $data): ?array
    {
        return $data['format'] === TournamentFormat::ROUND_ROBIN->value
            ? ['win_points' => (int) ($data['win_points'] ?? 3), 'draw_points' => (int) ($data['draw_points'] ?? 1), 'loss_points' => (int) ($data['loss_points'] ?? 0)] : null;
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
