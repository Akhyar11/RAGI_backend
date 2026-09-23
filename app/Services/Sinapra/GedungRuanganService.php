<?php

namespace App\Services\Sinapra;

use App\Models\Gedung;
use App\Models\Ruangan;
use App\Models\PeminjamanRuangan;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Memeriksa peminjaman ruangan yang sedang aktif/pending dan jadwal perkuliahan SIAKAD.
     */
    public function checkRuanganKetersediaan(
        int $ruanganId,
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        ?int $excludePeminjamanId = null
    ): bool {
        // 1. Cek bentrok dengan peminjaman ruangan yang sudah disetujui atau sedang dalam proses approval
        $activeStatuses = ['pending', 'pending_laboran', 'pending_admin_sinapra', 'disetujui'];

        $query = PeminjamanRuangan::where('ruangan_id', $ruanganId)
            ->where('tanggal', $tanggal)
            ->whereIn('status', $activeStatuses)
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

        if ($query->exists()) {
            return false;
        }

        // 2. Cek bentrok dengan jadwal kelas perkuliahan reguler modul SIAKAD (jika tabel siakad_kelas tersedia)
        if (Schema::hasTable('siakad_kelas')) {
            $dayOfWeek = Carbon::parse($tanggal)->dayOfWeek;
            $daysMap = [
                0 => 'minggu',
                1 => 'senin',
                2 => 'selasa',
                3 => 'rabu',
                4 => 'kamis',
                5 => 'jumat',
                6 => 'sabtu',
            ];
            $hari = $daysMap[$dayOfWeek] ?? 'senin';

            $conflictSiakad = DB::table('siakad_kelas')
                ->where('ruangan_id', $ruanganId)
                ->where('status', 'aktif')
                ->where('hari', $hari)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($jamMulai, $jamSelesai) {
                    $q->whereBetween('jam_mulai', [$jamMulai, $jamSelesai])
                      ->orWhereBetween('jam_selesai', [$jamMulai, $jamSelesai])
                      ->orWhere(function ($sub) use ($jamMulai, $jamSelesai) {
                          $sub->where('jam_mulai', '<=', $jamMulai)
                              ->where('jam_selesai', '>=', $jamSelesai);
                      });
                })
                ->exists();

            if ($conflictSiakad) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mengambil daftar laboran yang ditugaskan pada ruangan dengan pagination.
     */
    public function getLaboranByRuangan(Ruangan $ruangan, array $params = [])
    {
        $perPage = min(100, (int) ($params['per_page'] ?? 15));
        $query = $ruangan->laboran();

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'name'];
        $sortBy = in_array($params['sort_by'] ?? '', $allowedSort, true) ? $params['sort_by'] : 'created_at';
        $sortOrder = ($params['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Menugaskan laboran ke ruangan laboratorium.
     */
    public function assignLaboran(Ruangan $ruangan, int $userId, bool $isPrimary = true): \App\Models\LaboranRuangan
    {
        return DB::transaction(function () use ($ruangan, $userId, $isPrimary) {
            $assignment = \App\Models\LaboranRuangan::updateOrCreate(
                [
                    'ruangan_id' => $ruangan->id,
                    'user_id' => $userId,
                ],
                [
                    'is_primary' => $isPrimary,
                ]
            );

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'create',
                    tableName: 'sinapra_laboran_ruangan',
                    recordId: $assignment->id,
                    newValues: $assignment->toArray(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $assignment->load(['user', 'ruangan']);
        });
    }

    /**
     * Menghapus penugasan laboran dari ruangan.
     */
    public function unassignLaboran(Ruangan $ruangan, int $userId): void
    {
        DB::transaction(function () use ($ruangan, $userId) {
            $assignment = \App\Models\LaboranRuangan::where('ruangan_id', $ruangan->id)
                ->where('user_id', $userId)
                ->first();

            if ($assignment) {
                $oldValues = $assignment->toArray();
                $assignment->delete();

                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: 'delete',
                        tableName: 'sinapra_laboran_ruangan',
                        recordId: $assignment->id,
                        oldValues: $oldValues,
                        request: request()
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
