<?php

namespace App\Http\Controllers;

use App\Services\IAM\SsoService;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(private SsoService $ssoService) {}

    /**
     * Generate SSO token untuk user yang sudah login (via Sanctum).
     * Dipanggil setelah user berhasil login di IAM,
     * sebelum diredirect ke client_app.
     *
     * POST /api/sso/token
     * Body: { client_app: "spmb" }
     */
    public function token(Request $request)
    {
        $request->validate([
            'client_app' => [
                'required',
                'string',
                'in:spmb,siakad,sikeu,simpeg,lms,sinapra,upm,kerjasama',
            ],
        ]);

        $ssoToken = $this->ssoService->generateTokens(
            $request->user(),
            $request->client_app
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'SSO token generated successfully',
            'data'    => [
                'access_token'        => $ssoToken->access_token,
                'refresh_token'       => $ssoToken->refresh_token,
                'client_app'          => $ssoToken->client_app,
                'access_expires_at'   => $ssoToken->access_expires_at,
                'refresh_expires_at'  => $ssoToken->refresh_expires_at,
            ],
        ]);
    }

    /**
     * Verifikasi access_token dari client_app.
     * Dipanggil oleh aplikasi klien (SPMB, SIAKAD, dll.) untuk
     * memastikan token yang diterima valid dan mendapatkan data user.
     *
     * POST /api/sso/verify
     * Body: { access_token: "...", client_app: "spmb" }
     * Tidak memerlukan auth Sanctum (dipanggil server-to-server).
     */
    public function verify(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'client_app'   => 'required|string',
        ]);

        $ssoToken = $this->ssoService->verifyAccessToken(
            $request->access_token,
            $request->client_app
        );

        if (!$ssoToken) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token tidak valid atau sudah kedaluwarsa.',
            ], 401);
        }

        $user = $ssoToken->user;

        return response()->json([
            'status'  => 'success',
            'message' => 'Token valid',
            'data'    => [
                'valid'      => true,
                'expires_at' => $ssoToken->access_expires_at,
                'user'       => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'email'     => $user->email,
                    // user_type removed
                    'is_active' => $user->is_active,
                ],
            ],
        ]);
    }

    /**
     * Tukar refresh_token dengan pasangan token baru.
     * Dipanggil saat access_token sudah expired.
     *
     * POST /api/sso/refresh
     * Body: { refresh_token: "..." }
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $newToken = $this->ssoService->refreshTokens($request->refresh_token);

        if (!$newToken) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Refresh token tidak valid atau sudah kedaluwarsa.',
            ], 401);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Token berhasil diperbarui',
            'data'    => [
                'access_token'       => $newToken->access_token,
                'refresh_token'      => $newToken->refresh_token,
                'client_app'         => $newToken->client_app,
                'access_expires_at'  => $newToken->access_expires_at,
                'refresh_expires_at' => $newToken->refresh_expires_at,
            ],
        ]);
    }

    /**
     * Cabut (revoke) SSO token — logout dari client_app tertentu atau semua app.
     *
     * POST /api/sso/revoke
     * Body: { client_app: "spmb" } — opsional, jika kosong revoke semua app
     */
    public function revoke(Request $request)
    {
        $request->validate([
            'client_app' => 'nullable|string',
        ]);

        $count = $this->ssoService->revokeTokens(
            $request->user(),
            $request->client_app
        );

        $scope = $request->client_app
            ? "dari aplikasi '{$request->client_app}'"
            : 'dari semua aplikasi';

        return response()->json([
            'status'  => 'success',
            'message' => "Berhasil logout {$scope}. {$count} token dicabut.",
        ]);
    }
}
