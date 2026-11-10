<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\MidtransCallbackRequest;
use App\Http\Requests\Sikeu\MidtransSnapTokenRequest;
use App\Http\Requests\Sikeu\MidtransTrialRequest;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\VirtualAccount;
use App\Services\AuditLogService;
use App\Services\Sikeu\AutoJournalService;
use App\Services\Sikeu\JurnalSikeuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransCallbackController extends Controller
{
    /**
     * Webhook Handler Resmi Midtrans (HTTP POST /api/v1/sikeu/callback/midtrans)
     */
    public function handleCallback(MidtransCallbackRequest $request): JsonResponse
    {
        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $signatureKey = (string) $request->input('signature_key');
        $transactionStatus = (string) $request->input('transaction_status');
        $fraudStatus = (string) ($request->input('fraud_status') ?? 'accept');
        $paymentType = (string) ($request->input('payment_type') ?? 'qris');
        $transactionId = (string) ($request->input('transaction_id') ?? $orderId);

        Log::info("Midtrans Webhook Received [{$orderId} - {$transactionStatus}]", [
            'payment_type' => $paymentType,
            'gross_amount' => $grossAmount,
        ]);

        // 1. Validasi Keamanan Signature Key SHA512
        $serverKey = $this->resolveServerKey();
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($expectedSignature, $signatureKey)) {
            Log::warning("Midtrans Invalid Signature Key for Order: {$orderId}", [
                'expected' => $expectedSignature,
                'received' => $signatureKey,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Signature key tidak valid.',
            ], 400);
        }

        // 2. Idempotensi: Jika transaksi ini sudah pernah diproses lunas, return success langsung
        $existing = Pembayaran::where('kode_transaksi', $transactionId)->first();
        if ($existing) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Pembayaran Midtrans sudah pernah diproses sebelumnya (idempotent).',
                'data'    => [
                    'pembayaran_id'  => $existing->id,
                    'kode_transaksi' => $existing->kode_transaksi,
                    'status'         => $existing->status,
                ],
            ], 200);
        }

        // 3. Cari Tagihan Mahasiswa Terkait
        $tagihan = $this->resolveTagihanByOrderId($orderId);
        if (!$tagihan) {
            Log::error("Midtrans Callback: Tagihan tidak ditemukan untuk Order ID: {$orderId}");

            return response()->json([
                'status'  => 'error',
                'message' => "Tagihan mahasiswa untuk order {$orderId} tidak ditemukan dalam sistem.",
            ], 404);
        }

        // 4. Proses Status Pembayaran
        $nominal = (float) $grossAmount;
        $channelBayar = 'MIDTRANS_' . strtoupper($paymentType);

        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            try {
                DB::beginTransaction();

                // Lock row tagihan
                $tagihanLocked = TagihanMahasiswa::where('id', $tagihan->id)->lockForUpdate()->first();
                $oldTagihan = $tagihanLocked->getOriginal();

                // Resolve Unit Kas khusus Midtrans
                $unitKas = JurnalSikeuService::resolveUnitKasUntukChannel('MIDTRANS', $paymentType)
                    ?: $this->ensureMidtransUnitKas();

                // Tambah saldo kas Midtrans
                if ($unitKas && $nominal > 0) {
                    $unitKas->increment('saldo_saat_ini', $nominal);
                }

                // Catat record pembayaran
                $pembayaran = Pembayaran::create([
                    'tagihan_id'        => $tagihanLocked->id,
                    'unit_kas_id'       => $unitKas?->id,
                    'kode_transaksi'    => $transactionId,
                    'jumlah_bayar'      => $nominal,
                    'waktu_bayar'       => now(),
                    'channel_bayar'     => $channelBayar,
                    'bank_pengirim'     => strtoupper($paymentType),
                    'status'            => 'success',
                    'diverifikasi_oleh' => auth()->id() ?: null,
                    'catatan'           => "Pelunasan via Midtrans ({$paymentType}, Order: {$orderId})",
                ]);

                // Update Tagihan Mahasiswa
                $newTotalBayar = (float) $tagihanLocked->total_bayar + $nominal;
                $totalBersih = (float) ($tagihanLocked->total_tagihan + $tagihanLocked->total_denda - $tagihanLocked->total_potongan);
                $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

                $tagihanLocked->update([
                    'total_bayar' => $newTotalBayar,
                    'status'      => $newStatus,
                ]);

                // Alokasikan ke komponen rincian tagihan
                DetailTagihan::alokasikan($tagihanLocked, $nominal);

                // Update VA jika ada
                VirtualAccount::where('tagihan_id', $tagihanLocked->id)
                    ->where('status', 'aktif')
                    ->update(['status' => 'dibayar']);

                // Auto Jurnal Akuntansi: Dr Kas Midtrans / Cr Piutang Mahasiswa
                AutoJournalService::recordStudentPaymentJournal($tagihanLocked, $nominal, $unitKas);

                DB::commit();

                // Catat jejak audit log
                try {
                    AuditLogService::record(
                        module: 'SIKEU',
                        action: 'midtrans_payment',
                        tableName: 'sikeu_pembayaran',
                        recordId: $pembayaran->id,
                        oldValues: null,
                        newValues: $pembayaran->toArray(),
                        request: $request,
                    );

                    AuditLogService::record(
                        module: 'SIKEU',
                        action: 'midtrans_tagihan_update',
                        tableName: 'sikeu_tagihan_mahasiswa',
                        recordId: $tagihanLocked->id,
                        oldValues: $oldTagihan,
                        newValues: $tagihanLocked->getChanges(),
                        request: $request,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mencatat audit log Midtrans: ' . $e->getMessage());
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Pembayaran Midtrans berhasil diproses.',
                    'data'    => [
                        'pembayaran_id'  => $pembayaran->id,
                        'kode_transaksi' => $transactionId,
                        'order_id'       => $orderId,
                        'total_bayar'    => $nominal,
                        'status_tagihan' => $newStatus,
                    ],
                ], 200);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Midtrans Callback DB Error: ' . $e->getMessage(), ['exception' => $e]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Terjadi kesalahan sistem saat memproses transaksi Midtrans.',
                ], 500);
            }
        }

        // Penanganan status pending, deny, expire, cancel
        if (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            Log::info("Midtrans Transaction Cancelled/Expired for Order: {$orderId}");
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Notifikasi Midtrans status [{$transactionStatus}] berhasil diterima.",
            'data'    => [
                'order_id'           => $orderId,
                'transaction_status' => $transactionStatus,
            ],
        ], 200);
    }

    /**
     * Endpoint Simulasi & Trial Pembayaran untuk Super Admin
     * (POST /api/v1/sikeu/payment-gateway/midtrans/trial)
     */
    public function trialPayment(MidtransTrialRequest $request): JsonResponse
    {
        $serverKey = $this->resolveServerKey();
        $paymentType = (string) ($request->input('payment_type') ?? 'qris');
        $fraudStatus = (string) ($request->input('fraud_status') ?? 'accept');

        // Cari atau siapkan tagihan target
        $tagihanId = $request->input('tagihan_id');
        if ($tagihanId) {
            $tagihan = TagihanMahasiswa::find($tagihanId);
        } else {
            $tagihan = TagihanMahasiswa::where('status', '!=', 'lunas')->latest('id')->first();
        }

        if (!$tagihan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada tagihan mahasiswa aktif untuk dilakukan simulasi pembayaran.',
            ], 404);
        }

        $sisaTagihan = max(1000, (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - (float) $tagihan->total_bayar);
        $nominalSimulasi = (float) ($request->input('nominal') ?? $sisaTagihan);

        // Buat order_id dan signature simulasi yang valid
        $orderId = $tagihan->nomor_tagihan ?: ('TAG-' . $tagihan->id . '-' . time());
        $statusCode = '200';
        $grossAmountFormatted = number_format($nominalSimulasi, 2, '.', '');
        $simulatedSignature = hash('sha512', $orderId . $statusCode . $grossAmountFormatted . $serverKey);
        $transactionId = 'TRIAL-MID-' . Str::upper(Str::random(10));

        // Catat saldo kas sebelum simulasi
        $unitKas = $this->ensureMidtransUnitKas();
        $saldoSebelum = (float) $unitKas->saldo_saat_ini;

        // Eksekusi callback internal menggunakan controller
        $simulatedRequest = new MidtransCallbackRequest([
            'order_id'           => $orderId,
            'status_code'        => $statusCode,
            'gross_amount'       => $grossAmountFormatted,
            'signature_key'      => $simulatedSignature,
            'transaction_status' => 'settlement',
            'fraud_status'       => $fraudStatus,
            'payment_type'       => $paymentType,
            'transaction_id'     => $transactionId,
        ]);

        $callbackResponse = $this->handleCallback($simulatedRequest);
        $callbackData = $callbackResponse->getData(true);

        $unitKas->refresh();
        $saldoSesudah = (float) $unitKas->saldo_saat_ini;
        $tagihan->refresh();

        // Cari pembayaran yang baru saja diterbitkan
        $pembayaran = Pembayaran::where('kode_transaksi', $transactionId)->first();

        // Catat jejak audit log atas aksi trial payment oleh admin
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'midtrans_trial_payment',
                tableName: 'sikeu_pembayaran',
                recordId: $pembayaran?->id,
                oldValues: ['saldo_sebelum' => $saldoSebelum],
                newValues: [
                    'saldo_sesudah' => $saldoSesudah,
                    'nominal'       => $nominalSimulasi,
                    'order_id'      => $orderId,
                    'tagihan_id'    => $tagihan->id,
                ],
                request: $request,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log trial Midtrans: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Trial / Simulasi Pembayaran Midtrans Berhasil Dieksekusi.',
            'data'    => [
                'simulated_order_id'   => $orderId,
                'transaction_id'       => $transactionId,
                'nominal_bayar'        => $nominalSimulasi,
                'channel_bayar'        => 'MIDTRANS_' . strtoupper($paymentType),
                'saldo_kas_midtrans'   => [
                    'sebelum' => $saldoSebelum,
                    'sesudah' => $saldoSesudah,
                    'mutasi'  => '+' . number_format($nominalSimulasi, 2),
                ],
                'status_tagihan_akhir' => $tagihan->status,
                'total_terbayar'       => (float) $tagihan->total_bayar,
                'pembayaran'           => $pembayaran,
                'callback_response'    => $callbackData,
            ],
        ], 200);
    }

    /**
     * Penerbitan Token Midtrans Snap untuk Pembayaran Mahasiswa / SPMB
     * HTTP POST /api/v1/sikeu/midtrans/snap-token
     *
     * @param MidtransSnapTokenRequest $request
     * @return JsonResponse
     */
    public function createSnapToken(MidtransSnapTokenRequest $request): JsonResponse
    {
        $tagihanId = $request->integer('tagihan_id');
        $tagihan = TagihanMahasiswa::with(['mahasiswa', 'calonMahasiswa', 'detailTagihan'])->find($tagihanId);

        if (!$tagihan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tagihan mahasiswa tidak ditemukan.',
            ], 404);
        }

        if ($tagihan->status === 'lunas') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tagihan ini sudah lunas.',
            ], 422);
        }

        $totalTagihan = (float) $tagihan->total_tagihan;
        $totalBayar = (float) $tagihan->total_bayar;
        $totalPotongan = (float) $tagihan->total_potongan;
        $totalDenda = (float) $tagihan->total_denda;
        $sisaTagihan = max(0, $totalTagihan - $totalBayar - $totalPotongan + $totalDenda);

        if ($sisaTagihan <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sisa tagihan mahasiswa adalah Rp 0 atau sudah terpenuhi.',
            ], 422);
        }

        $orderId = 'TAG-' . $tagihan->id . '-' . time();

        $customerName = $request->input('first_name');
        $customerEmail = $request->input('email');
        $customerPhone = $request->input('phone');

        if (empty($customerName)) {
            $customerName = $tagihan->mahasiswa?->nama ?? $tagihan->calonMahasiswa?->nama_lengkap ?? auth()->user()?->name ?? 'Mahasiswa Kampus';
        }
        if (empty($customerEmail)) {
            $customerEmail = $tagihan->mahasiswa?->email ?? $tagihan->calonMahasiswa?->email ?? auth()->user()?->email ?? 'pembayaran@kampus.ac.id';
        }
        if (empty($customerPhone)) {
            $customerPhone = $tagihan->mahasiswa?->nomor_hp ?? $tagihan->calonMahasiswa?->no_hp ?? auth()->user()?->phone ?? '081234567890';
        }

        $itemDetails = [
            [
                'id'       => (string) $tagihan->id,
                'price'    => (int) $sisaTagihan,
                'quantity' => 1,
                'name'     => Str::limit('Pembayaran ' . ($tagihan->nomor_tagihan ?: ('Tagihan #' . $tagihan->id)), 45, ''),
            ]
        ];

        $snapPayload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $sisaTagihan,
            ],
            'customer_details' => [
                'first_name' => Str::limit($customerName, 50, ''),
                'email'      => $customerEmail,
                'phone'      => $customerPhone,
            ],
            'item_details' => $itemDetails,
        ];

        $isProd = $this->isProduction();
        $snapEndpoint = $isProd
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $serverKey = $this->resolveServerKey();
        $clientKey = $this->resolveClientKey();

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post($snapEndpoint, $snapPayload);

            if (!$response->successful()) {
                Log::error("Midtrans Snap Token Generation Failed: {$response->body()}", [
                    'order_id' => $orderId,
                    'status'   => $response->status(),
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal menerbitkan token Midtrans Snap: ' . ($response->json('error_messages.0') ?? $response->body()),
                ], $response->status() >= 400 && $response->status() < 500 ? $response->status() : 502);
            }

            $resData = $response->json();
            $snapToken = $resData['token'] ?? null;
            $redirectUrl = $resData['redirect_url'] ?? null;

            try {
                if (auth()->check()) {
                    AuditLogService::record(
                        auth()->user(),
                        'midtrans_snap_token_created',
                        'sikeu',
                        "Token Midtrans Snap diterbitkan untuk tagihan #{$tagihan->id} (Order: {$orderId})",
                        ['tagihan_id' => $tagihan->id, 'order_id' => $orderId, 'gross_amount' => (int) $sisaTagihan]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log snap token: ' . $e->getMessage(), [
                    'tagihan_id' => $tagihan->id,
                    'order_id'   => $orderId,
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Token Midtrans Snap berhasil diterbitkan.',
                'data'    => [
                    'token'        => $snapToken,
                    'redirect_url' => $redirectUrl,
                    'order_id'     => $orderId,
                    'client_key'   => $clientKey,
                    'gross_amount' => (int) $sisaTagihan,
                    'environment'  => $isProd ? 'production' : 'sandbox',
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error("Midtrans Snap Exception: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kendala saat menghubungkan ke server Midtrans Snap: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ==========================================
     * HELPER RESOLVERS
     * ==========================================
     */

    protected function resolveClientKey(): string
    {
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'midtrans')->first();
            if ($config && !empty($config->public_key_encrypted)) {
                return (string) $config->public_key_encrypted;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $clientKey = config('services.sikeu.midtrans.client_key');
        if (!empty($clientKey)) {
            return (string) $clientKey;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'test-midtrans-client-key';
        }

        return '';
    }

    protected function isProduction(): bool
    {
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'midtrans')->first();
            if ($config && !empty($config->environment)) {
                return strtolower((string) $config->environment) === 'production';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (bool) config('services.sikeu.midtrans.is_production', false);
    }

    protected function resolveServerKey(): string
    {
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'midtrans')->first();
            if ($config && !empty($config->api_key_encrypted)) {
                return (string) $config->api_key_encrypted;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $secret = config('services.sikeu.midtrans.server_key');
        if (!empty($secret)) {
            return (string) $secret;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'test-midtrans-server-key';
        }

        throw new \RuntimeException('Server Key Midtrans belum dikonfigurasi pada sistem.');
    }

    protected function resolveTagihanByOrderId(string $orderId): ?TagihanMahasiswa
    {
        // 1. Coba match langsung via nomor_tagihan
        $tagihan = TagihanMahasiswa::where('nomor_tagihan', $orderId)->first();
        if ($tagihan) {
            return $tagihan;
        }

        // 2. Coba match via kode_transaksi
        $tagihan = TagihanMahasiswa::where('kode_transaksi', $orderId)->first();
        if ($tagihan) {
            return $tagihan;
        }

        // 3. Coba ekstrak ID numerik jika format 'TAG-{id}' atau 'TAGIHAN-{id}'
        if (preg_match('/TAG(?:IHAN)?[-_]?([0-9]+)/i', $orderId, $matches)) {
            $id = (int) $matches[1];
            $tagihan = TagihanMahasiswa::find($id);
            if ($tagihan) {
                return $tagihan;
            }
        }

        // 4. Coba match via Virtual Account
        $va = VirtualAccount::where('va_number', $orderId)->with('tagihan')->first();
        if ($va && $va->tagihan) {
            return $va->tagihan;
        }

        return null;
    }

    protected function ensureMidtransUnitKas(): UnitKas
    {
        return UnitKas::firstOrCreate(
            ['kanal' => 'midtrans'],
            [
                'nama_kas'       => 'Midtrans Disbursement Kampus',
                'tipe_kas'       => 'operasional',
                'bank_name'      => 'Midtrans',
                'saldo_awal'     => 0,
                'saldo_saat_ini' => 0,
                'status'         => true,
            ]
        );
    }
}
