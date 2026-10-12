<?php

namespace App\Observers\Siakad;

use App\Models\Siakad\PindahProdi;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PindahProdiObserver
{
    private static array $oldValues = [];

    public function created(PindahProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_mahasiswa_pindah_prodi',
                recordId: $model->id,
                oldValues: null,
                newValues: collect($model->toArray())->except(['password', 'token'])->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PindahProdi created: ' . $e->getMessage());
        }
    }

    public function updating(PindahProdi $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())->only($dirty)->toArray();
    }

    public function updated(PindahProdi $model): void
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
                    module: 'SIAKAD',
                    action: $action,
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $model->id,
                    oldValues: $old,
                    newValues: collect($model->getChanges())->toArray(),
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PindahProdi updated: ' . $e->getMessage());
        }
    }

    public function deleted(PindahProdi $model): void
    {
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_mahasiswa_pindah_prodi',
                recordId: $model->id,
                oldValues: ['mahasiswa_id' => $model->mahasiswa_id, 'status' => $model->status],
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Audit log failed on PindahProdi deleted: ' . $e->getMessage());
        }
    }
}
