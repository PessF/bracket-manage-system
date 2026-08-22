<?php

use App\Http\Controllers\Api\TournamentController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['success' => true, 'data' => ['status' => 'ok']]);
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
