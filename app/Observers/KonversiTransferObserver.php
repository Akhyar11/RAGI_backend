<?php

namespace App\Observers;

use App\Models\Siakad\KonversiTransfer;
use App\Services\AuditLogService;

class KonversiTransferObserver
{
    /**
     * Handle the KonversiTransfer "created" event.
     */
    public function created(KonversiTransfer $konversi): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'create',
            tableName: 'siakad_konversi_transfer',
            recordId: $konversi->id,
            newValues: $konversi->toArray()
        );
    }

    /**
     * Handle the KonversiTransfer "updated" event.
     */
    public function updated(KonversiTransfer $konversi): void
    {
        if ($konversi->wasChanged()) {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_konversi_transfer',
                recordId: $konversi->id,
                oldValues: $konversi->getOriginal(),
                newValues: $konversi->getChanges()
            );
        }
    }

    /**
     * Handle the KonversiTransfer "deleted" event.
     */
    public function deleted(KonversiTransfer $konversi): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'delete',
            tableName: 'siakad_konversi_transfer',
            recordId: $konversi->id,
            oldValues: $konversi->toArray()
        );
    }

    /**
     * Handle the KonversiTransfer "restored" event.
     */
    public function restored(KonversiTransfer $konversi): void
    {
        AuditLogService::record(
            module: 'SIAKAD',
            action: 'restore',
            tableName: 'siakad_konversi_transfer',
            recordId: $konversi->id
        );
    }
}
