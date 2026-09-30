<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\PayoutReferral;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PayoutReferralObserver
{
    /**
     * Snapshot nilai sebelum persist agar jejak audit memakai data lama
     * yang presisi (diambil di hook updating, sebelum save).
     *
     * @var array<int|string, array{original: array, changes: array}>
     */
    protected static array $snapshots = [];
    public function created(PayoutReferral $payout): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $payout->getTable(),
                recordId: $payout->id,
                oldValues: null,
                newValues: $payout->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log payout #{$payout->id}: ".$e->getMessage());
        }
    }

    public function updating(PayoutReferral $payout): void
    {
        self::$snapshots[$payout->getKey() ?? spl_object_id($payout)] = [
            'original' => $payout->getOriginal(),
            'changes' => $payout->getDirty(),
        ];
    }

    public function updated(PayoutReferral $payout): void
    {
        try {
            $key = $payout->getKey() ?? spl_object_id($payout);
            $snapshot = self::$snapshots[$key] ?? null;
            unset(self::$snapshots[$key]);

            $original = $snapshot['original'] ?? $payout->getOriginal();
            $changes = $snapshot['changes'] ?? $payout->getChanges();

            if (empty($changes) && ! $payout->wasChanged()) {
                return;
            }

            $action = 'update';

            if ($payout->wasChanged('status')) {
                $action = match ($payout->status) {
                    PayoutReferral::STATUS_TERVERIFIKASI => 'approve',
                    PayoutReferral::STATUS_DITOLAK => 'reject',
                    default => 'update',
                };
            }

            AuditLogService::record(
                module: 'SPMB',
                action: $action,
                tableName: $payout->getTable(),
                recordId: $payout->id,
                oldValues: $original,
                newValues: $changes,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log update payout #{$payout->id}: ".$e->getMessage());
        }
    }

    public function deleted(PayoutReferral $payout): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $payout->getTable(),
                recordId: $payout->id,
                oldValues: $payout->toArray(),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log delete payout #{$payout->id}: ".$e->getMessage());
        }
    }
}
