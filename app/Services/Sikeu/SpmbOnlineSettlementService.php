<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\PaymentCheckout;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SpmbOnlineSettlementService
{
    public function midtrans(array $payload): array
    {
        $attempt = PaymentCheckout::where('order_id', $payload['order_id'] ?? '')->firstOrFail();
        abort_unless($attempt->gateway_name === PaymentGatewayConfig::MIDTRANS, 400, 'Provider pembayaran tidak sesuai.');
        $key = $attempt->gateway?->api_key_encrypted;
        $signature = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$key);
        abort_unless($key && hash_equals($signature, (string) ($payload['signature_key'] ?? '')), 400, 'Signature key tidak valid.');
        $status = $payload['transaction_status'] ?? '';
        $paid = $status === 'settlement' || ($status === 'capture' && ($payload['fraud_status'] ?? '') === 'accept');

        return $this->apply($attempt, $paid, $payload['gross_amount'] ?? 0, 'MIDTRANS_'.strtoupper($payload['payment_type'] ?? 'ONLINE'), $status);
    }

    public function xendit(array $payload, string $token): array
    {
        $attempt = PaymentCheckout::where('order_id', $payload['external_id'] ?? '')->firstOrFail();
        abort_unless($attempt->gateway_name === PaymentGatewayConfig::XENDIT, 400, 'Provider pembayaran tidak sesuai.');
        $expected = $attempt->gateway?->webhook_token_encrypted;
        abort_unless($expected && hash_equals($expected, $token), 401, 'Token callback tidak valid.');
        abort_unless(($payload['id'] ?? null) === $attempt->provider_id, 400, 'Identitas invoice tidak sesuai.');
        abort_unless(($payload['currency'] ?? '') === 'IDR', 400, 'Mata uang pembayaran tidak sesuai.');
        $paid = ($payload['status'] ?? '') === 'PAID';

        return $this->apply($attempt, $paid, $paid ? ($payload['paid_amount'] ?? $payload['amount'] ?? 0) : ($payload['amount'] ?? 0),
            'XENDIT_'.strtoupper($payload['payment_method'] ?? 'ONLINE'), strtolower($payload['status'] ?? 'pending'));
    }

    private function apply(PaymentCheckout $attempt, bool $paid, mixed $amount, string $channel, string $status): array
    {
        if (! is_numeric($amount) || (int) round((float) $amount * 100) !== (int) round((float) $attempt->amount * 100)) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran tidak sesuai dengan checkout yang diterbitkan.']);
        }

        return DB::transaction(function () use ($attempt, $paid, $channel, $status): array {
            $bill = $attempt->tagihan;
            PendaftaranCalonMhs::whereKey($bill->calon_mahasiswa_id)->lockForUpdate()->firstOrFail();
            $bill = TagihanMahasiswa::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $attempt = PaymentCheckout::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $transactionCode = 'SPMB-CHECKOUT-'.$attempt->id;
            if ($attempt->status === 'paid' || Pembayaran::where('kode_transaksi', $transactionCode)->exists()) {
                return ['processed' => false, 'status' => 'paid'];
            }
            if (! $paid) {
                if (in_array($status, ['expire', 'expired', 'cancel', 'deny', 'failed'], true)) {
                    $attempt->update(['status' => 'expired']);
                }

                return ['processed' => false, 'status' => $status];
            }
            $remaining = (int) round(((float) $bill->total_tagihan + (float) $bill->total_denda - (float) $bill->total_potongan - (float) $bill->total_bayar) * 100);
            if ($bill->status === 'lunas' || $remaining !== (int) round((float) $attempt->amount * 100)) {
                throw ValidationException::withMessages(['tagihan' => 'Tagihan telah berubah atau sudah dibayar melalui transaksi lain; pembayaran memerlukan rekonsiliasi.']);
            }
            $unitKas = JurnalSikeuService::resolveUnitKasUntukChannel($channel, null);
            $amount = (float) $attempt->amount;
            $oldBill = $bill->getOriginal();
            $payment = Pembayaran::create([
                'tagihan_id' => $bill->id, 'unit_kas_id' => $unitKas?->id, 'kode_transaksi' => $transactionCode,
                'jumlah_bayar' => $amount, 'waktu_bayar' => now(), 'channel_bayar' => $channel, 'status' => 'success',
                'catatan' => 'Pembayaran checkout '.$attempt->order_id,
            ]);
            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $amount);
            }
            $bill->update(['total_bayar' => (float) $bill->total_bayar + $amount, 'status' => 'lunas']);
            DetailTagihan::alokasikan($bill, $amount);
            VirtualAccount::where('tagihan_id', $bill->id)->where('status', 'aktif')->update(['status' => 'dibayar']);
            $journal = AutoJournalService::recordStudentPaymentJournal($bill, $amount, $unitKas);
            if (! $journal) {
                throw new \RuntimeException('Jurnal pembayaran belum dapat dibukukan. Periksa pemetaan COA dan unit kas kanal pembayaran.');
            }
            $attempt->update(['status' => 'paid']);
            try {
                AuditLogService::record(module: 'SIKEU', action: 'create', tableName: $payment->getTable(), recordId: $payment->id,
                    newValues: $payment->getAttributes());
                AuditLogService::record(module: 'SIKEU', action: 'update', tableName: $bill->getTable(), recordId: $bill->id,
                    oldValues: $oldBill, newValues: $bill->getChanges());
            } catch (\Throwable $exception) {
                Log::warning('Gagal mencatat audit pelunasan checkout SPMB: '.$exception->getMessage());
            }

            return ['processed' => true, 'status' => 'paid', 'pembayaran_id' => $payment->id];
        });
    }
}
