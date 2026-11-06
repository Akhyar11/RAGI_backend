<?php

namespace App\Observers\Spmb;

use App\Models\Spmb\SpmbKodeDiskonPenggunaan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class SpmbKodeDiskonPenggunaanObserver
{
    public function created(SpmbKodeDiskonPenggunaan $penggunaan): void
    {
        $this->record('create', $penggunaan, null, $penggunaan->toArray());
    }

    public function updated(SpmbKodeDiskonPenggunaan $penggunaan): void
    {
        $this->record('update', $penggunaan, $penggunaan->getRawOriginal(), $penggunaan->getChanges());
    }

    public function deleting(SpmbKodeDiskonPenggunaan $penggunaan): void
    {
        $this->record('delete', $penggunaan, $penggunaan->getRawOriginal(), null);
    }

    private function record(string $action, SpmbKodeDiskonPenggunaan $penggunaan, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: $action,
                tableName: $penggunaan->getTable(),
                recordId: $penggunaan->id,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat penggunaan kode diskon SPMB: '.$e->getMessage());
        }
    }
}
