<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Tournament::query()->withCount(['participants', 'matches'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('source_created_at')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(Tournament $tournament): JsonResponse
    {
        $tournament->load(['stages', 'participants' => fn ($query) => $query->orderBy('seed_number'), 'matches' => fn ($query) => $query->orderBy('match_number'), 'standings.participant']);

        return response()->json(['success' => true, 'data' => $tournament]);
    }
}
