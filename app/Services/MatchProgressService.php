<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use DomainException;
use Illuminate\Support\Facades\DB;

class MatchProgressService
{
    public function markInProgress(TournamentMatch|string $match): TournamentMatch
    {
        $matchId = $match instanceof TournamentMatch ? (string) $match->getKey() : $match;

        $tournamentId = TournamentMatch::query()->findOrFail($matchId)->tournament_id;

        return DB::transaction(function () use ($matchId, $tournamentId): TournamentMatch {
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);

            /** @var TournamentMatch $current */
            $current = TournamentMatch::query()
                ->lockForUpdate()
                ->findOrFail($matchId);

            $current->setRelation('tournament', $tournament);

            if ($current->tournament->status !== TournamentStatus::LIVE) {
                throw new DomainException(__('ui.match_progress_live_tournament_only'));
            }

            if ($current->status === MatchStatus::LIVE) {
                return $current;
            }

            if ($current->status !== MatchStatus::READY || $current->is_bye) {
                throw new DomainException(__('ui.match_progress_ready_only'));
            }

            if ($current->participant_a_id === null || $current->participant_b_id === null) {
                throw new DomainException(__('ui.participants_required_for_result'));
            }

            TournamentMatch::query()
                ->where('tournament_id', $current->tournament_id)
                ->where('status', MatchStatus::LIVE)
                ->whereKeyNot($current->id)
                ->lockForUpdate()
                ->update([
                    'status' => MatchStatus::READY,
                    'synced_at' => now(),
                ]);

            $now = now();
            $current->forceFill([
                'status' => MatchStatus::LIVE,
                'started_at' => $now,
                'synced_at' => $now,
            ])->save();

            return $current->refresh();
        }, 3);
    }

    public function startNextReadyMatch(Tournament $tournament): ?TournamentMatch
    {
        return DB::transaction(function () use ($tournament): ?TournamentMatch {
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);

            if ($tournament->status !== TournamentStatus::LIVE) {
                return null;
            }

            if ($tournament->matches()->where('status', MatchStatus::LIVE)->exists()) {
                return null;
            }

            /** @var TournamentMatch|null $match */
            $match = $tournament->matches()
                ->whereIn('status', [MatchStatus::READY, MatchStatus::PENDING])
                ->where('is_bye', false)
                ->whereNotNull('participant_a_id')
                ->whereNotNull('participant_b_id')
                ->orderBy('match_number')
                ->lockForUpdate()
                ->first();

            if ($match === null) {
                return null;
            }

            $match->forceFill(['status' => MatchStatus::LIVE, 'started_at' => now(), 'synced_at' => now()])->save();

            return $match;
        }, 3);
    }
}
