<?php

namespace App\Observers\Simpeg;

use App\Models\Simpeg\UsulanJafung;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class UsulanJafungObserver
{
    private static array $oldValues = [];

    /**
     * Handle the UsulanJafung "created" event.
     */
    public function created(UsulanJafung $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_usulan_jafung',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UsulanJafung created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(UsulanJafung $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the UsulanJafung "updated" event.
     */
    public function updated(UsulanJafung $model): void
    {
        try {
            if ($model->wasChanged()) {
                $changes = collect($model->getChanges())
                    ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                    ->toArray();

                $action = 'update';
                if ($model->wasChanged('status_usulan')) {
                    $action = match ($model->status_usulan) {
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
                    tableName: 'simpeg_usulan_jafung',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UsulanJafung updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the UsulanJafung "deleted" event.
     */
    public function deleted(UsulanJafung $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_usulan_jafung',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on UsulanJafung deleted: ' . $e->getMessage());
        }
    }
}
