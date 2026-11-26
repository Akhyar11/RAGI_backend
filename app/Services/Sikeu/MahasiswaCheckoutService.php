<?php

namespace App\Services\Sikeu;

use App\Models\SIAKAD\Mahasiswa;
use App\Models\Sikeu\PaymentCheckout;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MahasiswaCheckoutService
{
    /**
     * Kanal pembayaran aktif yang dikonfigurasi admin (Midtrans / Xendit / BSN H2H).
     */
    public function getPaymentChannels(): array
    {
        $activeGateway = PaymentGatewayConfig::active();
        $bsnConfig = PaymentGatewayConfig::where('gateway_name', 'bsn_h2h')
            ->where('is_active', true)
            ->first();

        return [
            'active_gateway' => $activeGateway?->only([
                'id', 'gateway_name', 'environment', 'is_active', 'is_default',
            ]),
            'bsn_h2h_active' => (bool) $bsnConfig,
        ];
    }

    /**
     * Hitung nomor VA dinamis berdasarkan bank & identifier.
     */
    public function computeVaNumber(string $bankCode, string $identifier): string
    {
        $cleanId = preg_replace('/[^0-9]/', '', $identifier);
        if (empty($cleanId)) {
            $cleanId = '20240001';
        }

        switch (strtoupper($bankCode)) {
            case 'MANDIRI':
                return VaNumberService::generate($cleanId, '70012');
            case 'BSN':
                return VaNumberService::generate($cleanId, '90012');
            case 'BRI':
                return VaNumberService::generate($cleanId, '12345');
            case 'BSI':
                return VaNumberService::generate($cleanId, '70012');
            case 'BCA':
                return VaNumberService::generate($cleanId, '10204');
            case 'PERMATA':
                return VaNumberService::generate($cleanId, '85220');
            case 'QRIS':
                return 'QRIS-' . date('ymd') . '-' . str_pad($cleanId, 6, '0', STR_PAD_LEFT);
            case 'BNI':
            default:
                return VaNumberService::generate($cleanId);
        }
    }

    /**
     * Terbitkan consolidated official invoice & VA untuk tagihan yang dipilih.
     */
    public function generateBatchInvoice(int $mahasiswaId, array $tagihanIds = [], string $bankCode = 'BSN'): array
    {
        $bankCode = strtoupper($bankCode ?: 'BSN');

        $query = TagihanMahasiswa::with(['details.masterBiaya', 'virtualAccount', 'mahasiswa.programStudi', 'tipeTagihanMahasiswa'])
            ->where('mahasiswa_id', $mahasiswaId);

        if (!empty($tagihanIds)) {
            $query->whereIn('id', $tagihanIds);
        }

        $tagihans = $query->orderBy('id', 'asc')->get();

        if ($tagihans->isEmpty()) {
            throw ValidationException::withMessages([
                'tagihan_ids' => 'Tidak ada tagihan yang dipilih atau tagihan tidak ditemukan.',
            ]);
        }

        $firstTagihan = $tagihans->first();
        $mhs = $firstTagihan->mahasiswa;
        $tipeMhs = $firstTagihan->tipeTagihanMahasiswa;

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ($mahasiswaId ? (string) $mahasiswaId : '-');
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $mahasiswaId);
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? '-';
        $angkatan = $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? null;

        $totalTagihan = 0;
        $totalPotongan = 0;
        $totalDenda = 0;
        $totalBayar = 0;
        $allItems = [];
        $periodeLabels = [];

        foreach ($tagihans as $t) {
            $totalTagihan += (float) $t->total_tagihan;
            $totalPotongan += (float) $t->total_potongan;
            $totalDenda += (float) $t->total_denda;
            $totalBayar += (float) $t->total_bayar;

            $lbl = $t->semester ? ('Semester ' . $t->semester) : 'Tagihan Kampus';
            if (!in_array($lbl, $periodeLabels, true)) {
                $periodeLabels[] = $lbl;
            }

            foreach ($t->details as $d) {
                $allItems[] = [
                    'deskripsi' => $d->keterangan ?: ($d->masterBiaya->nama ?? 'Komponen Biaya'),
                    'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Kuliah',
                    'tagihan_nomor' => $t->nomor_tagihan,
                    'nominal' => (float) $d->nominal,
                    'potongan' => (float) $d->potongan,
                    'nominal_bersih' => (float) $d->nominal_bersih,
                ];
            }
        }

        $totalSisa = max(0, ($totalTagihan + $totalDenda - $totalPotongan) - $totalBayar);
        $vaNumber = $this->computeVaNumber($bankCode, $nim);

        $invoiceNumber = count($tagihans) === 1
            ? 'INV-' . $firstTagihan->nomor_tagihan
            : 'INV-GABUNGAN-' . date('Ymd') . '-' . Str::upper(Str::random(4));

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'tanggal_terbit' => date('Y-m-d'),
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
            'periode' => implode(', ', $periodeLabels),
            'mahasiswa' => [
                'nama' => $nama,
                'nim' => $nim,
                'prodi' => $prodi,
                'angkatan' => $angkatan,
            ],
            'virtual_account' => [
                'bank' => $bankCode === 'QRIS' ? 'QRIS Indonesia' : ('Bank ' . $bankCode . ' (Virtual Account)'),
                'bank_kode' => $bankCode,
                'va_number' => $vaNumber,
                'nominal_instruksi' => (float) $totalSisa,
                'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ],
            'ringkasan' => [
                'subtotal' => (float) $totalTagihan,
                'potongan' => (float) $totalPotongan,
                'denda' => (float) $totalDenda,
                'total_dibayar' => (float) $totalBayar,
                'sisa_tagihan' => (float) $totalSisa,
                'status' => $totalSisa <= 0 ? 'lunas' : ($totalBayar > 0 ? 'sebagian' : 'belum_bayar'),
                'jumlah_tagihan_terpilih' => count($tagihans),
            ],
            'items' => $allItems,
        ];

        try {
            AuditLogService::record(
                action: 'terbitkan_invoice_batch',
                module: 'sikeu',
                targetModel: TagihanMahasiswa::class,
                targetId: $firstTagihan->id,
                oldValues: null,
                newValues: [
                    'invoice_number' => $invoiceNumber,
                    'va_number' => $vaNumber,
                    'bank_kode' => $bankCode,
                    'total_sisa' => $totalSisa,
                    'tagihan_ids' => $tagihans->pluck('id')->all(),
                ],
                description: "Mahasiswa #{$nim} menerbitkan invoice batch {$invoiceNumber} untuk " . count($tagihans) . " tagihan.",
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log generateBatchInvoice: ' . $e->getMessage());
        }

        return $invoiceData;
    }

    /**
     * Terbitkan billing H2H BSN untuk tagihan mahasiswa dan catat audit log.
     */
    public function terbitkanH2h(Mahasiswa $mhs, int $tagihanId, bool $force = false): array
    {
        $tagihan = TagihanMahasiswa::where('mahasiswa_id', $mhs->id)->findOrFail($tagihanId);
        $oldValues = $tagihan->getOriginal();

        $hasil = BsnH2hService::terbitkanBilling($tagihan, $force);
        $tagihanFresh = $tagihan->fresh();
        $newValues = array_intersect_key($tagihanFresh->getAttributes(), array_diff_assoc($tagihanFresh->getAttributes(), $oldValues));

        try {
            AuditLogService::record(
                action: 'terbitkan_billing_h2h',
                module: 'sikeu',
                targetModel: TagihanMahasiswa::class,
                targetId: $tagihan->id,
                oldValues: $oldValues,
                newValues: $newValues ?: ['custid' => $hasil['custid'] ?? null],
                description: "Mahasiswa menerbitkan billing H2H BSN CUSTID " . ($hasil['custid'] ?? '-') . " untuk tagihan #{$tagihan->nomor_tagihan}",
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log terbitkan billing H2H: ' . $e->getMessage());
        }

        return [
            'hasil' => $hasil,
            'tagihan' => $tagihanFresh,
        ];
    }

    /**
     * Buat transaksi Payment Gateway aktif (Midtrans Snap / Xendit) untuk tagihan mahasiswa.
     */
    public function checkoutGateway(Mahasiswa $mhs, int $tagihanId, ?string $userEmail = null): array
    {
        $tagihan = TagihanMahasiswa::where('mahasiswa_id', $mhs->id)->findOrFail($tagihanId);

        if ($tagihan->status === 'lunas') {
            throw ValidationException::withMessages(['tagihan' => 'Tagihan ini sudah lunas.']);
        }

        $sisa = (int) round(max(0, ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - $tagihan->total_bayar));
        if ($sisa <= 0) {
            throw ValidationException::withMessages(['tagihan' => 'Sisa tagihan adalah Rp 0.']);
        }

        $active = PaymentGatewayConfig::active();
        if (! $active) {
            throw ValidationException::withMessages(['gateway' => 'Belum ada Payment Gateway aktif yang dikonfigurasi admin.']);
        }

        if ($active->gateway_name === PaymentGatewayConfig::H2H) {
            $h2hResult = $this->terbitkanH2h($mhs, $tagihanId);
            return [
                'gateway_name' => PaymentGatewayConfig::H2H,
                'h2h' => $h2hResult,
            ];
        }

        if (empty($active->api_key_encrypted)) {
            throw ValidationException::withMessages(['gateway' => 'Kredensial Payment Gateway aktif belum dikonfigurasi admin.']);
        }

        $existing = PaymentCheckout::where('tagihan_id', $tagihan->id)
            ->where('gateway_name', $active->gateway_name)
            ->where('status', 'pending')
            ->where('amount', $sisa)
            ->where('expires_at', '>', now())
            ->whereNotNull('redirect_url')
            ->latest('id')
            ->first();

        if ($existing) {
            return [
                'gateway_name' => $existing->gateway_name,
                'order_id' => $existing->order_id,
                'amount' => (float) $existing->amount,
                'token' => $existing->token,
                'redirect_url' => $existing->redirect_url,
                'expires_at' => $existing->expires_at,
            ];
        }

        $orderId = 'TAG-' . $tagihan->id . '-' . time();
        $attempt = PaymentCheckout::create([
            'tagihan_id' => $tagihan->id,
            'payment_gateway_config_id' => $active->id,
            'gateway_name' => $active->gateway_name,
            'environment' => $active->environment,
            'order_id' => $orderId,
            'amount' => $sisa,
            'status' => 'creating',
            'expires_at' => now()->addDay(),
        ]);

        try {
            AuditLogService::record(
                action: 'create_checkout_attempt',
                module: 'sikeu',
                targetModel: PaymentCheckout::class,
                targetId: $attempt->id,
                oldValues: null,
                newValues: $attempt->toArray(),
                description: "Inisiasi transaksi checkout order {$orderId} untuk tagihan #{$tagihan->nomor_tagihan}",
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log create checkout attempt: ' . $e->getMessage());
        }

        $apiKey = $active->api_key_encrypted;
        try {
            $apiKey = Crypt::decryptString($active->getRawOriginal('api_key_encrypted'));
        } catch (\Throwable) {
            $apiKey = $active->api_key_encrypted;
        }

        $client = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->timeout(20);
        $returnUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        if ($active->gateway_name === PaymentGatewayConfig::MIDTRANS) {
            $endpoint = $active->environment === 'production'
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
            $response = $client->post($endpoint, [
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $sisa],
                'customer_details' => array_filter([
                    'first_name' => Str::limit($mhs->nama_lengkap ?? 'Mahasiswa', 50, ''),
                    'email' => $mhs->email ?: $userEmail,
                    'phone' => $mhs->telepon,
                ]),
                'expiry' => ['unit' => 'hours', 'duration' => 24],
                'callbacks' => ['finish' => $returnUrl],
            ]);
            $url = $response->json('redirect_url');
            $token = $response->json('token');
            $providerId = null;
        } else {
            $response = $client->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $orderId,
                'amount' => $sisa,
                'currency' => 'IDR',
                'invoice_duration' => 86400,
                'description' => 'Pembayaran ' . $tagihan->nomor_tagihan,
                'payer_email' => $mhs->email ?: $userEmail ?: 'mahasiswa@poltekindonusa.ac.id',
                'success_redirect_url' => $returnUrl,
                'failure_redirect_url' => $returnUrl,
            ]);
            $url = $response->json('invoice_url');
            $token = null;
            $providerId = $response->json('id');
        }

        if (! $response->successful() || empty($url)) {
            $oldValues = $attempt->getOriginal();
            $attempt->update(['status' => 'failed']);
            $newValues = $attempt->getChanges();

            try {
                AuditLogService::record(
                    action: 'checkout_payment_gateway_failed',
                    module: 'sikeu',
                    targetModel: PaymentCheckout::class,
                    targetId: $attempt->id,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    description: "Checkout pembayaran tagihan #{$tagihan->nomor_tagihan} via {$active->gateway_name} gagal dari upstream.",
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log checkout failed: ' . $e->getMessage());
            }

            Log::error('Checkout mahasiswa gagal', [
                'tagihan_id' => $tagihan->id,
                'gateway' => $active->gateway_name,
                'status' => $response->status(),
            ]);
            throw new HttpException(502, 'Layanan pembayaran belum dapat membuat transaksi. Silakan coba kembali.');
        }

        $oldValues = $attempt->getOriginal();
        $attempt->update([
            'status' => 'pending',
            'token' => $token,
            'redirect_url' => $url,
            'provider_id' => $providerId,
        ]);
        $newValues = $attempt->getChanges();

        try {
            AuditLogService::record(
                action: 'checkout_payment_gateway',
                module: 'sikeu',
                targetModel: PaymentCheckout::class,
                targetId: $attempt->id,
                oldValues: $oldValues,
                newValues: $newValues,
                description: "Mahasiswa menginisiasi checkout pembayaran tagihan #{$tagihan->nomor_tagihan} via {$active->gateway_name}",
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log checkout: ' . $e->getMessage());
        }

        return [
            'gateway_name' => $active->gateway_name,
            'order_id' => $orderId,
            'amount' => (float) $sisa,
            'token' => $token,
            'redirect_url' => $url,
            'expires_at' => $attempt->expires_at,
        ];
    }
}
