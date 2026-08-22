<?php

use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ParticipantImportController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentOperationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => [
    'success' => true,
    'data' => [
        'name' => 'EasyKids Tournament API',
        'version' => '1.0',
        'documentation' => url('/api/docs'),
        'authentication' => 'Public reads; admin writes use Authorization: Bearer <token>.',
    ],
]);
Route::get('/health', fn () => ['success' => true, 'data' => ['status' => 'ok']]);
Route::view('/docs', 'api.docs');

Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
Route::get('/tournaments/{tournament}/participants', [TournamentController::class, 'participants']);
Route::get('/tournaments/{tournament}/matches', [TournamentController::class, 'matches']);
Route::get('/tournaments/{tournament}/standings', [TournamentController::class, 'standings']);

Route::middleware(['throttle:60,1', 'api.admin'])->group(function (): void {
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::match(['put', 'patch'], '/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy']);

    Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store']);
    Route::post('/tournaments/{tournament}/participants/import', [ParticipantImportController::class, 'store']);
    Route::patch('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'update']);
    Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy']);
    Route::post('/tournaments/{tournament}/start', [TournamentOperationController::class, 'start']);
    Route::post('/tournaments/{tournament}/complete', [TournamentOperationController::class, 'complete']);
    Route::post('/tournaments/{tournament}/archive', [TournamentOperationController::class, 'archive']);
    Route::post('/tournaments/{tournament}/matches/{match}/result', [TournamentOperationController::class, 'result']);
    Route::post('/tournaments/{tournament}/participants/{participant}/attempts', [TournamentOperationController::class, 'attempt']);
});
