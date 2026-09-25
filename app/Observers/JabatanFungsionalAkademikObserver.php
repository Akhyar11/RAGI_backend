<?php

namespace App\Observers;

use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class JabatanFungsionalAkademikObserver
{
    public function created(JabatanFungsionalAkademik $m): void
    {
        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_jabatan_fungsional_akademik',
                recordId: $m->id,
                oldValues: [],
                newValues: $m->toArray(),
                request: request()
            );
        } catch (\Exception $e) {
            Log::error('Audit log gagal: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    public function updated(JabatanFungsionalAkademik $m): void
    {
        if ($m->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SIMPEG',
                    action: 'update',
                    tableName: 'simpeg_jabatan_fungsional_akademik',
                    recordId: $m->id,
                    oldValues: $m->getOriginal(),
                    newValues: $m->getChanges(),
                    request: request()
                );
            } catch (\Exception $e) {
                Log::error('Audit log gagal: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    public function deleted(JabatanFungsionalAkademik $m): void
    {
        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_jabatan_fungsional_akademik',
                recordId: $m->id,
                oldValues: $m->getOriginal(),
                newValues: [],
                request: request()
            );
        } catch (\Exception $e) {
            Log::error('Audit log gagal: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
