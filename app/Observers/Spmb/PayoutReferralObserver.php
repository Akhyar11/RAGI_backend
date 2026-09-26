<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\PayoutReferral;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PayoutReferralObserver
{
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

    public function updated(PayoutReferral $payout): void
    {
        try {
            if (! $payout->wasChanged()) {
                return;
            }

            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $payout->getTable(),
                recordId: $payout->id,
                oldValues: $payout->getOriginal(),
                newValues: $payout->getChanges(),
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
