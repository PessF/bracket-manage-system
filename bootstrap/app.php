<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'api.admin' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'The submitted data is invalid.',
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
                'error' => ['message' => 'Resource not found.'],
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            return response()->json([
                'success' => false,
                'error' => ['message' => $exception->getMessage() ?: 'Request failed.'],
            ], $status, $exception->getHeaders());
        });
    })->create();
