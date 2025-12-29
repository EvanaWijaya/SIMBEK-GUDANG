<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom middleware dengan alias
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'owner.readonly' => \App\Http\Middleware\CheckOwnerReadOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle Authentication Exception (Token invalid/expired)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Token invalid atau expired.',
                ], 401);
            }
        });

        // Handle Not Found Exception
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan.',
                ], 404);
            }
        });

        // Handle Forbidden Exception
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda tidak memiliki izin.',
                ], 403);
            }
        });

        // Handle General Exceptions
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Kalau development, tampilkan detail error
                if (config('app.debug')) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ], 500);
                }

                // Kalau production, generic message
                return response()->json([
                    'success' => false,
                    'message' => 'Internal Server Error',
                ], 500);
            }
        });
    })
    ->create();