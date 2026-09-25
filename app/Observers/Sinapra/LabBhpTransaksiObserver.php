<?php

namespace App\Observers\Sinapra;

use App\Models\LabBhpTransaksi;
use App\Services\AuditLogService;

class LabBhpTransaksiObserver
{
    public function created(LabBhpTransaksi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'sinapra_lab_bhp_transaksi',
                recordId: $model->id,
                newValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(LabBhpTransaksi $model): void
    {
        if ($model->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'sinapra_lab_bhp_transaksi',
                    recordId: $model->id,
                    oldValues: array_intersect_key($model->getOriginal(), $model->getChanges()),
                    newValues: $model->getChanges(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function deleted(LabBhpTransaksi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'sinapra_lab_bhp_transaksi',
                recordId: $model->id,
                oldValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
