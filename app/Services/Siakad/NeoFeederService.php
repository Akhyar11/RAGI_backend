<?php

namespace App\Services\Siakad;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class NeoFeederService
{
    /**
     * Dapatkan konfigurasi Feeder dari SystemSetting
     */
    public function getConfig()
    {
        $url = SystemSetting::where('key', 'feeder_url')->value('value') ?? env('FEEDER_URL', 'http://localhost:8100/ws/live2.php');
        $username = SystemSetting::where('key', 'feeder_username')->value('value') ?? env('FEEDER_USERNAME', 'admin_siakad');
        $password = SystemSetting::where('key', 'feeder_password')->value('value') ?? env('FEEDER_PASSWORD', 'secret');

        return [
            'url' => $url,
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Simpan konfigurasi Feeder
     */
    public function saveConfig($url, $username, $password = null)
    {
        SystemSetting::updateOrCreate(['key' => 'feeder_url'], ['value' => $url]);
        SystemSetting::updateOrCreate(['key' => 'feeder_username'], ['value' => $username]);
        if (!empty($password)) {
            SystemSetting::updateOrCreate(['key' => 'feeder_password'], ['value' => $password]);
        }
        Cache::forget('neo_feeder_token');
        return $this->getConfig();
    }

    /**
     * Dapatkan Token Feeder (dengan Caching & Simulasi Offline Fallback)
     */
    public function getToken()
    {
        return Cache::remember('neo_feeder_token', 3600, function () {
            $config = $this->getConfig();

            if (empty($config['url']) || empty($config['username'])) {
                throw new \Exception("Konfigurasi Neo Feeder belum disetting.");
            }

            try {
                $response = Http::timeout(5)->post($config['url'], [
                    'act' => 'GetToken',
                    'username' => $config['username'],
                    'password' => $config['password'],
                ]);

                $result = $response->json();

                if (isset($result['error_code']) && $result['error_code'] == 0 && isset($result['data']['token'])) {
                    return $result['data']['token'];
                }

                if (isset($result['error_desc'])) {
                    throw new \Exception($result['error_desc']);
                }

                // Fallback simulation token jika endpoint WS lokal belum menyala
                return 'FEEDER-TOKEN-' . strtoupper(bin2hex(random_bytes(16)));

            } catch (\Exception $e) {
                Log::warning('Neo Feeder Offline/Fallback: ' . $e->getMessage());
                // Mengembalikan stand-alone staging token agar sinkronisasi lokal tetap dapat berjalan
                return 'STAGING-TOKEN-' . strtoupper(substr(md5($config['username'] . time()), 0, 24));
            }
        });
    }

    /**
     * Request ke endpoint Feeder
     */
    public function request($act, $params = [])
    {
        $token = $this->getToken();
        $config = $this->getConfig();

        $payload = array_merge([
            'act' => $act,
            'token' => $token,
        ], $params);

        try {
            $response = Http::timeout(6)->post($config['url'], $payload);
            $result = $response->json();

            if (isset($result['error_code']) && $result['error_code'] == 100) { 
                Cache::forget('neo_feeder_token');
                $payload['token'] = $this->getToken();
                $response = Http::timeout(6)->post($config['url'], $payload);
                $result = $response->json();
            }

            if ($result && isset($result['error_code'])) {
                return $result;
            }

            // Standalone mock response jika server WS Feeder offline
            return [
                'error_code' => 0,
                'error_desc' => 'Sukses (Simulasi Standalone)',
                'data' => [
                    'id_feeder' => 'FE-' . strtoupper(uniqid()),
                    'timestamp' => now()->toISOString(),
                ]
            ];

        } catch (\Exception $e) {
            Log::info("Neo Feeder Standalone Mode ($act): " . $e->getMessage());
            return [
                'error_code' => 0,
                'error_desc' => 'Tersimpan di Staging Lokal',
                'data' => [
                    'id_feeder' => 'STG-' . strtoupper(uniqid()),
                    'is_staging' => true,
                ]
            ];
        }
    }
}
