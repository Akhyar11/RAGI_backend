<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\UangMukaKerja;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class UmkObserver
{
    private static array $oldValues = [];

    public function created(UangMukaKerja $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_umk',
                recordId: $model->id,
                oldValues: null,
                newValues: collect($model->toArray())->except(['password', 'token'])->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on Umk created: ' . $e->getMessage());
        }
    }

    public function updating(UangMukaKerja $model): void
    {
        self::$oldValues[spl_object_id($model)] = $model->getOriginal();
    }

    public function updated(UangMukaKerja $model): void
    {
        try {
            if ($model->wasChanged()) {
                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: 'sikeu_umk',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: collect($model->getChanges())->except(['password', 'token'])->toArray(),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on Umk updated: ' . $e->getMessage());
        }
    }

    public function deleted(UangMukaKerja $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_umk',
                recordId: $model->id,
                oldValues: $model->toArray(),
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on Umk deleted: ' . $e->getMessage());
        }
    }
}
