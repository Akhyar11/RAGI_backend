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
    /** Refresh TTL untuk sesi tanpa "remember me" (1 hari). */
    const REFRESH_TOKEN_TTL_SHORT = 1440;

    /**
     * Generate pasangan access & refresh token SSO untuk user + client_app tertentu.
     * Jika token untuk kombinasi user+client_app sudah ada, token lama dihapus dulu.
     *
     * Dipakai untuk alur SSO antar-aplikasi (satu sesi aktif per client_app).
     */
    public function generateTokens(User $user, string $clientApp, ?int $refreshTtlMinutes = null): SsoToken
    {
        return DB::transaction(function () use ($user, $clientApp, $refreshTtlMinutes) {
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
                'refresh_expires_at'  => now()->addMinutes($refreshTtlMinutes ?? self::REFRESH_TOKEN_TTL),
            ]);
        });
    }

    /**
     * Terbitkan pasangan token sesi baru TANPA menghapus sesi lain milik user
     * yang sama (mendukung multi-perangkat). Baris kedaluwarsa dibersihkan.
     */
    public function issueSessionPair(User $user, string $clientApp, ?int $refreshTtlMinutes = null): SsoToken
    {
        return DB::transaction(function () use ($user, $clientApp, $refreshTtlMinutes) {
            SsoToken::where('user_id', $user->id)
                ->where('refresh_expires_at', '<', now())
                ->delete();

            return SsoToken::create([
                'user_id'             => $user->id,
                'access_token'        => Str::random(64),
                'refresh_token'       => Str::random(64),
                'client_app'          => $clientApp,
                'access_expires_at'   => now()->addMinutes(self::ACCESS_TOKEN_TTL),
                'refresh_expires_at'  => now()->addMinutes($refreshTtlMinutes ?? self::REFRESH_TOKEN_TTL),
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
     * Tukar refresh_token yang valid dengan pasangan token baru (rotasi).
     * Hanya pasangan yang dipresentasikan yang dirotasi (single-use); sesi
     * lain milik user yang sama TIDAK ikut dicabut (mendukung multi-perangkat).
     */
    public function refreshTokens(string $refreshToken): ?SsoToken
    {
        $ssoToken = SsoToken::with('user')
            ->where('refresh_token', $refreshToken)
            ->first();

        if (!$ssoToken || !$ssoToken->isRefreshTokenValid()) {
            return null;
        }

        return $this->rotatePair($ssoToken);
    }

    /**
     * Rotasi satu pasangan token: hapus baris lama (berdasarkan id) lalu
     * terbitkan pasangan baru untuk user + client_app yang sama dengan TTL
     * refresh yang setara dengan pasangan lama.
     */
    public function rotatePair(SsoToken $old): SsoToken
    {
        return DB::transaction(function () use ($old) {
            $oldRefreshTtl = self::REFRESH_TOKEN_TTL;
            if ($old->created_at && $old->refresh_expires_at) {
                $oldRefreshTtl = max(
                    1,
                    (int) $old->created_at->diffInMinutes($old->refresh_expires_at)
                );
            }

            SsoToken::whereKey($old->getKey())->delete();

            return SsoToken::create([
                'user_id'             => $old->user_id,
                'access_token'        => Str::random(64),
                'refresh_token'       => Str::random(64),
                'client_app'          => $old->client_app,
                'access_expires_at'   => now()->addMinutes(self::ACCESS_TOKEN_TTL),
                'refresh_expires_at'  => now()->addMinutes($oldRefreshTtl),
            ]);
        });
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
