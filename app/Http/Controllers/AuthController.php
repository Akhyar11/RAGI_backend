<?php

namespace App\Http\Controllers;

use App\Models\SsoToken;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'user_type' => 'required|in:mahasiswa,dosen,tendik,admin,calon_mhs',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'user_type' => $request->user_type,
            'is_active' => true,
            'is_verified' => false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'data' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $request->user()
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Logout dari SEMUA perangkat — hapus seluruh Sanctum token + SSO token milik user.
     * POST /api/auth/logout-all
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();

        // Hapus semua Sanctum token
        $user->tokens()->delete();

        // Hapus semua SSO token
        SsoToken::where('user_id', $user->id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil logout dari semua perangkat dan aplikasi.',
        ]);
    }

    /**
     * Ganti password — dan otomatis invalidasi semua token aktif (Sanctum + SSO)
     * agar user wajib login ulang di semua perangkat.
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        // Update password
        $user->update(['password' => Hash::make($request->password)]);

        // Invalidasi SEMUA token aktif (Sanctum + SSO) — security best practice
        $user->tokens()->delete();
        SsoToken::where('user_id', $user->id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password berhasil diperbarui. Silakan login kembali di semua perangkat.',
        ]);
    }
}
