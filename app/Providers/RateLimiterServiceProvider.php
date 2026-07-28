<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class RateLimiterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Login: Maks 5 percobaan per menit per IP
         * Mencegah brute-force serangan pada endpoint login
         */
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        /*
         * Forgot Password: Maks 3 permintaan per 5 menit per IP
         * Mencegah penyalahgunaan endpoint kirim email reset
         */
        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinutes(5, 3)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Terlalu banyak permintaan reset password. Silakan coba lagi dalam 5 menit.',
                        'retry_after' => 300,
                    ], 429);
                });
        });

        /*
         * SSO Verify: Maks 60 request per menit per IP
         * Endpoint ini dipanggil server-to-server, sehingga limitnya lebih longgar
         */
        RateLimiter::for('sso-verify', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        /*
         * API Umum: Maks 60 request per menit per user (atau IP jika belum login)
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
