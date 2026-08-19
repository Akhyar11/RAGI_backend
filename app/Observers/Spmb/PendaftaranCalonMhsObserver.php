<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PendaftaranCalonMhsObserver
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function created(PendaftaranCalonMhs $pendaftaranCalonMhs): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $pendaftaranCalonMhs->getTable(),
                recordId: $pendaftaranCalonMhs->id,
                oldValues: null,
                newValues: $pendaftaranCalonMhs->toArray()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log creation for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }

    public function updated(PendaftaranCalonMhs $pendaftaranCalonMhs): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $pendaftaranCalonMhs->getTable(),
                recordId: $pendaftaranCalonMhs->id,
                oldValues: $pendaftaranCalonMhs->getOriginal(),
                newValues: $pendaftaranCalonMhs->getChanges()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log update for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }

    public function deleted(PendaftaranCalonMhs $pendaftaranCalonMhs): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $pendaftaranCalonMhs->getTable(),
                recordId: $pendaftaranCalonMhs->id,
                oldValues: $pendaftaranCalonMhs->toArray(),
                newValues: null
            );
        } catch (\Exception $e) {
            Log::error("Failed to log deletion for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }
}
