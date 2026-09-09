<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DynamicDatabaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Mendeteksi lingkungan demo berdasarkan:
     * 1. Header 'X-Environment: demo'
     * 2. Header 'Origin' atau 'Referer' yang mengandung subdomain 'demo-'
     * 3. Host API yang mengandung prefix 'demo-' (misal demo-api.polinus.cloud)
     *
     * Jika terdeteksi demo, koneksi database dialihkan ke 'mysql_demo'
     * sehingga database produksi tetap bersih dan tidak terkontaminasi.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerEnv = strtolower(trim((string) $request->header('X-Environment', '')));
        $origin = (string) ($request->header('Origin') ?: $request->header('Referer', ''));
        $host = (string) $request->getHost();

        $isDemo = $headerEnv === 'demo'
            || str_contains(strtolower($origin), 'demo-')
            || str_starts_with(strtolower($host), 'demo-');

        if ($isDemo) {
            $demoDatabase = config('database.connections.mysql_demo.database');

            // 1. Alihkan default connection ke mysql_demo jika terdaftar
            if (config('database.connections.mysql_demo')) {
                DB::setDefaultConnection('mysql_demo');
                Config::set('database.default', 'mysql_demo');
            }

            // 2. Pastikan fallback connection 'mysql' juga mengarah ke database demo
            if ($demoDatabase) {
                Config::set('database.connections.mysql.database', $demoDatabase);
                DB::purge('mysql');
            }
        }

        $response = $next($request);

        // Tambahkan header indikator environment pada response untuk transparansi
        $response->headers->set('X-Environment', $isDemo ? 'demo' : 'production');

        return $response;
    }
}
