<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\UnitKas;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class UnitKasObserver
{
    private static array $oldValues = [];

    /**
     * Handle the UnitKas "created" event.
     */
    public function created(UnitKas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_unit_kas',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UnitKas created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(UnitKas $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the UnitKas "updated" event.
     */
    public function updated(UnitKas $model): void
    {
        try {
            if ($model->wasChanged()) {
                $changes = collect($model->getChanges())
                    ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                    ->toArray();

                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'update',
                    tableName: 'sikeu_unit_kas',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UnitKas updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the UnitKas "deleted" event.
     */
    public function deleted(UnitKas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_unit_kas',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UnitKas deleted: ' . $e->getMessage());
        }
    }
}
