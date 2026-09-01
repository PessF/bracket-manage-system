<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RankingType;
use App\Models\Participant;
use App\Models\Tournament;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RankingAttemptController extends Controller
{
    public function __construct(private readonly RankingService $ranking) {}

    public function store(Request $request, Tournament $tournament, Participant $participant): JsonResponse|RedirectResponse
    {
        abort_unless($participant->tournament_id === $tournament->id, 404);
        $rules = [
            'attempt_number' => ['required', 'integer', 'between:1,20'],
            'is_valid' => ['nullable', 'boolean'],
        ];
        $type = RankingType::tryFrom((string) ($tournament->ranking_config['type'] ?? ''));

        if ($type === RankingType::RACING_ROBOT) {
            $rules['attempt_value'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        } elseif ($type === RankingType::DRONE_MISSION) {
            $rules['manual_score'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
            $rules['auto_score'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
            $rules['attempt_time'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        } else {
            $rules['attempt_value'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        }

        $data = $request->validate($rules);
        try {
            $attempt = $this->ranking->saveAttempt(
                $tournament,
                $participant,
                (int) $data['attempt_number'],
                $data['attempt_value'] ?? null,
                $request->boolean('is_valid'),
                $data['manual_score'] ?? null,
                $data['auto_score'] ?? null,
                $data['attempt_time'] ?? null,
            );

            $message = __('ui.attempt_saved', ['number' => $data['attempt_number']]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'attempt' => [
                        'participant_id' => $attempt->participant_id,
                        'attempt_number' => $attempt->attempt_number,
                        'attempt_value' => $attempt->attempt_value,
                        'manual_score' => $attempt->manual_score,
                        'auto_score' => $attempt->auto_score,
                        'attempt_time' => $attempt->attempt_time,
                        'is_valid' => $attempt->is_valid,
                    ],
                ]);
            }

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors($exception->getMessage());
        }
    }
}
