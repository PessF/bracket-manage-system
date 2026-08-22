<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = is_string($token) && strlen($token) >= 32
            ? User::query()->where('api_token_hash', hash('sha256', $token))->first()
            : null;

        if (! $user) {
            return $this->error('A valid bearer token is required.', 401);
        }

        if (! $user->isAdmin()) {
            return $this->error('Administrator access is required.', 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);
        $user->forceFill(['api_token_last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $message],
        ], $status);
    }
}
