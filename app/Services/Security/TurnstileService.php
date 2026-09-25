<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TurnstileService — verifikasi token Cloudflare Turnstile (siteverify).
 *
 * - Fail-closed: token kosong/invalid/gagal jaringan => false.
 * - Bila TURNSTILE_SECRET belum di-set, fitur dianggap nonaktif (isConfigured=false)
 *   sehingga middleware tidak memblokir (memudahkan dev tanpa kunci).
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isConfigured(): bool
    {
        return trim((string) config('services.turnstile.secret')) !== '';
    }

    public function verify(?string $token, string $expectedAction = '', ?string $remoteIp = null): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => (string) config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ], fn ($v) => $v !== null && $v !== ''));
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify error: '.$e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile siteverify HTTP '.$response->status());

            return false;
        }

        $data = $response->json();
        if (($data['success'] ?? false) !== true) {
            return false;
        }

        if ($expectedAction !== '' && ($data['action'] ?? '') !== $expectedAction) {
            return false;
        }

        return $this->hostnameAllowed(strtolower((string) ($data['hostname'] ?? '')));
    }

    /**
     * Hostname valid bila sama persis dengan entri allowlist, atau merupakan
     * subdomain dari entri (mis. entri "ragispace.com" mencakup "sso.ragispace.com").
     */
    protected function hostnameAllowed(string $hostname): bool
    {
        $allowed = (array) config('services.turnstile.hostnames', []);
        if ($hostname === '' || $allowed === []) {
            return false;
        }

        foreach ($allowed as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }
            if ($hostname === $entry || str_ends_with($hostname, '.'.$entry)) {
                return true;
            }
        }

        return false;
    }
}
