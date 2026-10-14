<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\PaketMkRombel;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PaketMkRombelObserver
{
    public function created(PaketMkRombel $paket): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $paket->id,
                newValues: $paket->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_paket_mk_rombel: ' . $e->getMessage());
        }
    }

    public function updated(PaketMkRombel $paket): void
    {
        if (!$paket->wasChanged()) {
            return;
        }
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $paket->id,
                oldValues: $paket->getOriginal(),
                newValues: $paket->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_paket_mk_rombel: ' . $e->getMessage());
        }
    }

    public function deleted(PaketMkRombel $paket): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $paket->id,
                oldValues: $paket->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_paket_mk_rombel: ' . $e->getMessage());
        }
    }
}
