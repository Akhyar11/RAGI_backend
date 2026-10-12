<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\KoreksiAlokasi;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class KoreksiAlokasiObserver
{
    private static array $oldValues = [];

    public function created(KoreksiAlokasi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_koreksi_alokasi',
                recordId: $model->id,
                oldValues: null,
                newValues: collect($model->toArray())->except(['password', 'token'])->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on KoreksiAlokasi created: ' . $e->getMessage());
        }
    }

    public function updating(KoreksiAlokasi $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->toArray();
    }

    public function updated(KoreksiAlokasi $model): void
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
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $model->id,
                    oldValues: $old,
                    newValues: collect($model->getChanges())->toArray(),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on KoreksiAlokasi updated: ' . $e->getMessage());
        }
    }

    public function deleted(KoreksiAlokasi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_koreksi_alokasi',
                recordId: $model->id,
                oldValues: ['nomor_koreksi' => $model->nomor_koreksi, 'status' => $model->status],
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on KoreksiAlokasi deleted: ' . $e->getMessage());
        }
    }
}
