<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\PengajuanPencairanKas;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PengajuanPencairanKasObserver
{
    private static array $oldValues = [];

    /**
     * Handle the PengajuanPencairanKas "created" event.
     */
    public function created(PengajuanPencairanKas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_pengajuan_pencairan_kas',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengajuanPencairanKas created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(PengajuanPencairanKas $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the PengajuanPencairanKas "updated" event.
     */
    public function updated(PengajuanPencairanKas $model): void
    {
        try {
            if ($model->wasChanged()) {
                $changes = collect($model->getChanges())
                    ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                    ->toArray();

                $action = 'update';
                if ($model->wasChanged('status')) {
                    $action = match ($model->status) {
                        'dicairkan', 'disetujui' => 'approve',
                        'ditolak' => 'reject',
                        default => 'update',
                    };
                }

                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIKEU',
                    action: $action,
                    tableName: 'sikeu_pengajuan_pencairan_kas',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengajuanPencairanKas updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the PengajuanPencairanKas "deleted" event.
     */
    public function deleted(PengajuanPencairanKas $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIKEU',
                action: 'delete',
                tableName: 'sikeu_pengajuan_pencairan_kas',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PengajuanPencairanKas deleted: ' . $e->getMessage());
        }
    }
}
