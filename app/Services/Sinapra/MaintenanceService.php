<?php

namespace App\Services\Sinapra;

use App\Models\MaintenanceLog;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    /**
     * Membuat tiket Maintenance baru (laporan kerusakan).
     */
    public function createMaintenanceLog(array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = $data['status'] ?? 'dilaporkan';
            $data['tanggal_lapor'] = $data['tanggal_lapor'] ?? now()->toDateString();

            $log = MaintenanceLog::create($data);

            // Update status Aset jika maintenance terkait aset
            if (!empty($data['aset_id'])) {
                $aset = Aset::find($data['aset_id']);
                if ($aset) {
                    $aset->update(['status' => 'maintenance']);
                }
            }

            // Update status Ruangan jika maintenance terkait ruangan
            if (!empty($data['ruangan_id'])) {
                $ruangan = Ruangan::find($data['ruangan_id']);
                if ($ruangan) {
                    $ruangan->update(['status' => 'maintenance']);
                }
            }

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'maintenance_log',
                recordId: $log->id,
                newValues: $log->toArray()
            );

            return $log;
        });
    }

    /**
     * Mengubah status / progress perbaikan maintenance log.
     */
    public function updateMaintenanceStatus(MaintenanceLog $log, array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($log, $data) {
            $oldValues = $log->toArray();
            $log->update($data);

            // Jika status selesai, kembalikan status aset & ruangan ke aktif/tersedia
            if (isset($data['status']) && $data['status'] === 'selesai') {
                if ($log->aset_id) {
                    $aset = Aset::find($log->aset_id);
                    if ($aset) {
                        $aset->update(['status' => 'tersedia', 'kondisi' => 'baik']);
                    }
                }

                if ($log->ruangan_id) {
                    $ruangan = Ruangan::find($log->ruangan_id);
                    if ($ruangan) {
                        $ruangan->update(['status' => 'aktif']);
                    }
                }
            }

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'maintenance_log',
                recordId: $log->id,
                oldValues: $oldValues,
                newValues: $log->fresh()->toArray()
            );

            return $log->fresh();
        });
    }

    /**
     * Menghapus tiket maintenance log.
     */
    public function deleteMaintenanceLog(MaintenanceLog $log): void
    {
        DB::transaction(function () use ($log) {
            $oldValues = $log->toArray();
            $log->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'maintenance_log',
                recordId: $log->id,
                oldValues: $oldValues
            );
        });
    }
}
