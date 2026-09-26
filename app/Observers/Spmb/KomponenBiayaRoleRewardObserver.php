<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\KomponenBiayaRoleReward;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class KomponenBiayaRoleRewardObserver
{
    public function created(KomponenBiayaRoleReward $reward): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $reward->getTable(),
                recordId: $reward->id,
                oldValues: null,
                newValues: $reward->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log reward role #{$reward->id}: ".$e->getMessage());
        }
    }

    public function updated(KomponenBiayaRoleReward $reward): void
    {
        try {
            if (! $reward->wasChanged()) {
                return;
            }

            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $reward->getTable(),
                recordId: $reward->id,
                oldValues: $reward->getOriginal(),
                newValues: $reward->getChanges(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log update reward role #{$reward->id}: ".$e->getMessage());
        }
    }

    public function deleted(KomponenBiayaRoleReward $reward): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $reward->getTable(),
                recordId: $reward->id,
                oldValues: $reward->toArray(),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat audit log delete reward role #{$reward->id}: ".$e->getMessage());
        }
    }
}
