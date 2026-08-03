<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\GelombangPenerimaan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class GelombangPenerimaanObserver
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function created(GelombangPenerimaan $gelombangPenerimaan): void
    {
        try {
            $this->auditLogService->log(
                'SPMB',
                'create',
                $gelombangPenerimaan->getTable(),
                $gelombangPenerimaan->id,
                null,
                $gelombangPenerimaan->toArray()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log creation for GelombangPenerimaan ID {$gelombangPenerimaan->id}: " . $e->getMessage());
        }
    }

    public function updated(GelombangPenerimaan $gelombangPenerimaan): void
    {
        try {
            $this->auditLogService->log(
                'SPMB',
                'update',
                $gelombangPenerimaan->getTable(),
                $gelombangPenerimaan->id,
                $gelombangPenerimaan->getOriginal(),
                $gelombangPenerimaan->getChanges()
            );
        } catch (\Exception $e) {
            Log::error("Failed to log update for GelombangPenerimaan ID {$gelombangPenerimaan->id}: " . $e->getMessage());
        }
    }

    public function deleted(GelombangPenerimaan $gelombangPenerimaan): void
    {
        try {
            $this->auditLogService->log(
                'SPMB',
                'delete',
                $gelombangPenerimaan->getTable(),
                $gelombangPenerimaan->id,
                $gelombangPenerimaan->toArray(),
                null
            );
        } catch (\Exception $e) {
            Log::error("Failed to log deletion for GelombangPenerimaan ID {$gelombangPenerimaan->id}: " . $e->getMessage());
        }
    }
}
