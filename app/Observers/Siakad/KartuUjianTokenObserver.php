<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\KartuUjianToken;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class KartuUjianTokenObserver
{
    public function created(KartuUjianToken $token): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_kartu_ujian_tokens',
                recordId: $token->id,
                newValues: ['mahasiswa_id' => $token->mahasiswa_id, 'tipe_ujian' => $token->tipe_ujian]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_kartu_ujian_tokens: ' . $e->getMessage());
        }
    }

    public function updated(KartuUjianToken $token): void
    {
        if (!$token->wasChanged()) {
            return;
        }
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_kartu_ujian_tokens',
                recordId: $token->id,
                oldValues: $token->getOriginal(),
                newValues: $token->getChanges()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_kartu_ujian_tokens: ' . $e->getMessage());
        }
    }

    public function deleted(KartuUjianToken $token): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_kartu_ujian_tokens',
                recordId: $token->id,
                oldValues: ['mahasiswa_id' => $token->mahasiswa_id, 'tipe_ujian' => $token->tipe_ujian]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log siakad_kartu_ujian_tokens: ' . $e->getMessage());
        }
    }
}
