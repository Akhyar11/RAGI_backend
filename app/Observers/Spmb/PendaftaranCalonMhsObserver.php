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
            $this->auditLogService->log(
                'SPMB',
                'create',
                $pendaftaranCalonMhs->getTable(),
                $pendaftaranCalonMhs->id,
                null,
                $pendaftaranCalonMhs->toArray()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log creation for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }

    public function updated(PendaftaranCalonMhs $pendaftaranCalonMhs): void
    {
        try {
            $this->auditLogService->log(
                'SPMB',
                'update',
                $pendaftaranCalonMhs->getTable(),
                $pendaftaranCalonMhs->id,
                $pendaftaranCalonMhs->getOriginal(),
                $pendaftaranCalonMhs->getChanges()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log update for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }

    public function deleted(PendaftaranCalonMhs $pendaftaranCalonMhs): void
    {
        try {
            $this->auditLogService->log(
                'SPMB',
                'delete',
                $pendaftaranCalonMhs->getTable(),
                $pendaftaranCalonMhs->id,
                $pendaftaranCalonMhs->toArray(),
                null
            );
        } catch (\Exception $e) {
            Log::error("Failed to log deletion for PendaftaranCalonMhs ID {$pendaftaranCalonMhs->id}: " . $e->getMessage());
        }
    }
}
