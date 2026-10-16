<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\TutupKasUnit;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class TutupKasUnitObserver
{
    private static array $oldValues = [];

    public function created(TutupKasUnit $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_tutup_kas',
                recordId: $model->id,
                oldValues: null,
                newValues: $model->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on TutupKas created: ' . $e->getMessage());
        }
    }

    public function updating(TutupKasUnit $model): void
    {
        self::$oldValues[spl_object_id($model)] = $model->getOriginal();
    }

    public function updated(TutupKasUnit $model): void
    {
        try {
            if ($model->wasChanged()) {
                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: 'sikeu_tutup_kas',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $model->getChanges(),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on TutupKas updated: ' . $e->getMessage());
        }
    }

    public function deleted(TutupKasUnit $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_tutup_kas',
                recordId: $model->id,
                oldValues: $model->toArray(),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on TutupKas deleted: ' . $e->getMessage());
        }
    }
}
