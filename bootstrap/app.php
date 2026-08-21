<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Protected SPMB module routes (auth:api + CheckMenuAccess + prefix api/spmb)
            Route::middleware(['auth:api', \App\Http\Middleware\CheckMenuAccess::class])->prefix('api/spmb')->group(base_path('routes/spmb_core.php'));
            // Protected SIAKAD module routes
            Route::middleware('auth:api')->prefix('api/v1/siakad')->group(base_path('routes/siakad.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: fn (Request $request) => $request->is('api/*') ? null : '/login'
        );
    })
    ->withProviders([
        \App\Providers\RateLimiterServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        // Selalu render JSON untuk semua request ke /api/*
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 422 — Validasi gagal
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data yang diberikan tidak valid.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 404 — Model tidak ditemukan
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'status'  => 'error',
                    'message' => "{$model} tidak ditemukan.",
                ], 404);
            }
        });

        // 403 — Tidak memiliki izin
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.',
                ], 403);
            }
        });

        // 401 — Belum login / token tidak valid
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Token tidak valid atau sesi telah berakhir.',
                ], 401);
            }
        });

        // 429 — Rate limit terlampaui
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Terlalu banyak percobaan. Silakan coba lagi dalam beberapa saat.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });
    })->create();
