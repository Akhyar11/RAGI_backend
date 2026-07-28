<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IAM\PasswordResetService;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwordResetService) {}

    /**
     * Kirim link reset password ke email user.
     *
     * POST /api/auth/forgot-password
     * Body: { email: "user@kampus.ac.id" }
     *
     * Selalu mengembalikan 200 meskipun email tidak ditemukan
     * (mencegah email enumeration attack).
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if ($user) {
            $plainToken = $this->passwordResetService->createToken($user);

            // TODO: Kirim email dengan link reset password
            // Mail::to($user->email)->send(new PasswordResetMail($plainToken));
            // Untuk sementara, token dikembalikan di response (DEVELOPMENT ONLY)
            if (app()->environment('local', 'testing')) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Link reset password telah dikirim ke email Anda.',
                    '_dev_token' => $plainToken, // Hanya tersedia di environment lokal
                ]);
            }
        }

        // Response yang sama meskipun email tidak ada (security)
        return response()->json([
            'status'  => 'success',
            'message' => 'Jika email terdaftar, link reset password akan dikirimkan.',
        ]);
    }

    /**
     * Proses reset password menggunakan token dari email.
     *
     * POST /api/auth/reset-password
     * Body: { email, token, password, password_confirmation }
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|string|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $success = $this->passwordResetService->resetPassword(
            $request->email,
            $request->token,
            $request->password
        );

        if (!$success) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token tidak valid, sudah digunakan, atau telah kedaluwarsa.',
            ], 422);
        }

        // Invalidasi semua Sanctum + SSO token milik user setelah reset password
        $user = \App\Models\User::where('email', $request->email)->first();
        if ($user) {
            $user->tokens()->delete();
            \App\Models\SsoToken::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Password berhasil diperbarui. Silakan login kembali.',
        ]);
    }
}
