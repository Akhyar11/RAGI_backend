<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

class ApiIntegrationService
{
    /**
     * Ambil konfigurasi integrasi API berdasarkan key sistem
     */
    public static function get(string $key): ?IamApiIntegration
    {
        return IamApiIntegration::where('key', $key)->first();
    }

    /**
     * Buat HTTP Request Client yang sudah dikonfigurasi auth token/header
     */
    public static function client(IamApiIntegration $integration, int $timeoutSeconds = 15)
    {
        $http = Http::timeout($timeoutSeconds)->acceptJson();

        if (!empty($integration->token)) {
            if (strtolower($integration->auth_type ?? 'bearer') === 'bearer') {
                $http = $http->withToken(trim($integration->token));
            } else {
                $http = $http->withHeaders([
                    'X-API-KEY' => trim($integration->token),
                ]);
            }
        }

        return $http;
    }

    /**
     * Eksekusi pemanggilan endpoint integrasi oleh modul internal (misal SPMB)
     */
    public static function call(string $key, array $queryParams = [], array $bodyParams = []): Response
    {
        $integration = self::get($key);

        if (!$integration) {
            throw new RuntimeException("Integrasi API dengan key '{$key}' belum terdaftar di sistem.");
        }

        if (!$integration->is_active) {
            throw new RuntimeException("Integrasi API '{$integration->name}' sedang dinonaktifkan.");
        }

        if (empty($integration->url_api)) {
            throw new RuntimeException("URL API untuk '{$integration->name}' belum dikonfigurasi di pengaturan IAM.");
        }

        $client = self::client($integration);
        $method = strtoupper($integration->http_method ?? 'GET');

        if ($method === 'GET') {
            return $client->get($integration->url_api, $queryParams);
        }

        if ($method === 'POST') {
            return $client->post($integration->url_api, $bodyParams);
        }

        return $client->send($method, $integration->url_api, [
            'query' => $queryParams,
            'json' => $bodyParams,
        ]);
    }
}
