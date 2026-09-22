<?php

namespace App\Observers\Simpeg;

use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PegawaiKomponenGajiObserver
{
    private static array $oldValues = [];

    /**
     * Handle the PegawaiKomponenGaji "created" event.
     */
    public function created(PegawaiKomponenGaji $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_pegawai_komponen_gaji',
                recordId: $model->id,
                oldValues: null,
                newValues: $cleanValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PegawaiKomponenGaji created: ' . $e->getMessage());
        }
    }

    /**
     * Capture pre-update original state.
     */
    public function updating(PegawaiKomponenGaji $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getOriginal())
            ->only($dirty)
            ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
            ->toArray();
    }

    /**
     * Handle the PegawaiKomponenGaji "updated" event.
     */
    public function updated(PegawaiKomponenGaji $model): void
    {
        try {
            if ($model->wasChanged()) {
                $changes = collect($model->getChanges())
                    ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                    ->toArray();

                $oldValues = self::$oldValues[spl_object_id($model)] ?? [];
                unset(self::$oldValues[spl_object_id($model)]);

                AuditLogService::record(
                    module: 'SIMPEG',
                    action: 'update',
                    tableName: 'simpeg_pegawai_komponen_gaji',
                    recordId: $model->id,
                    oldValues: $oldValues,
                    newValues: $changes,
                    request: request()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PegawaiKomponenGaji updated: ' . $e->getMessage());
        }
    }

    /**
     * Handle the PegawaiKomponenGaji "deleted" event.
     */
    public function deleted(PegawaiKomponenGaji $model): void
    {
        try {
            $cleanValues = collect($model->toArray())
                ->except(['password', 'token', 'remember_token', 'pin', 'secret'])
                ->toArray();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_pegawai_komponen_gaji',
                recordId: $model->id,
                oldValues: $cleanValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::error('Audit log failed on PegawaiKomponenGaji deleted: ' . $e->getMessage());
        }
    }
}
