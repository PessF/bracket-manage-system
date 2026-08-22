<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = strtolower((string) $request->query('lang', ''));
        $headerLocale = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));
        $locale = in_array($requested, ['en', 'th'], true) ? $requested : $headerLocale;

        if (in_array($locale, ['en', 'th'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
