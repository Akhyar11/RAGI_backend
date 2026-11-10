<?php

namespace App\Http\Middleware;

use App\Models\Sikeu\PaymentGatewayConfig;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware untuk memvalidasi otentikasi request dari Bank Host-to-Host (BTNS).
 * Mendukung header `Authorization: Bearer <token>` maupun header warisan `sopingi-btns`.
 */
class H2hBankAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = $this->expectedToken();

        if (empty($expectedToken)) {
            $expectedToken = app()->environment(['local', 'testing']) ? 'test-btns-token' : null;
        }

        $bearerToken = $request->bearerToken();
        $customHeader = $request->header('sopingi-btns');
        $provided = $bearerToken ?: $customHeader;

        if (empty($expectedToken) || empty($provided) || !hash_equals($expectedToken, (string) $provided)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API Token Bank Tidak Valid atau Tidak Ditemukan',
                'rc'      => '401',
                'msg'     => 'API Token Bank Tidak Valid atau Tidak Ditemukan',
            ], 401);
        }

        return $next($request);
    }

    protected function expectedToken(): ?string
    {
        // 1. Cek dari konfigurasi Payment Gateway di database
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'bsn_h2h')->first();
            if ($config && !empty($config->api_key_encrypted)) {
                return (string) $config->api_key_encrypted;
            }
        } catch (\Throwable $e) {
            // fallback bila DB query gagal
        }

        // 2. Fallback ke environment variable
        $envToken = config('services.sikeu.btns_api_token');

        return !empty($envToken) ? (string) $envToken : null;
    }
}
