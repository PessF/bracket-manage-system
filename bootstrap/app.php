<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureTournamentIsLive;
use App\Http\Middleware\EnsureTournamentIsVisible;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(append: [SetApiLocale::class]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'api.admin' => AuthenticateApiToken::class,
            'tournament.live' => EnsureTournamentIsLive::class,
            'tournament.visible' => EnsureTournamentIsVisible::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, AuthenticateApiToken::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*') && $request->isMethod('GET')) {
                return response()->view('errors.request', [
                    'status' => 422, 'title' => __('ui.api_validation_failed'),
                    'help' => __('ui.invalid_filters_help'),
                    'retryUrl' => $request->url(),
                ], 422);
            }
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => __('ui.api_validation_failed'),
                    'fields' => $exception->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => ['message' => __('ui.resource_not_found')],
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*') && in_array($exception->getStatusCode(), [409, 419, 429], true)) {
                $status = $exception->getStatusCode();

                return response()->view('errors.request', [
                    'status' => $status,
                    'title' => __('ui.request_failed'),
                    'help' => $status === 409 ? ($exception->getMessage() ?: __('ui.request_failed')) : __('ui.error_help_'.$status),
                    'retryUrl' => route('events.index'),
                ], $status, $exception->getHeaders());
            }
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $status === 404
                ? __('ui.resource_not_found')
                : ($status >= 500 ? __('ui.request_failed') : ($exception->getMessage() ?: __('ui.request_failed')));

            return response()->json([
                'success' => false,
                'error' => ['message' => $message],
            ], $status, $exception->getHeaders());
        });

        // Keep infrastructure details in the logs, including when a session database is down.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof ValidationException || $exception instanceof AuthenticationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)) {
                return null;
            }

            $status = $exception instanceof QueryException ? 503 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'error' => ['message' => __('ui.request_failed')]], $status);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.request_failed')], $status);
            }

            return response()->view('errors.request', [
                'status' => $status, 'title' => __('ui.server_error_title'),
                'help' => __('ui.server_error_help'), 'retryUrl' => route('events.index'),
            ], $status);
        });
    })->create();
