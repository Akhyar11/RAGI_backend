<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\PerubahanProdi;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PerubahanProdiObserver
{
    private static array $oldValues = [];

    public function created(PerubahanProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_mahasiswa_perubahan_prodi',
                recordId: $model->id,
                oldValues: null,
                newValues: collect($model->toArray())->except(['password', 'token'])->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PerubahanProdi created: ' . $e->getMessage());
        }
    }

    public function updating(PerubahanProdi $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())->only($dirty)->toArray();
    }

    public function updated(PerubahanProdi $model): void
    {
        try {
            if ($model->wasChanged()) {
                $action = 'update';
                if ($model->wasChanged('status')) {
                    $action = match ($model->status) {
                        'approved', 'applied' => 'approve',
                        'rejected', 'cancelled' => 'reject',
                        default => 'update',
                    };
                }

                $old = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIKEU',
                    action: $action,
                    tableName: 'sikeu_mahasiswa_perubahan_prodi',
                    recordId: $model->id,
                    oldValues: $old,
                    newValues: collect($model->getChanges())->toArray(),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PerubahanProdi updated: ' . $e->getMessage());
        }
    }

    public function deleted(PerubahanProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_mahasiswa_perubahan_prodi',
                recordId: $model->id,
                oldValues: ['mahasiswa_id' => $model->mahasiswa_id, 'status' => $model->status],
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PerubahanProdi deleted: ' . $e->getMessage());
        }
    }
}
