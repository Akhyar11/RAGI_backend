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
        if ($request->header('X-Environment') === 'demo') {
            DB::setDefaultConnection('mysql_demo');
            config(['database.default' => 'mysql_demo']);
        }

        return $next($request);
    }
}
