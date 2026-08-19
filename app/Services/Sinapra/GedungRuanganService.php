<?php

namespace App\Services\Sinapra;

use App\Models\Gedung;
use App\Models\Ruangan;
use App\Models\PeminjamanRuangan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use ValidationException;

class GedungRuanganService
{
    /**
     * Membuat data Gedung baru.
     */
    public function createGedung(array $data): Gedung
    {
        return DB::transaction(function () use ($data) {
            $gedung = Gedung::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'gedung',
                recordId: $gedung->id,
                newValues: $gedung->toArray()
            );

            return $gedung;
        });
    }

    /**
     * Mengubah data Gedung.
     */
    public function updateGedung(Gedung $gedung, array $data): Gedung
    {
        return DB::transaction(function () use ($gedung, $data) {
            $oldValues = $gedung->toArray();
            $gedung->update($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'gedung',
                recordId: $gedung->id,
                oldValues: $oldValues,
                newValues: $gedung->fresh()->toArray()
            );

            return $gedung->fresh();
        });
    }

    /**
     * Menghapus data Gedung.
     */
    public function deleteGedung(Gedung $gedung): void
    {
        DB::transaction(function () use ($gedung) {
            $oldValues = $gedung->toArray();
            $gedung->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'gedung',
                recordId: $gedung->id,
                oldValues: $oldValues
            );
        });
    }

    /**
     * Membuat data Ruangan baru.
     */
    public function createRuangan(array $data): Ruangan
    {
        return DB::transaction(function () use ($data) {
            $ruangan = Ruangan::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'ruangan',
                recordId: $ruangan->id,
                newValues: $ruangan->toArray()
            );

            return $ruangan;
        });
    }

    /**
     * Mengubah data Ruangan.
     */
    public function updateRuangan(Ruangan $ruangan, array $data): Ruangan
    {
        return DB::transaction(function () use ($ruangan, $data) {
            $oldValues = $ruangan->toArray();
            $ruangan->update($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'ruangan',
                recordId: $ruangan->id,
                oldValues: $oldValues,
                newValues: $ruangan->fresh()->toArray()
            );

            return $ruangan->fresh();
        });
    }

    /**
     * Menghapus data Ruangan.
     */
    public function deleteRuangan(Ruangan $ruangan): void
    {
        DB::transaction(function () use ($ruangan) {
            $oldValues = $ruangan->toArray();
            $ruangan->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'ruangan',
                recordId: $ruangan->id,
                oldValues: $oldValues
            );
        });
    }

    /**
     * Cek apakah ruangan tersedia pada tanggal dan rentang jam tertentu.
     */
    public function checkRuanganKetersediaan(
        int $ruanganId,
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        ?int $excludePeminjamanId = null
    ): bool {
        $query = PeminjamanRuangan::where('ruangan_id', $ruanganId)
            ->where('tanggal', $tanggal)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->whereBetween('jam_mulai', [$jamMulai, $jamSelesai])
                  ->orWhereBetween('jam_selesai', [$jamMulai, $jamSelesai])
                  ->orWhere(function ($sub) use ($jamMulai, $jamSelesai) {
                      $sub->where('jam_mulai', '<=', $jamMulai)
                          ->where('jam_selesai', '>=', $jamSelesai);
                  });
            });

        if ($excludePeminjamanId) {
            $query->where('id', '!=', $excludePeminjamanId);
        }

        return !$query->exists();
    }
}
