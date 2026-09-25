<?php

namespace App\Observers\Sinapra;

use App\Models\AlatKalibrasi;
use App\Services\AuditLogService;

class AlatKalibrasiObserver
{
    public function created(AlatKalibrasi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'sinapra_alat_kalibrasi',
                recordId: $model->id,
                newValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(AlatKalibrasi $model): void
    {
        if ($model->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'sinapra_alat_kalibrasi',
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

    public function deleted(AlatKalibrasi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'sinapra_alat_kalibrasi',
                recordId: $model->id,
                oldValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
