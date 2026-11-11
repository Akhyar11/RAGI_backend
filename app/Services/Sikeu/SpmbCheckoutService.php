<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\PaymentCheckout;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpmbCheckoutService
{
    public function checkout(int $billId): array
    {
        $prepared = DB::transaction(function () use ($billId): array {
            $bill = TagihanMahasiswa::findOrFail($billId);
            PendaftaranCalonMhs::whereKey($bill->calon_mahasiswa_id)->lockForUpdate()->firstOrFail();
            $bill = TagihanMahasiswa::query()->lockForUpdate()->findOrFail($billId);
            $remaining = max(0, (int) round(((float) $bill->total_tagihan + (float) $bill->total_denda
                - (float) $bill->total_potongan - (float) $bill->total_bayar) * 100));
            if ($bill->status === 'lunas' || $remaining === 0 || $bill->status === 'pending_approval') {
                throw ValidationException::withMessages(['tagihan' => 'Tagihan sudah lunas atau belum disetujui untuk pembayaran.']);
            }
            if (! $bill->payment_gateway_config_id) {
                $va = $bill->virtualAccount;
                if (! $va || $va->status !== 'aktif' || $va->expired_at?->isPast()) {
                    throw ValidationException::withMessages(['tagihan' => 'Virtual Account lama tidak tersedia atau kedaluwarsa. Hubungi panitia SPMB.']);
                }

                return ['response' => ['method' => 'virtual_account', 'gateway_name' => null, 'amount' => $remaining / 100,
                    'va_number' => $va->va_number, 'bank_name' => $va->bank_nama, 'expires_at' => $va->expired_at]];
            }
            $gateway = $bill->paymentGateway;
            if (! $gateway || ! $gateway->is_active || $gateway->gateway_name !== $bill->payment_gateway_name
                || $gateway->environment !== $bill->payment_gateway_environment) {
                throw ValidationException::withMessages(['payment_gateway_config_id' => 'Kanal pembayaran tagihan tidak aktif atau konfigurasinya telah berubah. Hubungi panitia SPMB.']);
            }
            if ($gateway->gateway_name === PaymentGatewayConfig::H2H) {
                try {
                    $billing = BsnH2hService::terbitkanBilling($bill);
                } catch (\RuntimeException $exception) {
                    Log::error('Checkout H2H gagal', ['tagihan_id' => $bill->id, 'error' => $exception->getMessage()]);
                    throw new HttpException(502, 'Layanan H2H belum dapat menerbitkan billing. Silakan coba kembali.');
                }

                return ['response' => array_merge($billing, ['method' => 'h2h', 'gateway_name' => $gateway->gateway_name,
                    'amount' => $remaining / 100, 'bank_name' => 'BTN Syariah'])];
            }
            if (! in_array($gateway->gateway_name, [PaymentGatewayConfig::MIDTRANS, PaymentGatewayConfig::XENDIT], true)
                || empty($gateway->api_key_encrypted)
                || ($gateway->gateway_name === PaymentGatewayConfig::XENDIT && empty($gateway->webhook_token_encrypted))) {
                throw ValidationException::withMessages(['payment_gateway_config_id' => 'Kredensial kanal pembayaran belum lengkap. Hubungi panitia SPMB.']);
            }
            if ($remaining % 100 !== 0) {
                throw ValidationException::withMessages(['tagihan' => 'Nominal pembayaran online harus berupa rupiah bulat. Hubungi panitia SPMB.']);
            }
            $existing = $bill->checkouts()->where('status', 'pending')->where('amount', $remaining / 100)
                ->where('expires_at', '>', now())->whereNotNull('redirect_url')->latest('id')->first();
            if ($existing) {
                return ['response' => $this->response($existing)];
            }
            if ($bill->checkouts()->where('status', 'creating')->where('created_at', '>', now()->subMinute())->exists()) {
                throw ValidationException::withMessages(['tagihan' => 'Checkout sedang disiapkan. Silakan coba beberapa saat lagi.']);
            }
            $attempt = PaymentCheckout::create([
                'tagihan_id' => $bill->id, 'payment_gateway_config_id' => $gateway->id,
                'gateway_name' => $gateway->gateway_name, 'environment' => $gateway->environment,
                'order_id' => 'SPMB-'.$bill->id.'-'.Str::ulid(), 'amount' => $remaining / 100,
                'status' => 'creating', 'expires_at' => now()->addDay(),
            ]);

            return ['attempt' => $attempt, 'gateway' => $gateway, 'bill' => $bill];
        });
        if (isset($prepared['response'])) {
            return $prepared['response'];
        }
        $attempt = $prepared['attempt'];
        $gateway = $prepared['gateway'];
        $application = $prepared['bill']->calonMahasiswa;
        $amount = (int) $attempt->amount;
        $returnUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/spmb/pembayaran?tahap='
            .($prepared['bill']->tipe_referensi === TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG ? 'daftar_ulang' : 'pendaftaran');
        $client = Http::withBasicAuth($gateway->api_key_encrypted, '')->acceptJson()->timeout(20);
        try {
            if ($gateway->gateway_name === PaymentGatewayConfig::MIDTRANS) {
                $endpoint = $gateway->environment === 'production' ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
                $response = $client->post($endpoint, [
                    'transaction_details' => ['order_id' => $attempt->order_id, 'gross_amount' => $amount],
                    'customer_details' => array_filter(['first_name' => Str::limit($application->nama_lengkap, 50, ''),
                        'email' => $application->email ?: $application->user?->email, 'phone' => $application->no_hp]),
                    'expiry' => ['unit' => 'hours', 'duration' => 24],
                    'callbacks' => ['finish' => $returnUrl],
                ]);
                $url = $response->json('redirect_url');
                $token = $response->json('token');
                $providerId = null;
            } else {
                $response = $client->post('https://api.xendit.co/v2/invoices', [
                    'external_id' => $attempt->order_id, 'amount' => $amount, 'currency' => 'IDR', 'invoice_duration' => 86400,
                    'description' => 'Pembayaran '.$prepared['bill']->nomor_tagihan,
                    'payer_email' => $application->email ?: $application->user?->email,
                    'success_redirect_url' => $returnUrl,
                    'failure_redirect_url' => $returnUrl,
                ]);
                $url = $response->json('invoice_url');
                $token = null;
                $providerId = $response->json('id');
            }
            if (! $response->successful() || ! $this->validRedirect($url, $gateway->gateway_name)
                || ($gateway->gateway_name === PaymentGatewayConfig::MIDTRANS && ! is_string($token))
                || ($gateway->gateway_name === PaymentGatewayConfig::XENDIT && ! is_string($providerId))) {
                $attempt->update(['status' => 'failed']);
                Log::error('Provider menolak checkout SPMB', ['tagihan_id' => $attempt->tagihan_id, 'gateway' => $gateway->gateway_name, 'http_status' => $response->status()]);
                throw new HttpException(502, 'Layanan pembayaran belum dapat membuat checkout. Silakan coba kembali.');
            }
            $attempt->refresh();
            $attempt->update(['status' => $attempt->status === 'paid' ? 'paid' : 'pending', 'token' => $token, 'redirect_url' => $url, 'provider_id' => $providerId]);

            return $this->response($attempt);
        } catch (ConnectionException $exception) {
            $attempt->update(['status' => 'failed']);
            Log::error('Koneksi checkout SPMB gagal', ['tagihan_id' => $attempt->tagihan_id, 'gateway' => $gateway->gateway_name]);
            throw new HttpException(502, 'Layanan pembayaran tidak dapat dihubungi. Silakan coba kembali.');
        }
    }

    private function response(PaymentCheckout $attempt): array
    {
        return ['method' => 'redirect', 'gateway_name' => $attempt->gateway_name, 'environment' => $attempt->environment,
            'order_id' => $attempt->order_id, 'amount' => (float) $attempt->amount, 'redirect_url' => $attempt->redirect_url,
            'expires_at' => $attempt->expires_at];
    }

    private function validRedirect(mixed $url, string $gateway): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $domain = $gateway === PaymentGatewayConfig::MIDTRANS ? 'midtrans.com' : 'xendit.co';

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
