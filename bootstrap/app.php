<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(\App\Http\Middleware\EnsureValidOrigin::class);
        $middleware->validateCsrfTokens(except: [
            'api/v1/payment/duitku/callback',
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Standardized JSON error response for API routes matching error-contract.md
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'VALIDATION_ERROR',
                            'message' => 'The given data was invalid.',
                            'details' => [
                                'errors' => $e->errors(),
                            ],
                        ],
                    ], 422);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'UNAUTHORIZED',
                            'message' => 'Unauthenticated.',
                        ],
                    ], 401);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => $e->getMessage() ?: 'This action is unauthorized.',
                        ],
                    ], 403);
                }

                $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $errorCode = match ($statusCode) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    422 => 'VALIDATION_ERROR',
                    429 => 'RATE_LIMITED',
                    default => 'INTERNAL_SERVER_ERROR',
                };

                $message = $statusCode === 500 && !config('app.debug')
                    ? 'Internal server error.'
                    : $e->getMessage();

                return response()->json([
                    'error' => [
                        'code' => $errorCode,
                        'message' => $message ?: 'An error occurred',
                    ],
                ], $statusCode);
            }
        });
    })->create();
