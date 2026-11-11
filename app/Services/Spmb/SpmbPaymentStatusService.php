<?php

namespace App\Services\Spmb;

use App\Events\Spmb\MahasiswaDiterima;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PembayaranSpmb;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpmbPaymentStatusService
{
    public function sync(int $applicationId, bool $reenrollment): void
    {
        DB::transaction(function () use ($applicationId, $reenrollment): void {
            $application = PendaftaranCalonMhs::query()->lockForUpdate()->find($applicationId);
            if (! $application) {
                return;
            }
            $bills = TagihanMahasiswa::spmbStage($applicationId, $reenrollment)->get();
            if ($bills->isEmpty()) {
                return;
            }
            $paid = $bills->every(fn ($bill) => $bill->status === 'lunas');
            if ($reenrollment) {
                if (! $paid) {
                    return;
                }
                $result = HasilSeleksi::query()->where('pendaftaran_id', $applicationId)->lockForUpdate()->first();
                if ($result && $result->status === HasilSeleksi::STATUS_LULUS && $result->status_daftar_ulang !== HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS) {
                    $oldResult = $result->getOriginal();
                    $result->update(['status_daftar_ulang' => HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS]);
                    $this->recordAudit($result->getTable(), (int) $result->id, $oldResult, $result->getChanges());
                    DB::afterCommit(fn () => event(new MahasiswaDiterima($application)));
                }

                return;
            }
            $wasPaid = $application->status_pembayaran === 'lunas';
            $status = $paid ? 'lunas' : ($bills->sum('total_bayar') > 0 ? 'sebagian' : 'belum_bayar');
            $oldApplication = $application->getOriginal();
            $application->update([
                'status_pembayaran' => $status,
                'status' => $paid && $application->status === PendaftaranCalonMhs::STATUS_DRAFT ? PendaftaranCalonMhs::STATUS_SUBMITTED : $application->status,
            ]);
            $this->recordAudit($application->getTable(), (int) $application->id, $oldApplication, $application->getChanges());
            if ($paid && ! $wasPaid) {
                $paidAmount = $bills->sum('total_bayar');
                $paidAt = now();
                $payments = PembayaranSpmb::query()->where('pendaftaran_id', $applicationId)->get();
                PembayaranSpmb::where('pendaftaran_id', $applicationId)->update(['status' => 'paid', 'jumlah_bayar' => $paidAmount, 'paid_at' => $paidAt]);
                $paymentChanges = ['status' => 'paid', 'jumlah_bayar' => $paidAmount, 'paid_at' => $paidAt];
                foreach ($payments as $payment) {
                    $this->recordAudit($payment->getTable(), (int) $payment->id, $payment->getOriginal(), $paymentChanges);
                }
                app(SpmbPendaftaranService::class)->generateProgressAlur($application);
            }
        });
    }

    /**
     * Catat mutasi status pembayaran tanpa menggagalkan proses utama.
     */
    private function recordAudit(string $tableName, int $recordId, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $tableName,
                recordId: $recordId,
                oldValues: $oldValues,
                newValues: $newValues,
                request: request(),
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit status pembayaran SPMB: '.$e->getMessage());
        }
    }
}
