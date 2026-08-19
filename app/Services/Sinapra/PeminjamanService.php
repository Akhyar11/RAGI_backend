<?php

namespace App\Services\Sinapra;

use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\Aset;
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
                throw new Exception("Ruangan tidak tersedia pada tanggal dan jam yang dipilih (terdapat bentrok jadwal).");
            }

            $data['user_id'] = $userId;
            $data['status'] = 'pending';

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
     * Persetujuan (Approve/Reject) Peminjaman Ruangan oleh Admin Sarpras.
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
                $peminjaman->status = 'ditolak';
                $peminjaman->catatan_penolakan = $catatanPenolakan;
            }

            $peminjaman->disetujui_oleh = $approverId;
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
            $aset = Aset::findOrFail($data['aset_id']);

            if ($aset->status !== 'tersedia') {
                throw new Exception("Aset '{$aset->nama}' sedang tidak tersedia untuk dipinjam (status: {$aset->status}).");
            }

            $data['user_id'] = $userId;
            $data['status'] = 'pending';

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
     * Persetujuan Peminjaman Aset oleh Admin Sarpras.
     */
    public function approvePeminjamanAset(
        PeminjamanAset $peminjaman,
        int $approverId,
        bool $isApproved
    ): PeminjamanAset {
        return DB::transaction(function () use ($peminjaman, $approverId, $isApproved) {
            $oldValues = $peminjaman->toArray();

            if ($isApproved) {
                $aset = Aset::findOrFail($peminjaman->aset_id);
                if ($aset->status !== 'tersedia') {
                    throw new Exception("Aset sedang tidak tersedia untuk dipinjam.");
                }

                $peminjaman->status = 'dipinjam';
                $aset->update(['status' => 'dipinjam']);
            } else {
                $peminjaman->status = 'terlambat'; // atau dibatalkan
            }

            $peminjaman->disetujui_oleh = $approverId;
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
