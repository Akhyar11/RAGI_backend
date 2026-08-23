<?php

namespace App\Observers;

use App\Models\Siakad\Mahasiswa;
use App\Services\AuditLogService;

class MahasiswaObserver
{
    /**
     * Handle the Mahasiswa "created" event.
     */
    public function created(Mahasiswa $mahasiswa): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'create',
            tableName: 'siakad_mahasiswa',
            recordId: $mahasiswa->id,
            newValues: $mahasiswa->toArray()
        );
    }

    /**
     * Handle the Mahasiswa "updated" event.
     */
    public function updated(Mahasiswa $mahasiswa): void
    {
        if ($mahasiswa->wasChanged()) {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_mahasiswa',
                recordId: $mahasiswa->id,
                oldValues: $mahasiswa->getOriginal(),
                newValues: $mahasiswa->getChanges()
            );
        }
    }

    /**
     * Handle the Mahasiswa "deleted" event.
     */
    public function deleted(Mahasiswa $mahasiswa): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'delete',
            tableName: 'siakad_mahasiswa',
            recordId: $mahasiswa->id,
            oldValues: $mahasiswa->toArray()
        );
    }

    /**
     * Handle the Mahasiswa "restored" event.
     */
    public function restored(Mahasiswa $mahasiswa): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'restore',
            tableName: 'siakad_mahasiswa',
            recordId: $mahasiswa->id
        );
    }
}
