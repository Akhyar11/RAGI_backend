<?php

namespace App\Services\Sinapra;

use App\Models\PengajuanPengadaan;
use App\Models\DetailPengadaan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class PengadaanService
{
    /**
     * Membuat pengajuan pengadaan baru beserta rincian detail barang.
     */
    public function createPengajuan(array $data, int $diajukanOleh): PengajuanPengadaan
    {
        return DB::transaction(function () use ($data, $diajukanOleh) {
            $data['diajukan_oleh'] = $diajukanOleh;
            $data['status'] = $data['status'] ?? 'draft';
            $data['tanggal_pengajuan'] = $data['tanggal_pengajuan'] ?? now()->toDateString();

            $details = $data['details'] ?? [];
            unset($data['details']);

            // Hitung estimasi anggaran dari total rincian barang
            $estimasiAnggaran = 0;
            foreach ($details as &$detail) {
                $jumlah = $detail['jumlah'] ?? 1;
                $hargaSatuan = $detail['harga_satuan_estimasi'] ?? 0;
                $detail['total_estimasi'] = $jumlah * $hargaSatuan;
                $estimasiAnggaran += $detail['total_estimasi'];
            }

            $data['estimasi_anggaran'] = $estimasiAnggaran;

            $pengajuan = PengajuanPengadaan::create($data);

            foreach ($details as $detail) {
                $pengajuan->details()->create($detail);
            }

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'pengajuan_pengadaan',
                recordId: $pengajuan->id,
                newValues: $pengajuan->load('details')->toArray()
            );

            return $pengajuan->load('details');
        });
    }

    /**
     * Mengubah status / persetujuan pengajuan pengadaan barang.
     */
    public function updateStatusPengadaan(
        PengajuanPengadaan $pengajuan,
        int $approverId,
        string $status
    ): PengajuanPengadaan {
        return DB::transaction(function () use ($pengajuan, $approverId, $status) {
            $allowedStatus = ['draft', 'diajukan', 'disetujui', 'ditolak', 'proses_pengadaan', 'selesai'];
            if (!in_array($status, $allowedStatus)) {
                throw new Exception("Status pengadaan tidak valid.");
            }

            $oldValues = $pengajuan->toArray();

            $pengajuan->update([
                'status' => $status,
                'disetujui_oleh' => $approverId,
            ]);

            AuditLogService::record(
                module: 'SINAPRA',
                action: ($status === 'disetujui') ? 'approve' : (($status === 'ditolak') ? 'reject' : 'update'),
                tableName: 'pengajuan_pengadaan',
                recordId: $pengajuan->id,
                oldValues: $oldValues,
                newValues: $pengajuan->fresh()->toArray()
            );

            return $pengajuan->fresh()->load('details');
        });
    }

    /**
     * Menghapus pengajuan pengadaan barang.
     */
    public function deletePengajuan(PengajuanPengadaan $pengajuan): void
    {
        DB::transaction(function () use ($pengajuan) {
            $oldValues = $pengajuan->toArray();
            $pengajuan->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'pengajuan_pengadaan',
                recordId: $pengajuan->id,
                oldValues: $oldValues
            );
        });
    }
}
