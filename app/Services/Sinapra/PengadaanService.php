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

            $updateData = [
                'status' => $status,
                'disetujui_oleh' => $approverId,
            ];

            // Integrasi SIKEU: Saat disetujui, otomatis buat tiket permohonan dana operasional di SIKEU
            if ($status === 'disetujui' && empty($pengajuan->sikeu_pencairan_id)) {
                $pencairan = \App\Models\Sikeu\PengajuanPencairanKas::create([
                    'nomor_pengajuan' => 'OPR-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
                    'unit_kerja_id' => $pengajuan->unit_kerja_id,
                    'unit_kas_id' => null, // Ditentukan oleh Bagian Keuangan saat pencairan
                    'pemohon_id' => $pengajuan->diajukan_oleh,
                    'judul_pengajuan' => 'Pengadaan Barang: ' . $pengajuan->judul,
                    'deskripsi' => $pengajuan->alasan_kebutuhan,
                    'nominal_diajukan' => (float) $pengajuan->estimasi_anggaran,
                    'nominal_disetujui' => (float) $pengajuan->estimasi_anggaran,
                    'jenis_pengajuan' => 'sarpras',
                    'kategori_pengajuan' => 'pengadaan_barang',
                    'status' => 'pending_keuangan',
                    'kanal' => 'sinapra_pengadaan',
                    'referensi_eksternal' => 'sinapra_pengadaan:' . $pengajuan->id,
                ]);

                $updateData['sikeu_pencairan_id'] = $pencairan->id;

                // Salin rincian detail barang ke items pencairan kas SIKEU
                $pengajuan->loadMissing('details');
                foreach ($pengajuan->details as $d) {
                    \App\Models\Sikeu\PengajuanItem::create([
                        'pengajuan_id' => $pencairan->id,
                        'nama_barang' => $d->nama_barang,
                        'qty' => (float) $d->jumlah,
                        'satuan' => $d->satuan ?? 'unit',
                        'harga_satuan' => (float) $d->harga_satuan_estimasi,
                        'subtotal' => (float) $d->total_estimasi,
                        'keterangan' => $d->spesifikasi,
                    ]);
                }
            }

            $pengajuan->update($updateData);

            AuditLogService::record(
                module: 'SINAPRA',
                action: ($status === 'disetujui') ? 'approve' : (($status === 'ditolak') ? 'reject' : 'update'),
                tableName: 'pengajuan_pengadaan',
                recordId: $pengajuan->id,
                oldValues: $oldValues,
                newValues: $pengajuan->fresh()->toArray()
            );

            return $pengajuan->fresh()->load(['details', 'pencairanKas']);
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
