<?php

namespace App\Services\IAM;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    // Token berlaku selama 60 menit
    const TOKEN_TTL_MINUTES = 60;

    /**
     * Buat token reset password untuk user berdasarkan email.
     * Token lama yang belum dipakai akan dihapus terlebih dahulu.
     * Mengembalikan plain-text token untuk dikirim via email.
     */
    public function createToken(User $user): string
    {
        return DB::transaction(function () use ($user) {
            // Hapus token lama yang belum dipakai
            PasswordReset::where('user_id', $user->id)
                ->where('is_used', false)
                ->delete();

            $plainToken = Str::random(64);

            PasswordReset::create([
                'user_id'    => $user->id,
                'token'      => Hash::make($plainToken),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
                'is_used'    => false,
            ]);

            return $plainToken;
        });
    }

    /**
     * Reset password menggunakan token yang valid.
     * Mengembalikan true jika berhasil, false jika token tidak valid.
     */
    public function resetPassword(string $email, string $plainToken, string $newPassword): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        // Cari token yang belum expired dan belum dipakai
        $resetRecord = PasswordReset::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$resetRecord || !Hash::check($plainToken, $resetRecord->token)) {
            return false;
        }

        return DB::transaction(function () use ($user, $resetRecord, $newPassword) {
            // Tandai token sebagai sudah dipakai
            $resetRecord->update(['is_used' => true]);

            // Update password user
            $user->update(['password' => Hash::make($newPassword)]);

            return true;
        });
    }
}
