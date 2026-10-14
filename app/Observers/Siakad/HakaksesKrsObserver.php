<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\HakaksesKrs;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class HakaksesKrsObserver
{
    public function created(HakaksesKrs $akses): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_hakakses_krs',
                recordId: $akses->id,
                newValues: $akses->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_hakakses_krs: ' . $e->getMessage());
        }
    }

    public function updated(HakaksesKrs $akses): void
    {
        if (!$akses->wasChanged()) {
            return;
        }
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_hakakses_krs',
                recordId: $akses->id,
                oldValues: $akses->getOriginal(),
                newValues: $akses->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_hakakses_krs: ' . $e->getMessage());
        }
    }

    public function deleted(HakaksesKrs $akses): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_hakakses_krs',
                recordId: $akses->id,
                oldValues: $akses->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_hakakses_krs: ' . $e->getMessage());
        }
    }
}
