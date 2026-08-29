<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentLiveStateService
{
    public function version(Tournament $tournament): string
    {
        $current = Tournament::query()->findOrFail($tournament->id);

        $state = [
            'tournament' => [
                'status' => $current->getRawOriginal('status'),
                'participant_count' => $current->participant_count,
                'synced_at' => $current->getRawOriginal('synced_at'),
                'completed_at' => $current->getRawOriginal('completed_at'),
            ],
            'participants' => $this->aggregate($current->participants()),
            'matches' => $this->aggregate($current->matches()),
            'standings' => $this->aggregate($current->standings()),
            'ranking_attempts' => $this->aggregate($current->rankingAttempts()),
        ];

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /** @return array{count: int, latest_sync: string} */
    private function aggregate(HasMany $relation): array
    {
        $row = $relation->getQuery()
            ->toBase()
            ->selectRaw('COUNT(*) AS row_count, MAX(synced_at) AS latest_sync')
            ->first();

        return [
            'count' => (int) ($row->row_count ?? 0),
            'latest_sync' => (string) ($row->latest_sync ?? ''),
        ];
    }
}
