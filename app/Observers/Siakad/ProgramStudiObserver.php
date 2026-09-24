<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\ProgramStudi;
use App\Services\AuditLogService;
use Illuminate\Support\Arr;

class ProgramStudiObserver
{
    private const HIDDEN = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'token', 'access_token', 'refresh_token', 'secret', 'sso_token',
    ];

    public function created(ProgramStudi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
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

    public function updated(ProgramStudi $model): void
    {
        try {
            if ($model->wasChanged()) {
                AuditLogService::record(
                    module: 'SIAKAD',
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

    public function deleted(ProgramStudi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
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

    public function restored(ProgramStudi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
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
