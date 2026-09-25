<?php

namespace App\Observers\Sinapra;

use App\Models\LaboranRuangan;
use App\Services\AuditLogService;

class LaboranRuanganObserver
{
    public function created(LaboranRuangan $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'sinapra_laboran_ruangan',
                recordId: $model->id,
                newValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(LaboranRuangan $model): void
    {
        if ($model->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'sinapra_laboran_ruangan',
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

    public function deleted(LaboranRuangan $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'sinapra_laboran_ruangan',
                recordId: $model->id,
                oldValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
