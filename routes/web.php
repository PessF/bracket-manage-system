<?php

use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\FirstAdminSetupController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MatchProgressController;
use App\Http\Controllers\MatchResultController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ParticipantImportController;
use App\Http\Controllers\RankingAttemptController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentGroupAssignmentController;
use App\Http\Controllers\TournamentLifecycleController;
use App\Http\Controllers\TournamentWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tournaments');
Route::post('/locale/{locale}', LocaleController::class)->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/admin/setup', [FirstAdminSetupController::class, 'create'])->name('admin.setup');
Route::post('/admin/setup', [FirstAdminSetupController::class, 'store'])->middleware('throttle:5,1')->name('admin.setup.store');

Route::get('/participants/import-template.csv', [ParticipantImportController::class, 'template'])->name('participants.import.template');
Route::view('/api/docs', 'api.docs')->name('api.docs');
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');

Route::middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
    Route::patch('/tournaments/display-order', [TournamentController::class, 'updateDisplayOrder'])->name('tournaments.display-order.update');
    Route::get('/tournaments/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournaments.edit');
    Route::get('/tournaments/{tournament}/settings', [TournamentController::class, 'edit'])->name('tournaments.settings');
    Route::match(['put', 'patch'], '/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
    Route::patch('/tournaments/{tournament}/share-link', [TournamentController::class, 'updateShareLink'])->name('tournaments.share-link.update');
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');

    Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::post('/tournaments/{tournament}/participants/bulk', [ParticipantController::class, 'bulkStore'])->name('participants.bulk-store');
    Route::put('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
    Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
    Route::delete('/tournaments/{tournament}/participants', [ParticipantController::class, 'destroyAll'])->name('participants.destroy-all');
    Route::post('/tournaments/{tournament}/participants/import', [ParticipantImportController::class, 'store'])->name('participants.import');
    Route::get('/tournaments/{tournament}/groups', [TournamentGroupAssignmentController::class, 'edit'])->name('tournaments.groups.edit');
    Route::put('/tournaments/{tournament}/groups', [TournamentGroupAssignmentController::class, 'update'])->name('tournaments.groups.update');
    Route::post('/tournaments/{tournament}/groups/randomize', [TournamentGroupAssignmentController::class, 'randomize'])->name('tournaments.groups.randomize');
    Route::post('/tournaments/{tournament}/randomize-participants', [TournamentLifecycleController::class, 'randomizeParticipants'])->name('tournaments.randomize-participants');
    Route::post('/tournaments/{tournament}/prepare-bracket', [TournamentLifecycleController::class, 'prepareBracket'])->name('tournaments.prepare-bracket');
    Route::post('/tournaments/{tournament}/start', [TournamentLifecycleController::class, 'start'])->name('tournaments.start');
    Route::post('/tournaments/{tournament}/playoff', [TournamentLifecycleController::class, 'createPlayoff'])->name('tournaments.playoff.create');
    Route::post('/tournaments/{tournament}/reset-bracket', [TournamentLifecycleController::class, 'resetBracket'])->name('tournaments.reset-bracket');
    Route::post('/tournaments/{tournament}/complete', [TournamentLifecycleController::class, 'complete'])->name('tournaments.complete');
    Route::post('/tournaments/{tournament}/archive', [TournamentLifecycleController::class, 'archive'])->name('tournaments.archive');
    Route::post('/tournaments/{tournament}/matches/{match}/result', [MatchResultController::class, 'store'])->name('matches.results.store');
    Route::post('/tournaments/{tournament}/matches/{match}/progress', [MatchProgressController::class, 'store'])->name('matches.progress.store');
    Route::post('/tournaments/{tournament}/participants/{participant}/attempts', [RankingAttemptController::class, 'store'])->name('ranking.attempts.store');

    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/api-token', [ApiTokenController::class, 'show'])->name('admin.api-token.show');
    Route::post('/admin/api-token', [ApiTokenController::class, 'store'])->name('admin.api-token.store');
    Route::delete('/admin/api-token', [ApiTokenController::class, 'destroy'])->name('admin.api-token.destroy');
});

Route::middleware('tournament.visible')->group(function (): void {
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'entry'])->name('tournaments.show');
    Route::get('/tournaments/{tournament}/overview', [TournamentController::class, 'show'])->name('tournaments.overview');
    Route::get('/tournaments/{tournament}/bracket', [TournamentWorkspaceController::class, 'bracket'])->name('tournaments.bracket');
    Route::get('/tournaments/{tournament}/matches', [TournamentWorkspaceController::class, 'adminMatches'])->name('tournaments.matches');
    Route::get('/tournaments/{tournament}/results', [TournamentWorkspaceController::class, 'results'])->name('tournaments.results');
    Route::get('/tournaments/{tournament}/live-state', [TournamentWorkspaceController::class, 'liveState'])->name('tournaments.live-state');
});

Route::view('/view', 'tournaments.public-index')->name('public.tournaments.index');

Route::middleware('tournament.live')->prefix('view')->name('public.tournaments.')->group(function (): void {
    Route::get('/{tournament:public_token}', [TournamentWorkspaceController::class, 'bracket'])->name('show');
    Route::get('/{tournament:public_token}/bracket', [TournamentWorkspaceController::class, 'bracket'])->name('bracket');
    Route::get('/{tournament:public_token}/matches', [TournamentWorkspaceController::class, 'matches'])->name('matches');
    Route::get('/{tournament:public_token}/results', [TournamentWorkspaceController::class, 'results'])->name('results');
    Route::get('/{tournament:public_token}/live-state', [TournamentWorkspaceController::class, 'liveState'])->name('live-state');
});
