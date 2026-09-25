<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\ReferralUsage;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class ReferralUsageObserver
{
    public function created(ReferralUsage $referralUsage): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $referralUsage->getTable(),
                recordId: $referralUsage->id,
                oldValues: null,
                newValues: $referralUsage->toArray()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log creation for ReferralUsage ID {$referralUsage->id}: ".$e->getMessage());
        }
    }

    public function updated(ReferralUsage $referralUsage): void
    {
        try {
            if (! $referralUsage->wasChanged()) {
                return;
            }

            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $referralUsage->getTable(),
                recordId: $referralUsage->id,
                oldValues: $referralUsage->getOriginal(),
                newValues: $referralUsage->getChanges()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log update for ReferralUsage ID {$referralUsage->id}: ".$e->getMessage());
        }
    }

    public function deleted(ReferralUsage $referralUsage): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $referralUsage->getTable(),
                recordId: $referralUsage->id,
                oldValues: $referralUsage->toArray(),
                newValues: null
            );
        } catch (\Exception $e) {
            Log::error("Failed to log deletion for ReferralUsage ID {$referralUsage->id}: ".$e->getMessage());
        }
    }
}
