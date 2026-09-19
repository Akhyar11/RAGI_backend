<?php

namespace App\Observers\Simpeg;

use App\Models\Simpeg\SuratTugas;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class SuratTugasObserver
{
    private static array $oldValues = [];

    /**
     * Handle the SuratTugas "created" event.
     */
    public function created(SuratTugas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_surat_tugas',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on SuratTugas created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(SuratTugas $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the SuratTugas "updated" event.
     */
    public function updated(SuratTugas $model): void
    {
        try {
            if ($model->wasChanged()) {
                $changes = collect($model->getChanges())
                    ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                    ->toArray();

                $action = 'update';
                if ($model->wasChanged('status')) {
                    $action = match ($model->status) {
                        'disetujui' => 'approve',
                        'ditolak' => 'reject',
                        default => 'update',
                    };
                }

                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIMPEG',
                    action: $action,
                    tableName: 'simpeg_surat_tugas',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on SuratTugas updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the SuratTugas "deleted" event.
     */
    public function deleted(SuratTugas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_surat_tugas',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on SuratTugas deleted: ' . $e->getMessage());
        }
    }
}
