<?php

namespace App\Http\Controllers;

use App\Models\OauthAppClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

class OAuthController extends Controller
{
    /**
     * Tampilkan halaman login SSO.
     * Dipanggil oleh Passport saat user mengakses /oauth/authorize
     * dan belum terautentikasi via sesi web.
     *
     * GET /sso/login
     */
    public function showLogin(Request $request)
    {
        // Validasi bahwa client_app yang meminta adalah yang terdaftar
        $clientApp = $request->query('client_app');
        $redirectUri = $request->query('redirect_uri');

        if ($clientApp) {
            $appClient = OauthAppClient::findActive($clientApp);
            if (!$appClient || ($redirectUri && !$appClient->isRedirectUriAllowed($redirectUri))) {
                abort(403, 'Aplikasi tidak diizinkan atau redirect URI tidak valid.');
            }
        }

        return view('auth.sso-login', [
            'query'      => $request->getQueryString(),
            'client_app' => $clientApp,
            'app_name'   => $appClient?->client_name ?? 'Ekosistem Kampus',
        ]);
    }

    /**
     * Proses login SSO.
     * Setelah berhasil login, redirect ke /oauth/authorize dengan query string asli
     * agar Passport melanjutkan Authorization Code Flow.
     *
     * POST /sso/login
     */
    public function processLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif. Hubungi administrator.'],
            ]);
        }

        // Login ke sesi web agar Passport dapat mendeteksi user yang sudah terautentikasi
        Auth::login($user, remember: true);
        $user->update(['last_login_at' => now()]);

        // Redirect kembali ke endpoint authorize Passport dengan semua query parameter asli
        $query = $request->input('_query', '');
        return redirect('/oauth/authorize' . ($query ? '?' . $query : ''));
    }

    /**
     * Kembalikan data user berdasarkan access token Passport.
     * Digunakan oleh aplikasi klien sebagai "resource server" endpoint.
     *
     * GET /api/auth/user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'            => $user->id,
                'username'      => $user->username,
                'email'         => $user->email,
                'phone'         => $user->phone,
                // user_type removed
                'is_active'     => $user->is_active,
                'is_verified'   => $user->is_verified,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }
}
