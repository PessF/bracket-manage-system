<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Models\TournamentMatch;
use DomainException;
use Illuminate\Support\Facades\DB;

class MatchProgressService
{
    public function markInProgress(TournamentMatch|string $match): TournamentMatch
    {
        $matchId = $match instanceof TournamentMatch ? (string) $match->getKey() : $match;

        return DB::transaction(function () use ($matchId): TournamentMatch {
            /** @var TournamentMatch $current */
            $current = TournamentMatch::query()
                ->with('tournament')
                ->lockForUpdate()
                ->findOrFail($matchId);

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
}
