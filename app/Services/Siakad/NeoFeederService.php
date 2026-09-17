<?php

namespace App\Services\Siakad;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class NeoFeederService
{
    /**
     * Dapatkan konfigurasi Feeder dari SystemSetting (IAM → Pengaturan Sistem).
     * Sumber kebenaran tunggal adalah database; tidak ada fallback .env
     * agar kredensial hanya dikelola dari halaman IAM/Settings.
     */
    public function getConfig()
    {
        $url = SystemSetting::where('key', 'feeder_url')->value('value') ?? '';
        $username = SystemSetting::where('key', 'feeder_username')->value('value') ?? '';
        $password = SystemSetting::where('key', 'feeder_password')->value('value') ?? '';

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
     * Ubah exception teknis menjadi penjelasan yang dimengerti admin.
     * Tidak pernah menyertakan username/password.
     */
    public function describeTokenError(\Throwable $e, array $config): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'belum disetting')) {
            return 'Konfigurasi Neo Feeder belum disetting (URL atau Username kosong). Silakan atur di IAM → Pengaturan Sistem.';
        }

        if (str_contains($msg, 'cURL error 7')) {
            $host = parse_url((string) $config['url'], PHP_URL_HOST) ?: (string) $config['url'];
            $port = parse_url((string) $config['url'], PHP_URL_PORT);
            $endpoint = $port ? "{$host}:{$port}" : $host;

            return "Tidak dapat terhubung ke {$endpoint} (koneksi ditolak/jaringan). Periksa firewall egress & whitelist IP server di sisi Feeder.";
        }

        if (str_contains($msg, 'cURL error 28')) {
            return 'Koneksi ke WS Feeder timeout (tidak merespons). Coba lagi atau periksa jaringan/server Feeder.';
        }

        if (preg_match('/salah|invalid|password|username|kredensial|ditolak|denied|unauthor/i', $msg)) {
            return 'Kredensial ditolak oleh WS Feeder (username/password salah). Periksa kembali isian di IAM Settings lalu Simpan.';
        }

        return 'Gagal terhubung ke Web Service Neo Feeder: ' . mb_substr($msg, 0, 160);
    }

    /**
     * Dapatkan Token Feeder secara STRICT (dengan Caching)
     * Mode STRICT: Tidak ada token staging/simulasi palsu. Jika WS Feeder
     * tidak terjangkau atau kredensial salah, exception dilempar (fail-fast).
     */
    public function getToken(): string
    {
        return Cache::remember('neo_feeder_token', 3600, function () {
            $config = $this->getConfig();

            if (empty($config['url']) || empty($config['username'])) {
                throw new \RuntimeException("Konfigurasi Neo Feeder belum disetting (URL atau Username kosong). Silakan atur di IAM → Pengaturan Sistem.");
            }

            try {
                $response = Http::timeout(15)->post($config['url'], [
                    'act' => 'GetToken',
                    'username' => $config['username'],
                    'password' => $config['password'],
                ]);

                if (!$response->successful()) {
                    throw new \RuntimeException("Server Neo Feeder mengembalikan HTTP status {$response->status()}.");
                }

                $result = $response->json();

                if (isset($result['error_code']) && $result['error_code'] == 0 && !empty($result['data']['token'])) {
                    return $result['data']['token'];
                }

                $errorDesc = $result['error_desc'] ?? 'Respon tidak valid dari Neo Feeder.';
                throw new \RuntimeException("Autentikasi Neo Feeder gagal: {$errorDesc}");

            } catch (\Exception $e) {
                Cache::forget('neo_feeder_token');
                $friendlyError = $this->describeTokenError($e, $config);
                Log::error('Neo Feeder Connection Failed: ' . $e->getMessage());
                throw new \RuntimeException($friendlyError, 0, $e);
            }
        });
    }

    /**
     * Request ke endpoint Feeder (STRICT Mode)
     * Mengembalikan response asli dari Web Service Neo Feeder.
     * Jika terjadi kegagalan jaringan atau timeout, exception dilempar (fail-fast).
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
            $response = Http::timeout(120)->post($config['url'], $payload);

            if (!$response->successful()) {
                throw new \RuntimeException("WS Neo Feeder mengembalikan HTTP {$response->status()} pada aksi {$act}.");
            }

            $result = $response->json();

            // Token expired (error_code 100 di Neo Feeder), refresh sekali
            if (isset($result['error_code']) && $result['error_code'] == 100) { 
                Cache::forget('neo_feeder_token');
                $payload['token'] = $this->getToken();
                $response = Http::timeout(120)->post($config['url'], $payload);
                $result = $response->json();
            }

            if (is_array($result) && isset($result['error_code'])) {
                return $result;
            }

            throw new \RuntimeException("Respon tidak valid dari Neo Feeder pada aksi {$act}.");

        } catch (\Exception $e) {
            Log::error("Neo Feeder Request Failed ({$act}): " . $e->getMessage());
            throw new \RuntimeException("Permintaan ke Neo Feeder gagal ({$act}): " . $e->getMessage(), 0, $e);
        }
    }
}
