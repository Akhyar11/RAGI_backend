<?php

namespace App\Http\Controllers;

use App\Models\SsoToken;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Mail\VerifyEmailMail;
use App\Services\AuditLogService;
use App\Services\IAM\SsoService;

class AuthController extends Controller
{
    public function __construct(private SsoService $ssoService) {}

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
// user_type removed
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => true,
            'is_verified' => false,
        ]);

        $defaultRoleSetting = \App\Models\SystemSetting::where('key', 'default_register_role')->first();
        $roleSlug = $defaultRoleSetting ? $defaultRoleSetting->value : 'calon_mhs';
        
        $defaultRole = \App\Models\Role::where('slug', $roleSlug)->first();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
        }

        // Auto-create draft PendaftaranCalonMhs for calon mahasiswa
        if ($roleSlug === 'calon_mhs' || ($defaultRole && $defaultRole->slug === 'calon_mhs')) {
            try {
                $gelombang = \App\Models\Spmb\GelombangPenerimaan::where('status', 'aktif')->first()
                    ?? \App\Models\Spmb\GelombangPenerimaan::first();
                $gelombangId = $gelombang ? $gelombang->id : 1;

                $defaultProdi = \App\Models\Spmb\MasterProgramStudi::where('is_active', true)->first();
                $prodiId = $defaultProdi ? $defaultProdi->id : 1;

                \App\Models\Spmb\PendaftaranCalonMhs::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'gelombang_id' => $gelombangId,
                        'program_studi_id' => $prodiId,
                        'no_pendaftaran' => 'REG-' . date('Ymd') . '-' . rand(1000, 9999),
                        'nama_lengkap' => $user->username,
                        'nik' => '3300' . str_pad($user->id, 12, '0', STR_PAD_LEFT),
                        'tanggal_lahir' => now()->subYears(18)->format('Y-m-d'),
                        'tempat_lahir' => '-',
                        'jenis_kelamin' => 'L',
                        'kewarganegaraan' => 'WNI',
                        'alamat' => '-',
                        'asal_sekolah' => '-',
                        'jurusan_sekolah' => '-',
                        'no_hp' => $user->phone,
                        'status' => 'draft',
                        'status_pembayaran' => 'belum_bayar',
                    ]
                );
            } catch (\Throwable $th) {
                \Illuminate\Support\Facades\Log::warning('Gagal auto-create PendaftaranCalonMhs: ' . $th->getMessage());
            }
        }

        // Generate email verification token
        $verifyToken = Str::random(60);
        Cache::put('email_verify_' . $verifyToken, $user->id, now()->addMinutes(60));

        // Construct frontend URL (adjust as needed)
        $verifyUrl = config('app.url') . '/verify-email?token=' . $verifyToken;

        // Send email
        Mail::to($user->email)->send(new VerifyEmailMail($verifyUrl, $user->username));

        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran berhasil. Silakan periksa email Anda untuk verifikasi akun.',
            'data' => $user->load(['roles', 'roles.permissions']),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $userId = Cache::get('email_verify_' . $request->token);

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token verifikasi tidak valid atau telah kedaluwarsa.'
            ], 400);
        }

        $user = User::find($userId);
        if ($user) {
            $user->email_verified_at = now();
            $user->is_verified = true;
            $user->save();

            Cache::forget('email_verify_' . $request->token);

            return response()->json([
                'status' => 'success',
                'message' => 'Email berhasil diverifikasi.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'User tidak ditemukan.'
        ], 404);
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->identifier ?? $request->email ?? $request->username;

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        // 1. Dukung login menggunakan NIM Mahasiswa (SIAKAD)
        if (!$user) {
            $mahasiswa = \App\Models\Siakad\Mahasiswa::where('nim', $identifier)->first();
            if ($mahasiswa && $mahasiswa->user_id) {
                $user = User::find($mahasiswa->user_id);
            }
        }

        // 2. Dukung login menggunakan NIDN atau NIP Dosen (SIAKAD)
        if (!$user) {
            $dosen = \App\Models\Siakad\Dosen::where('nidn', $identifier)
                ->orWhere('nip', $identifier)
                ->first();
            if ($dosen && $dosen->user_id) {
                $user = User::find($dosen->user_id);
            }
        }

        // 3. Dukung login menggunakan NIP Pegawai (SIMPEG)
        if (!$user && class_exists(\App\Models\Simpeg\Pegawai::class)) {
            $pegawai = \App\Models\Simpeg\Pegawai::where('nip', $identifier)->first();
            if ($pegawai && $pegawai->user_id) {
                $user = User::find($pegawai->user_id);
            }
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLogService::record('IAM', 'login_failed', 'users', $user?->id, null, ['identifier' => $identifier]);
            throw ValidationException::withMessages([
                'identifier' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'identifier' => ['Akun Anda tidak aktif.'],
            ]);
        }

        // 2FA Check (Opsi B: Temporary Token)
        if ($user->two_factor_confirmed_at) {
            $tempToken = Str::random(60);
            Cache::put('2fa_login_' . $tempToken, $user->id, now()->addMinutes(10));

            return response()->json([
                'status' => 'success',
                'requires_2fa' => true,
                'temp_token' => $tempToken,
                'message' => 'Silakan masukkan kode TOTP dari aplikasi Authenticator Anda.'
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;
        
        \App\Models\UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $tokenResult->token->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        AuditLogService::record('IAM', 'login', 'users', $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'requires_2fa' => false,
            'data' => $user->load(['roles', 'roles.permissions']),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function mfaLoginVerify(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'totp_code' => 'required|string|size:6'
        ]);

        $userId = Cache::get('2fa_login_' . $request->temp_token);

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi login telah kedaluwarsa. Silakan login ulang dengan password.'
            ], 400);
        }

        $user = User::find($userId);
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        
        $secret = decrypt($user->two_factor_secret);
        $valid = $google2fa->verifyKey($secret, $request->totp_code);

        if ($valid) {
            Cache::forget('2fa_login_' . $request->temp_token);
            $user->update(['last_login_at' => now()]);
            
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

            \App\Models\UserSessionIam::create([
                'user_id' => $user->id,
                'token' => $tokenResult->token->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            AuditLogService::record('IAM', 'login', 'users', $user->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => $user->load(['roles', 'roles.permissions']),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Kode TOTP tidak valid.'
        ], 422);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $request->user()->load(['roles', 'roles.permissions'])
        ]);
    }

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

    public function logout(Request $request)
    {
        $token = $request->user()->token();
        
        if ($token) {
            AuditLogService::record('IAM', 'logout', 'users', $request->user()->id);
            \App\Models\UserSessionIam::where('token', $token->id)->delete();
            $token->revoke();
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function logoutAll(Request $request)
    {
        $user = $request->user();
        
        // Revoke all Passport tokens
        $user->tokens->each(function ($token) {
            $token->revoke();
        });
        
        // Clear sessions
        \App\Models\UserSessionIam::where('user_id', $user->id)->delete();
        SsoToken::where('user_id', $user->id)->delete();

        AuditLogService::record('IAM', 'logout_all', 'users', $user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil logout dari semua perangkat dan aplikasi.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            AuditLogService::record('IAM', 'change_password_failed', 'users', $user->id);
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);
        
        // Revoke all Passport tokens
        $user->tokens->each(function ($token) {
            $token->revoke();
        });
        
        \App\Models\UserSessionIam::where('user_id', $user->id)->delete();
        SsoToken::where('user_id', $user->id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password berhasil diperbarui. Silakan login kembali di semua perangkat.',
        ]);
    }
}
