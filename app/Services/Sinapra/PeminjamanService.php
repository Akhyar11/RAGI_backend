<?php

namespace App\Services\Sinapra;

use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class PeminjamanService
{
    public function __construct(private GedungRuanganService $gedungRuanganService) {}

    /**
     * Pengajuan Peminjaman Ruangan oleh User.
     */
    public function applyPeminjamanRuangan(array $data, int $userId): PeminjamanRuangan
    {
        return DB::transaction(function () use ($data, $userId) {
            // Cek ketersediaan jadwal ruangan
            $isAvailable = $this->gedungRuanganService->checkRuanganKetersediaan(
                ruanganId: $data['ruangan_id'],
                tanggal: $data['tanggal'],
                jamMulai: $data['jam_mulai'],
                jamSelesai: $data['jam_selesai']
            );

            if (!$isAvailable) {
                throw new Exception("Ruangan tidak tersedia pada tanggal dan jam yang dipilih (terdapat bentrok jadwal atau perkuliahan).");
            }

            $ruangan = Ruangan::findOrFail($data['ruangan_id']);
            $isLab = ($ruangan->tipe === 'lab') || $ruangan->laboran()->exists();

            $data['user_id'] = $userId;
            $data['status'] = $isLab ? 'pending_laboran' : 'pending_admin_sinapra';

            $peminjaman = PeminjamanRuangan::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'peminjaman_ruangan',
                recordId: $peminjaman->id,
                newValues: $peminjaman->toArray()
            );

            return $peminjaman;
        });
    }

    /**
     * Persetujuan Tahap Laboran untuk Peminjaman Ruangan Laboratorium.
     */
    public function approveLaboranRuangan(
        PeminjamanRuangan $peminjaman,
        int $laboranId,
        bool $isApproved,
        ?string $catatanLaboran = null
    ): PeminjamanRuangan {
        return DB::transaction(function () use ($peminjaman, $laboranId, $isApproved, $catatanLaboran) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $peminjaman->status = 'pending_admin_sinapra';
            } else {
                $peminjaman->status = 'ditolak_laboran';
            }

            $peminjaman->laboran_approved_by = $laboranId;
            $peminjaman->laboran_approved_at = now();
            $peminjaman->catatan_laboran = $catatanLaboran;
            $peminjaman->save();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: $isApproved ? 'approve' : 'reject',
                    tableName: 'peminjaman_ruangan',
                    recordId: $peminjaman->id,
                    oldValues: $oldValues,
                    newValues: $peminjaman->fresh()->toArray(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Persetujuan Akhir Peminjaman Ruangan oleh Admin SINAPRA.
     */
    public function approvePeminjamanRuangan(
        PeminjamanRuangan $peminjaman,
        int $approverId,
        bool $isApproved,
        ?string $catatanPenolakan = null
    ): PeminjamanRuangan {
        return DB::transaction(function () use ($peminjaman, $approverId, $isApproved, $catatanPenolakan) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                // Double check bentrok jadwal sebelum disetujui
                $isAvailable = $this->gedungRuanganService->checkRuanganKetersediaan(
                    ruanganId: $peminjaman->ruangan_id,
                    tanggal: $peminjaman->tanggal,
                    jamMulai: $peminjaman->jam_mulai,
                    jamSelesai: $peminjaman->jam_selesai,
                    excludePeminjamanId: $peminjaman->id
                );

                if (!$isAvailable) {
                    throw new Exception("Tidak dapat menyetujui. Ruangan sudah disetujui untuk peminjam lain pada jam yang sama.");
                }

                $peminjaman->status = 'disetujui';
            } else {
                $peminjaman->status = 'ditolak_admin_sinapra';
                $peminjaman->catatan_penolakan = $catatanPenolakan;
            }

            $peminjaman->disetujui_oleh = $approverId;
            $peminjaman->admin_approved_at = now();
            $peminjaman->save();

            AuditLogService::record(
                module: 'SINAPRA',
                action: $isApproved ? 'approve' : 'reject',
                tableName: 'peminjaman_ruangan',
                recordId: $peminjaman->id,
                oldValues: $oldValues,
                newValues: $peminjaman->fresh()->toArray()
            );

            return $peminjaman->fresh();
        });
    }

    /**
     * Pengajuan Peminjaman Aset oleh User.
     */
    public function applyPeminjamanAset(array $data, int $userId): PeminjamanAset
    {
        return DB::transaction(function () use ($data, $userId) {
            $aset = Aset::with('ruangan')->findOrFail($data['aset_id']);

            if (!$aset->is_borrowable) {
                throw new Exception("Aset '{$aset->nama}' merupakan aset tetap yang tidak dapat dipinjam.");
            }

            if ($aset->status !== 'tersedia') {
                throw new Exception("Aset '{$aset->nama}' sedang tidak tersedia untuk dipinjam (status: {$aset->status}).");
            }

            $isLab = $aset->is_lab_asset || ($aset->ruangan && $aset->ruangan->tipe === 'lab');

            $data['user_id'] = $userId;
            $data['status'] = $isLab ? 'pending_laboran' : 'pending_admin_sinapra';

            $peminjaman = PeminjamanAset::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'peminjaman_aset',
                recordId: $peminjaman->id,
                newValues: $peminjaman->toArray()
            );

            return $peminjaman;
        });
    }

    /**
     * Persetujuan Tahap Laboran untuk Peminjaman Aset Laboratorium.
     */
    public function approveLaboranAset(
        PeminjamanAset $peminjaman,
        int $laboranId,
        bool $isApproved,
        ?string $catatanLaboran = null
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $laboranId, $isApproved, $catatanLaboran) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $peminjaman->status = 'pending_admin_sinapra';
            } else {
                $peminjaman->status = 'ditolak_laboran';
            }

            $peminjaman->laboran_approved_by = $laboranId;
            $peminjaman->laboran_approved_at = now();
            $peminjaman->catatan_laboran = $catatanLaboran;
            $peminjaman->save();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: $isApproved ? 'approve' : 'reject',
                    tableName: 'peminjaman_aset',
                    recordId: $peminjaman->id,
                    oldValues: $oldValues,
                    newValues: $peminjaman->fresh()->toArray(),
                    request: request()
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $peminjaman->fresh();
        });
    }

    /**
     * Persetujuan Akhir Peminjaman Aset oleh Admin SINAPRA.
     */
    public function approvePeminjamanAset(
        PeminjamanAset $peminjaman,
        int $approverId,
        bool $isApproved,
        ?string $catatanPenolakan = null
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $approverId, $isApproved, $catatanPenolakan) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $aset = Aset::findOrFail($peminjaman->aset_id);
                if ($aset->status !== 'tersedia') {
                    throw new Exception("Aset sedang tidak tersedia untuk dipinjam.");
                }

                $peminjaman->status = 'disetujui';
                $aset->update(['status' => 'dipinjam']);
            } else {
                $peminjaman->status = 'ditolak_admin_sinapra';
                $peminjaman->catatan_penolakan = $catatanPenolakan;
            }

            $peminjaman->disetujui_oleh = $approverId;
            $peminjaman->admin_approved_at = now();
            $peminjaman->save();

            AuditLogService::record(
                module: 'SINAPRA',
                action: $isApproved ? 'approve' : 'reject',
                tableName: 'peminjaman_aset',
                recordId: $peminjaman->id,
                oldValues: $oldValues,
                newValues: $peminjaman->fresh()->toArray()
            );

            return $peminjaman->fresh();
        });
    }

    /**
     * Proses pengembalian barang/aset yang dipinjam.
     */
    public function prosesPengembalianAset(PeminjamanAset $peminjaman, string $kondisiKembali): PeminjamanAset
    {
        return DB::transaction(function () use ($peminjaman, $kondisiKembali) {
            $oldValues = $peminjaman->toArray();

            $peminjaman->update([
                'tanggal_kembali_aktual' => now()->toDateString(),
                'kondisi_kembali' => $kondisiKembali,
                'status' => 'kembali',
            ]);

            // Update status & kondisi aset
            $aset = Aset::findOrFail($peminjaman->aset_id);
            $asetStatus = ($kondisiKembali === 'rusak_berat') ? 'maintenance' : 'tersedia';
            $aset->update([
                'status' => $asetStatus,
                'kondisi' => $kondisiKembali,
            ]);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'peminjaman_aset',
                recordId: $peminjaman->id,
                oldValues: $oldValues,
                newValues: $peminjaman->fresh()->toArray()
            );

            return $peminjaman->fresh();
        });
    }
}
