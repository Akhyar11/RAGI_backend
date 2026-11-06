<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\SpmbKodeDiskon;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class SpmbKodeDiskonObserver
{
    public function created(SpmbKodeDiskon $kodeDiskon): void
    {
        $this->record('create', $kodeDiskon, null, $kodeDiskon->toArray());
    }

    public function updated(SpmbKodeDiskon $kodeDiskon): void
    {
        $this->record('update', $kodeDiskon, $kodeDiskon->getRawOriginal(), $kodeDiskon->getChanges());
    }

    public function deleting(SpmbKodeDiskon $kodeDiskon): void
    {
        $this->record('delete', $kodeDiskon, $kodeDiskon->getRawOriginal(), null);
    }

    private function record(string $action, SpmbKodeDiskon $kodeDiskon, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: $action,
                tableName: $kodeDiskon->getTable(),
                recordId: $kodeDiskon->id,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit kode diskon SPMB: '.$e->getMessage());
        }
    }
}
