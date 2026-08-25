<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTournamentIsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $request->route('tournament');
        abort_unless(
            $tournament instanceof Tournament,
            404,
        );

        return $next($request);
    }
}
