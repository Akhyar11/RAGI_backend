<?php

namespace App\Http\Middleware;

use App\Models\Sikeu\PaymentGatewayConfig;
use Closure;
use Illuminate\Http\Request;

/**
 * Memverifikasi token callback/webhook payment gateway.
 *
 * Token diambil dari PaymentGatewayConfig.webhook_token_encrypted (gateway aktif)
 * atau env SIKEU_CALLBACK_TOKEN. Bila token belum dikonfigurasi, callback hanya
 * diizinkan pada environment local/testing; pada produksi ditolak.
 */
class VerifyPaymentCallbackToken
{
    public function handle(Request $request, Closure $next)
    {
        $provided = $request->header('x-callback-token') ?? $request->input('callback_token');
        $expected = $this->expectedToken();

        if (! empty($expected)) {
            if (! is_string($provided) || ! hash_equals($expected, (string) $provided)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Callback token tidak valid.',
                ], 403);
            }

            return $next($request);
        }

        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Callback token belum dikonfigurasi pada server.',
        ], 403);
    }

    protected function expectedToken(): ?string
    {
        $config = PaymentGatewayConfig::where('is_active', true)->first();
        $token = $config?->webhook_token_encrypted;

        if (! empty($token)) {
            return (string) $token;
        }

        $envToken = config('services.sikeu.callback_token') ?: env('SIKEU_CALLBACK_TOKEN');

        return ! empty($envToken) ? (string) $envToken : null;
    }
}
