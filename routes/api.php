<?php

use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ParticipantImportController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentOperationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => [
    'success' => true,
    'data' => [
        'name' => __('ui.api_name'),
        'version' => '1.0',
        'documentation' => url('/api/docs'),
        'authentication' => __('ui.api_auth_summary'),
    ],
]);
Route::get('/health', fn () => ['success' => true, 'data' => ['status' => 'ok']]);
Route::middleware(['throttle:60,1', 'api.admin'])->group(function (): void {
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::get('/tournaments/{tournament}/participants', [TournamentController::class, 'participants']);
    Route::get('/tournaments/{tournament}/participants/{participant}', [TournamentController::class, 'participant']);
    Route::get('/tournaments/{tournament}/matches', [TournamentController::class, 'matches']);
    Route::get('/tournaments/{tournament}/matches/{match}', [TournamentController::class, 'match']);
    Route::get('/tournaments/{tournament}/standings', [TournamentController::class, 'standings']);
    Route::get('/tournaments/{tournament}/standings/{participant}', [TournamentController::class, 'standing']);
});

Route::middleware(['throttle:60,1', 'api.admin'])->group(function (): void {
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy']);

    Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store']);
    Route::post('/tournaments/{tournament}/participants/import', [ParticipantImportController::class, 'store']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'update']);
    Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy']);
    Route::post('/tournaments/{tournament}/start', [TournamentOperationController::class, 'start']);
    Route::post('/tournaments/{tournament}/complete', [TournamentOperationController::class, 'complete']);
    Route::post('/tournaments/{tournament}/archive', [TournamentOperationController::class, 'archive']);
    Route::patch('/tournaments/{tournament}/status', [TournamentOperationController::class, 'transition']);
    Route::match(['put', 'post'], '/tournaments/{tournament}/matches/{match}/result', [TournamentOperationController::class, 'result']);
    Route::post('/tournaments/{tournament}/participants/{participant}/attempts', [TournamentOperationController::class, 'attempt']);
    Route::put('/tournaments/{tournament}/participants/{participant}/attempts/{attemptNumber}', [TournamentOperationController::class, 'attemptAt'])
        ->whereNumber('attemptNumber');
});
