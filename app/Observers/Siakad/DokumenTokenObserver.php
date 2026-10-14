<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\DokumenToken;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class DokumenTokenObserver
{
    public function created(DokumenToken $dokumen): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_dokumen_tokens',
                recordId: $dokumen->id,
                newValues: ['jenis' => $dokumen->jenis, 'mahasiswa_id' => $dokumen->mahasiswa_id, 'nomor_dokumen' => $dokumen->nomor_dokumen]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_dokumen_tokens: ' . $e->getMessage());
        }
    }

    public function updated(DokumenToken $dokumen): void
    {
        if (!$dokumen->wasChanged()) {
            return;
        }
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_dokumen_tokens',
                recordId: $dokumen->id,
                oldValues: $dokumen->getOriginal(),
                newValues: $dokumen->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_dokumen_tokens: ' . $e->getMessage());
        }
    }

    public function deleted(DokumenToken $dokumen): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_dokumen_tokens',
                recordId: $dokumen->id,
                oldValues: ['jenis' => $dokumen->jenis, 'mahasiswa_id' => $dokumen->mahasiswa_id]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_dokumen_tokens: ' . $e->getMessage());
        }
    }
}
