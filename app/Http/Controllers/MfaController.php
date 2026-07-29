<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class MfaController extends Controller
{
    /**
     * Menghasilkan QR Code & Secret Key 2FA untuk setup.
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        // Jika sudah aktif, tidak bisa setup lagi kecuali di-disable dulu
        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA sudah aktif pada akun ini.'
            ], 400);
        }

        $google2fa = new Google2FA();

        // Generate secret baru jika belum ada
        if (!$user->two_factor_secret) {
            $secret = $google2fa->generateSecretKey();
            
            // Generate recovery codes (8 codes, 10 chars each)
            $recoveryCodes = collect(range(1, 8))->map(function () {
                return Str::random(10);
            })->toArray();

            $user->two_factor_secret = encrypt($secret);
            $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
            $user->save();
        } else {
            $secret = decrypt($user->two_factor_secret);
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        }

        // URL untuk QR Code
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Render QR Code as SVG
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svgQrCode = $writer->writeString($qrCodeUrl);

        return response()->json([
            'status' => 'success',
            'data' => [
                'secret' => $secret,
                'qr_code_svg' => base64_encode($svgQrCode),
                'recovery_codes' => $recoveryCodes
            ]
        ]);
    }

    /**
     * Memverifikasi kode TOTP 2FA untuk mengaktifkannya pertama kali.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'totp_code' => 'required|string|size:6'
        ]);

        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA sudah terverifikasi sebelumnya.'
            ], 400);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan panggil endpoint setup terlebih dahulu.'
            ], 400);
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $request->totp_code);

        if ($valid) {
            $user->two_factor_confirmed_at = now();
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => '2FA berhasil diverifikasi dan diaktifkan.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Kode TOTP tidak valid.'
        ], 422);
    }

    /**
     * Mematikan fitur 2FA dengan konfirmasi password.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password salah.'
            ], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => '2FA berhasil dinonaktifkan.'
        ]);
    }
}
