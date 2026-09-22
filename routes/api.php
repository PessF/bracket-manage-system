<?php

use App\Http\Controllers\Api\EventCompetitionController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ParticipantImportController;
use App\Http\Controllers\Api\ParticipantMemberController;
use App\Http\Controllers\Api\RankingAttemptController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentOperationController;
use App\Http\Controllers\Api\TournamentStructureController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::get('/events/{event}/competitions', [EventController::class, 'competitions']);
    Route::get('/events/{event}/competitions/{tournament}', [EventCompetitionController::class, 'show']);
    Route::get('/events/{event}/competitions/{tournament}/bracket', [EventCompetitionController::class, 'bracket']);
});

Route::middleware(['throttle:60,1', 'api.admin'])->group(function (): void {
    Route::post('/events', [EventController::class, 'store']);
    Route::match(['put', 'patch'], '/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::post('/events/{event}/competitions', [EventCompetitionController::class, 'store']);
    Route::match(['put', 'patch'], '/events/{event}/competitions/{tournament}', [EventCompetitionController::class, 'update']);
    Route::delete('/events/{event}/competitions/{tournament}', [EventCompetitionController::class, 'destroy']);
});

Route::get('/', fn () => [
    'success' => true,
    'data' => [
        'name' => __('ui.api_name'),
        'version' => '1.0',
        'documentation' => url('/api/docs'),
        'thai_manual' => url('/api/manual/th'),
        'capabilities' => url('/api/capabilities'),
        'authentication' => __('ui.api_auth_summary'),
    ],
]);
Route::get('/health', fn () => ['success' => true, 'data' => ['status' => 'ok']]);
Route::get('/capabilities', MetadataController::class);
Route::get('/manual/th', fn () => response(
    (string) file_get_contents(base_path('docs/API_MANUAL_TH.md')),
    200,
    ['Content-Type' => 'text/markdown; charset=UTF-8'],
));
Route::middleware(['throttle:60,1', 'api.admin:optional'])->group(function (): void {
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::get('/tournaments/{tournament}/participants', [TournamentController::class, 'participants']);
    Route::get('/tournaments/{tournament}/participants/{participant}', [TournamentController::class, 'participant']);
    Route::get('/tournaments/{tournament}/participants/{participant}/members', [ParticipantMemberController::class, 'index']);
    Route::get('/tournaments/{tournament}/participants/{participant}/members/{member}', [ParticipantMemberController::class, 'show']);
    Route::get('/tournaments/{tournament}/matches', [TournamentController::class, 'matches']);
    Route::get('/tournaments/{tournament}/matches/{match}', [TournamentController::class, 'match']);
    Route::get('/tournaments/{tournament}/standings', [TournamentController::class, 'standings']);
    Route::get('/tournaments/{tournament}/standings/{participant}', [TournamentController::class, 'standing']);
    Route::get('/tournaments/{tournament}/stages', [TournamentStructureController::class, 'stages']);
    Route::get('/tournaments/{tournament}/stages/{stage}', [TournamentStructureController::class, 'stage']);
    Route::get('/tournaments/{tournament}/groups', [TournamentStructureController::class, 'groups']);
    Route::get('/tournaments/{tournament}/groups/{group}', [TournamentStructureController::class, 'group']);
    Route::get('/tournaments/{tournament}/groups/{group}/standings', [TournamentStructureController::class, 'groupStandings']);
    Route::get('/tournaments/{tournament}/group-assignments', [TournamentStructureController::class, 'assignments']);
    Route::get('/tournaments/{tournament}/advancement-rules', [TournamentStructureController::class, 'advancementRules']);
    Route::get('/tournaments/{tournament}/advancement-rules/{rule}', [TournamentStructureController::class, 'advancementRule']);
    Route::get('/tournaments/{tournament}/participants/{participant}/attempts', [RankingAttemptController::class, 'index']);
    Route::get('/tournaments/{tournament}/participants/{participant}/attempts/{attemptNumber}', [RankingAttemptController::class, 'show'])
        ->whereNumber('attemptNumber');
    Route::get('/tournaments/{tournament}/live-state', [TournamentOperationController::class, 'liveState']);
});

Route::middleware(['throttle:60,1', 'api.admin'])->group(function (): void {
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::patch('/tournaments/display-order', [TournamentController::class, 'updateDisplayOrder']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::patch('/tournaments/{tournament}/share-link', [TournamentController::class, 'updateShareLink']);
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy']);

    Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store']);
    Route::post('/tournaments/{tournament}/participants/bulk', [ParticipantController::class, 'bulkStore']);
    Route::post('/tournaments/{tournament}/participants/import', [ParticipantImportController::class, 'store']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'update']);
    Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy']);
    Route::delete('/tournaments/{tournament}/participants', [ParticipantController::class, 'destroyAll']);
    Route::post('/tournaments/{tournament}/participants/{participant}/members', [ParticipantMemberController::class, 'store']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}/participants/{participant}/members/{member}', [ParticipantMemberController::class, 'update']);
    Route::delete('/tournaments/{tournament}/participants/{participant}/members/{member}', [ParticipantMemberController::class, 'destroy']);
    Route::put('/tournaments/{tournament}/group-assignments', [TournamentStructureController::class, 'updateAssignments']);
    Route::post('/tournaments/{tournament}/group-assignments/randomize', [TournamentStructureController::class, 'randomizeAssignments']);
    Route::post('/tournaments/{tournament}/randomize-participants', [TournamentOperationController::class, 'randomizeParticipants']);
    Route::post('/tournaments/{tournament}/prepare-bracket', [TournamentOperationController::class, 'prepareBracket']);
    Route::post('/tournaments/{tournament}/start', [TournamentOperationController::class, 'start']);
    Route::post('/tournaments/{tournament}/playoff', [TournamentOperationController::class, 'createPlayoff']);
    Route::post('/tournaments/{tournament}/reset-bracket', [TournamentOperationController::class, 'resetBracket']);
    Route::post('/tournaments/{tournament}/complete', [TournamentOperationController::class, 'complete']);
    Route::post('/tournaments/{tournament}/archive', [TournamentOperationController::class, 'archive']);
    Route::patch('/tournaments/{tournament}/status', [TournamentOperationController::class, 'transition']);
    Route::match(['put', 'post'], '/tournaments/{tournament}/matches/{match}/result', [TournamentOperationController::class, 'result']);
    Route::patch('/tournaments/{tournament}/matches/{match}/status', [TournamentOperationController::class, 'matchStatus']);
    Route::post('/tournaments/{tournament}/matches/{match}/progress', [TournamentOperationController::class, 'progress']);
    Route::post('/tournaments/{tournament}/participants/{participant}/attempts', [TournamentOperationController::class, 'attempt']);
    Route::put('/tournaments/{tournament}/participants/{participant}/attempts/{attemptNumber}', [TournamentOperationController::class, 'attemptAt'])
        ->whereNumber('attemptNumber');
    Route::delete('/tournaments/{tournament}/participants/{participant}/attempts/{attemptNumber}', [RankingAttemptController::class, 'destroy'])
        ->whereNumber('attemptNumber');
});
