<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseConnection
{
    /**
     * Dual-Environment (Produksi vs Demo).
     *
     * Ketika request membawa header `X-Environment: demo`, seluruh query
     * dialihkan ke koneksi database demo (mysql_demo) sehingga API yang sama
     * dapat melayani data produksi maupun data demo secara terisolasi.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Otomatis: semua host dengan awalan "demo" (mis. demo-sso.polinus.cloud,
        // demo-spmb.polinus.cloud) diarahkan ke koneksi database demo.
        $isDemoHost = (bool) preg_match('/^demo([.-]|$)/', $host);

        if ($request->header('X-Environment') === 'demo' || $isDemoHost) {
            DB::setDefaultConnection('mysql_demo');
            config(['database.default' => 'mysql_demo']);
        }

        return $next($request);
    }
}
