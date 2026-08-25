<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTournamentIsLive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $request->route('tournament');

        abort_unless(
            $tournament instanceof Tournament
                && in_array($tournament->status, [TournamentStatus::LIVE, TournamentStatus::COMPLETED, TournamentStatus::ARCHIVED], true),
            404,
        );

        return $next($request);
    }
}
