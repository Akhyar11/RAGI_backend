<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\PengeluaranKampus;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PengeluaranKampusObserver
{
    private static array $oldValues = [];

    /**
     * Handle the PengeluaranKampus "created" event.
     */
    public function created(PengeluaranKampus $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_pengeluaran_kampus',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengeluaranKampus created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(PengeluaranKampus $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the PengeluaranKampus "updated" event.
     */
    public function updated(PengeluaranKampus $model): void
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
                    tableName: 'sikeu_pengeluaran_kampus',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengeluaranKampus updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the PengeluaranKampus "deleted" event.
     */
    public function deleted(PengeluaranKampus $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_pengeluaran_kampus',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengeluaranKampus deleted: ' . $e->getMessage());
        }
    }
}
