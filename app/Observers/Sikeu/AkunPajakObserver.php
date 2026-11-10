<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\AkunPajak;
use App\Services\AuditLogService;

class AkunPajakObserver
{
    public function created(AkunPajak $akunPajak): void
    {
        AuditLogService::record(
            module: 'SIKEU',
            action: 'create',
            tableName: 'sikeu_akun_pajak',
            recordId: $akunPajak->id,
            newValues: $akunPajak->toArray()
        );
    }

    public function updated(AkunPajak $akunPajak): void
    {
        if ($akunPajak->wasChanged()) {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'update',
                tableName: 'sikeu_akun_pajak',
                recordId: $akunPajak->id,
                newValues: $akunPajak->getChanges()
            );
        }
    }
}
