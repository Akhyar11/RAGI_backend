<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * F-004: tolak bearer token Passport yang baris oauth_access_tokens-nya
 * sudah melewati `expires_at`.
 *
 * Latar: guard `auth:api` memvalidasi klaim JWT `exp` + flag `revoked`,
 * sehingga sesi web yang diterbitkan lama (PAT 1 tahun) tetap hidup setahun.
 * Middleware ini menegakkan batas per-token dari database: token sesi web
 * diterbitkan dengan `expires_at` pendek (15 menit) dan diperpanjang hanya
 * lewat rotasi refresh token. Baris tanpa `expires_at` dan token transien
 * (cookie session) dilewati agar tidak mengubah perilaku auth lain.
 */
class EnsurePassportTokenIsFresh
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user() ?? ($request->bearerToken() ? auth('api')->user() : null);

        if ($user) {
            $token = method_exists($user, 'currentAccessToken')
                ? $user->currentAccessToken()
                : (method_exists($user, 'token') ? $user->token() : null);

            if (
                $token
                && isset($token->expires_at)
                && $token->expires_at
                && now()->greaterThanOrEqualTo($token->expires_at)
            ) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Token kedaluwarsa. Silakan login ulang atau refresh sesi.',
                ], 401);
            }
        }

        return $next($request);
    }
}
