<?php

use App\Http\Middleware\SetLocaleFromSession;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocaleFromSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*');
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Validation error',
                $e->errors(),
                $e->status
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $e->getMessage() ?: 'Unauthenticated.',
                null,
                401
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $retryAfter = (int) ($e->getHeaders()['Retry-After'][0] ?? 0);
            $errors = $retryAfter > 0
                ? ['phone' => ["Try again in {$retryAfter} seconds."]]
                : null;

            return ApiResponse::error(
                $e->getMessage() ?: 'Too many requests.',
                $errors,
                429
            );
        });
    })->create();
