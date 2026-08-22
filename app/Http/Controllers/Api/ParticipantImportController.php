<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\ParticipantCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ParticipantImportController extends Controller
{
    public function __construct(private readonly ParticipantCsvImportService $importer) {}

    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->importer->import($tournament, $request->file('csv_file')),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => ['message' => $exception->getMessage()],
            ], 422);
        }
    }
}
