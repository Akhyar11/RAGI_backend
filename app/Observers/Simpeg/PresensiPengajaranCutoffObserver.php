<?php

namespace App\Observers\Simpeg;

use App\Models\Simpeg\PresensiPengajaranCutoff;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class PresensiPengajaranCutoffObserver
{
    /**
     * Observer dibuat ulang untuk setiap event oleh container, jadi state
     * antar-event harus disimpan statis dan dibersihkan setelah dipakai.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $oldValues = [];

    public function created(PresensiPengajaranCutoff $model): void
    {
        $this->record('create', $model, null, $model->only(['periode', 'cutoff_at', 'set_by']));
    }

    public function updating(PresensiPengajaranCutoff $model): void
    {
        $dirty = array_keys($model->getDirty());
        self::$oldValues[spl_object_id($model)] = collect($model->getRawOriginal())->only($dirty)->all();
    }

    public function updated(PresensiPengajaranCutoff $model): void
    {
        $old = self::$oldValues[spl_object_id($model)] ?? [];
        unset(self::$oldValues[spl_object_id($model)]);

        if (!$model->wasChanged()) {
            return;
        }

        $this->record('update', $model, $old, $model->getChanges());
    }

    private function record(string $action, PresensiPengajaranCutoff $model, ?array $old, ?array $new): void
    {
        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: $action,
                tableName: $model->getTable(),
                recordId: $model->id,
                oldValues: $old,
                newValues: $new,
                request: request()
            );
        } catch (\Throwable $exception) {
            Log::error('Audit log gagal untuk cut-off presensi pengajaran.', [
                'record_id' => $model->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
