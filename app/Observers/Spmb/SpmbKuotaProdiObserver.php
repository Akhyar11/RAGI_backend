<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\SpmbKuotaProdi;
use App\Services\AuditLogService;

class SpmbKuotaProdiObserver
{
    private function sanitize(array $attributes): array
    {
        return collect($attributes)->except(['password', 'remember_token', 'token'])->toArray();
    }

    public function created(SpmbKuotaProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: null,
                newValues: $this->sanitize($model->getAttributes()),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(SpmbKuotaProdi $model): void
    {
        if ($model->wasChanged()) {
            try {
                AuditLogService::record(
                    module: 'SPMB',
                    action: 'update',
                    tableName: $model->getTable(),
                    recordId: $model->id,
                    oldValues: $this->sanitize(array_intersect_key($model->getOriginal(), $model->getChanges())),
                    newValues: $this->sanitize($model->getChanges()),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function deleted(SpmbKuotaProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: $this->sanitize($model->getOriginal()),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
