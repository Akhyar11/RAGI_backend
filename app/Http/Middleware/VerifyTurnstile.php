<?php

namespace App\Http\Middleware;

use App\Services\Security\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyTurnstile — gate anti-bot untuk endpoint publik (register/login).
 *
 * Pemakaian: ->middleware('turnstile:register')  (action = register|login)
 * Bila TURNSTILE_SECRET belum dikonfigurasi, middleware dilewati (fitur off).
 */
class VerifyTurnstile
{
    public function __construct(private TurnstileService $turnstile) {}

    public function handle(Request $request, Closure $next, string $action = ''): Response
    {
        if (! $this->turnstile->isConfigured()) {
            return $next($request);
        }

        $token = $request->input('captcha_token') ?? $request->input('cf-turnstile-response');
        $token = is_string($token) ? $token : null;

        if (! $this->turnstile->verify($token, $action, $request->ip())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verifikasi keamanan (anti-bot) gagal. Muat ulang halaman lalu coba lagi.',
                'errors' => [
                    'captcha_token' => ['Verifikasi anti-bot gagal.'],
                ],
            ], 422);
        }

        return $next($request);
    }
}
