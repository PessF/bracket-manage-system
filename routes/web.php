<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MatchResultController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ParticipantImportController;
use App\Http\Controllers\RankingAttemptController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentLifecycleController;
use App\Http\Controllers\TournamentWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tournaments');
Route::post('/locale/{locale}', LocaleController::class)->name('locale.update');
Route::get('/participants/import-template.csv', [ParticipantImportController::class, 'template'])->name('participants.import.template');
Route::resource('tournaments', TournamentController::class);
Route::get('/tournaments/{tournament}/settings', [TournamentController::class, 'edit'])->name('tournaments.settings');
Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store'])->name('participants.store');
Route::put('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
Route::post('/tournaments/{tournament}/participants/import', [ParticipantImportController::class, 'store'])->name('participants.import');
Route::post('/tournaments/{tournament}/start', [TournamentLifecycleController::class, 'start'])->name('tournaments.start');
Route::post('/tournaments/{tournament}/complete', [TournamentLifecycleController::class, 'complete'])->name('tournaments.complete');
Route::post('/tournaments/{tournament}/archive', [TournamentLifecycleController::class, 'archive'])->name('tournaments.archive');
Route::get('/tournaments/{tournament}/bracket', [TournamentWorkspaceController::class, 'bracket'])->name('tournaments.bracket');
Route::get('/tournaments/{tournament}/matches', [TournamentWorkspaceController::class, 'matches'])->name('tournaments.matches');
Route::get('/tournaments/{tournament}/results', [TournamentWorkspaceController::class, 'results'])->name('tournaments.results');
Route::post('/tournaments/{tournament}/matches/{match}/result', [MatchResultController::class, 'store'])->name('matches.results.store');
Route::post('/tournaments/{tournament}/participants/{participant}/attempts', [RankingAttemptController::class, 'store'])->name('ranking.attempts.store');
