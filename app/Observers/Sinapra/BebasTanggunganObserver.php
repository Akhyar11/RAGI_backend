<?php

namespace App\Observers\Sinapra;

use App\Models\BebasTanggungan;
use App\Services\AuditLogService;

class BebasTanggunganObserver
{
    public function created(BebasTanggungan $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'sinapra_bebas_tanggungan',
                recordId: $model->id,
                newValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(BebasTanggungan $model): void
    {
        if ($model->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'sinapra_bebas_tanggungan',
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

    public function deleted(BebasTanggungan $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'sinapra_bebas_tanggungan',
                recordId: $model->id,
                oldValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
