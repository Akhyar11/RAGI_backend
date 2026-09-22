<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\MasterBiayaItem;
use App\Services\AuditLogService;
use Illuminate\Support\Arr;

class MasterBiayaItemObserver
{
    private const HIDDEN = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'token', 'access_token', 'refresh_token', 'secret', 'sso_token',
    ];

    public function created(MasterBiayaItem $model): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: null,
                newValues: Arr::except($model->toArray(), self::HIDDEN),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updated(MasterBiayaItem $model): void
    {
        try {
            if ($model->wasChanged()) {
                AuditLogService::record(
                    module: 'SPMB',
                    action: 'update',
                    tableName: $model->getTable(),
                    recordId: $model->id,
                    oldValues: Arr::except($model->getOriginal(), self::HIDDEN),
                    newValues: Arr::except($model->getChanges(), self::HIDDEN),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function deleted(MasterBiayaItem $model): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: Arr::except($model->toArray(), self::HIDDEN),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function restored(MasterBiayaItem $model): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'restore',
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: null,
                newValues: Arr::except($model->toArray(), self::HIDDEN),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
