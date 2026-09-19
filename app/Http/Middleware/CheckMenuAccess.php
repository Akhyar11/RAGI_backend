<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Menu;

class CheckMenuAccess
{
    /**
     * Path SPMB self-service (calon mahasiswa) & publik yang tidak
     * memerlukan pengecekan menu. Auth tetap diperlukan (middleware auth:api).
     */
    private const PUBLIC_PATHS = [
        // Data master publik untuk form registrasi
        'GET /spmb/prodi',
        'GET /spmb/jalur',
        'GET /spmb/gelombang',
        'GET /spmb/tahun-akademik',
        'GET /spmb/master-tipe-jalur',
        'GET /spmb/master-jalur-kelas',
        'GET /spmb/tarif',
        'GET /spmb/sekolah-mitra',
        // Pendaftaran mandiri calon mahasiswa
        'GET /spmb/pendaftaran/me',
        'POST /spmb/pendaftaran/biodata',
        'POST /spmb/pendaftaran/berkas',
        'POST /spmb/pendaftaran/finalize',
        'POST /spmb/pendaftaran/reissue-va',
        'POST /spmb/pendaftaran/reset',
        // Daftar ulang mandiri
        'POST /spmb/daftar-ulang/{pendaftaran_id}/generate-tagihan',
        'POST /spmb/daftar-ulang/{pendaftaran_id}/konfirmasi',
        // SIKEU Portal Mahasiswa (Self-Service)
        'GET /sikeu/mahasiswa/payment-channels',
        'GET /sikeu/mahasiswa/tagihan',
        'GET /sikeu/mahasiswa/invoice/{id}',
        'POST /sikeu/mahasiswa/invoice-batch',
        'GET /sikeu/mahasiswa/riwayat-pembayaran',
        'POST /sikeu/mahasiswa/pay-bills',
        'POST /sikeu/dispensasi',
        'GET /sikeu/dispensasi/{id}',
        // SIKEU Payment Callbacks & Webhooks
        'POST /sikeu/callback/va-paid',
        'POST /sikeu/callback/spmb/{calonMahasiswaId}',
        'GET /sikeu/checkout/lookup-va',
    ];

    /**
     * Handle an incoming request dynamically based on DB menu_role relation.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        // Superadmin & Admin bypass
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $next($request);
        }

        // 1. Extract target URL from request path (stripping api/ and api/v1/ prefixes)
        $rawPath = $request->path();
        $targetUrl = '/' . ltrim(preg_replace('#^api/(v\d+/)?#', '', $rawPath), '/');
        $method = $request->method();

        // 2. Self-service & public paths tidak dicek menu (hanya perlu login)
        foreach (self::PUBLIC_PATHS as $publicPath) {
            [$pubMethod, $pubUrl] = explode(' ', $publicPath, 2);
            if ($method === $pubMethod && $this->pathMatches($targetUrl, $pubUrl)) {
                return $next($request);
            }
        }

        // 3. Query database Menu model directly by URL
        $menu = Menu::where('url', $targetUrl)->first();

        // 4. Dynamic DB Menu entity matching fallback
        if (!$menu) {
            $lastSegment = basename($targetUrl);
            $menu = Menu::whereNotNull('url')
                        ->where('url', '!=', '#')
                        ->get()
                        ->first(function ($m) use ($targetUrl, $lastSegment) {
                            return str_contains($m->url, $lastSegment) || str_contains($targetUrl, basename($m->url));
                        });
        }

        if ($menu) {
            $roleIds = $user->roles()->pluck('core_roles.id')->toArray();
            $hasAccess = $menu->roles()->whereIn('core_roles.id', $roleIds)->exists();

            if (!$hasAccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database.'
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Cocokkan path target dengan pola menu URL, termasuk parameter {id}.
     */
    private function pathMatches(string $targetUrl, string $pattern): bool
    {
        if ($targetUrl === $pattern) {
            return true;
        }

        $patternSegments = explode('/', trim($pattern, '/'));
        $targetSegments = explode('/', trim($targetUrl, '/'));

        if (count($patternSegments) !== count($targetSegments)) {
            return false;
        }

        foreach ($patternSegments as $i => $seg) {
            if (str_starts_with($seg, '{') && str_ends_with($seg, '}')) {
                continue;
            }
            if ($seg !== $targetSegments[$i]) {
                return false;
            }
        }

        return true;
    }
}
