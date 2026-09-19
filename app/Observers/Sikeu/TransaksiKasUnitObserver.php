<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\TransaksiKasUnit;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class TransaksiKasUnitObserver
{
    private static array $oldValues = [];

    /**
     * Handle the TransaksiKasUnit "created" event.
     */
    public function created(TransaksiKasUnit $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_transaksi_kas_unit',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on TransaksiKasUnit created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(TransaksiKasUnit $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the TransaksiKasUnit "updated" event.
     */
    public function updated(TransaksiKasUnit $model): void
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
                    tableName: 'sikeu_transaksi_kas_unit',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on TransaksiKasUnit updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the TransaksiKasUnit "deleted" event.
     */
    public function deleted(TransaksiKasUnit $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_transaksi_kas_unit',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on TransaksiKasUnit deleted: ' . $e->getMessage());
        }
    }
}
