<?php

namespace App\Services\IAM;

use App\Models\SsoToken;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SsoService
{
    // Durasi token (menit)
    const ACCESS_TOKEN_TTL  = 15;    // 15 menit
    const REFRESH_TOKEN_TTL = 43200; // 30 hari

    /**
     * Generate pasangan access & refresh token SSO untuk user + client_app tertentu.
     * Jika token untuk kombinasi user+client_app sudah ada, token lama dihapus dulu.
     */
    public function generateTokens(User $user, string $clientApp): SsoToken
    {
        return DB::transaction(function () use ($user, $clientApp) {
            // Hapus token lama untuk client_app yang sama
            SsoToken::where('user_id', $user->id)
                ->where('client_app', $clientApp)
                ->delete();

            return SsoToken::create([
                'user_id'             => $user->id,
                'access_token'        => Str::random(64),
                'refresh_token'       => Str::random(64),
                'client_app'          => $clientApp,
                'access_expires_at'   => now()->addMinutes(self::ACCESS_TOKEN_TTL),
                'refresh_expires_at'  => now()->addMinutes(self::REFRESH_TOKEN_TTL),
            ]);
        });
    }

    /**
     * Verifikasi access_token:
     * - Pastikan token ada di DB
     * - Pastikan client_app cocok
     * - Pastikan belum expired
     * Mengembalikan SsoToken beserta relasi user-nya jika valid.
     */
    public function verifyAccessToken(string $accessToken, string $clientApp): ?SsoToken
    {
        $ssoToken = SsoToken::with('user')
            ->where('access_token', $accessToken)
            ->where('client_app', $clientApp)
            ->first();

        if (!$ssoToken || !$ssoToken->isAccessTokenValid()) {
            return null;
        }

        return $ssoToken;
    }

    /**
     * Tukar refresh_token yang valid dengan pasangan token baru.
     */
    public function refreshTokens(string $refreshToken): ?SsoToken
    {
        $ssoToken = SsoToken::with('user')
            ->where('refresh_token', $refreshToken)
            ->first();

        if (!$ssoToken || !$ssoToken->isRefreshTokenValid()) {
            return null;
        }

        return $this->generateTokens($ssoToken->user, $ssoToken->client_app);
    }

    /**
     * Cabut (revoke) semua token SSO milik user, atau hanya untuk client_app tertentu.
     */
    public function revokeTokens(User $user, ?string $clientApp = null): int
    {
        $query = SsoToken::where('user_id', $user->id);

        if ($clientApp) {
            $query->where('client_app', $clientApp);
        }

        return $query->delete();
    }
}
