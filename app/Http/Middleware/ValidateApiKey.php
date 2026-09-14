<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.attendance_api.key', env('ATTENDANCE_API_KEY', 'indo_absen_sec_2026_x89a7f3d'));

        if (empty($configuredKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan API Integrasi belum dikonfigurasi pada server (ATTENDANCE_API_KEY kosong).',
            ], 500);
        }

        $apiKey = $request->header('X-API-KEY');

        if (!$apiKey && $request->hasHeader('Authorization')) {
            $authHeader = $request->header('Authorization');
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $apiKey = $matches[1];
            }
        }

        if (!$apiKey) {
            $apiKey = $request->query('api_key');
        }

        if (!$apiKey || !hash_equals((string) $configuredKey, (string) $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: API Key tidak valid atau tidak disertakan pada header X-API-KEY.',
            ], 401);
        }

        return $next($request);
    }
}
