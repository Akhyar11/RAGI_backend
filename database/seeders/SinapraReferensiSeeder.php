<?php

namespace Database\Seeders;

use App\Models\System\MasterReferensi;
use App\Models\System\MasterTipeReferensi;
use Illuminate\Database\Seeder;

class SinapraReferensiSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['kode' => 'metode_disposal', 'nama' => 'Metode Pemutihan / Disposal Aset', 'modul' => 'sinapra'],
            ['kode' => 'kondisi_aset', 'nama' => 'Kondisi Fisik Aset', 'modul' => 'sinapra'],
            ['kode' => 'status_keberadaan_aset', 'nama' => 'Status Keberadaan Aset Audit', 'modul' => 'sinapra'],
            ['kode' => 'status_opname', 'nama' => 'Status Sesi Stock Opname', 'modul' => 'sinapra'],
            ['kode' => 'status_mutasi', 'nama' => 'Status Mutasi Aset', 'modul' => 'sinapra'],
            ['kode' => 'status_disposal', 'nama' => 'Status BAP Penghapusan Aset', 'modul' => 'sinapra'],
        ];

        foreach ($types as $i => $t) {
            MasterTipeReferensi::firstOrCreate(
                ['kode' => $t['kode'], 'modul' => $t['modul']],
                ['nama' => $t['nama'], 'urutan' => $i + 1, 'is_active' => true]
            );
        }

        $items = [
            // Metode Disposal
            ['tipe' => 'metode_disposal', 'kode' => 'rusak_total', 'nama' => 'Rusak Total / Pemusnahan', 'urutan' => 1],
            ['tipe' => 'metode_disposal', 'kode' => 'kadaluwarsa', 'nama' => 'Kedaluwarsa Teknis & Manfaat', 'urutan' => 2],
            ['tipe' => 'metode_disposal', 'kode' => 'hilang', 'nama' => 'Kehilangan Resmi', 'urutan' => 3],
            ['tipe' => 'metode_disposal', 'kode' => 'lelang', 'nama' => 'Penjualan / Pelelangan Terbuka', 'urutan' => 4],
            ['tipe' => 'metode_disposal', 'kode' => 'hibah', 'nama' => 'Hibah Sosial / Pendidikan', 'urutan' => 5],
            ['tipe' => 'metode_disposal', 'kode' => 'lainnya', 'nama' => 'Metode Lainnya', 'urutan' => 6],

            // Kondisi Fisik
            ['tipe' => 'kondisi_aset', 'kode' => 'baik', 'nama' => 'Kondisi Baik', 'urutan' => 1],
            ['tipe' => 'kondisi_aset', 'kode' => 'rusak_ringan', 'nama' => 'Rusak Ringan', 'urutan' => 2],
            ['tipe' => 'kondisi_aset', 'kode' => 'rusak_berat', 'nama' => 'Rusak Berat', 'urutan' => 3],

            // Status Keberadaan Aset Audit
            ['tipe' => 'status_keberadaan_aset', 'kode' => 'sesuai', 'nama' => 'Sesuai di Tempat', 'urutan' => 1],
            ['tipe' => 'status_keberadaan_aset', 'kode' => 'rusak', 'nama' => 'Ditemukan Rusak', 'urutan' => 2],
            ['tipe' => 'status_keberadaan_aset', 'kode' => 'tidak_ditemukan', 'nama' => 'Tidak Ditemukan (Hilang)', 'urutan' => 3],
            ['tipe' => 'status_keberadaan_aset', 'kode' => 'tertukar', 'nama' => 'Tertukar Ruangan', 'urutan' => 4],

            // Status Sesi Opname
            ['tipe' => 'status_opname', 'kode' => 'berlangsung', 'nama' => 'Sedang Berlangsung', 'urutan' => 1],
            ['tipe' => 'status_opname', 'kode' => 'selesai', 'nama' => 'Telah Ditutup & Disinkron', 'urutan' => 2],

            // Status Mutasi
            ['tipe' => 'status_mutasi', 'kode' => 'diajukan', 'nama' => 'Diajukan', 'urutan' => 1],
            ['tipe' => 'status_mutasi', 'kode' => 'disetujui', 'nama' => 'Disetujui', 'urutan' => 2],
            ['tipe' => 'status_mutasi', 'kode' => 'ditolak', 'nama' => 'Ditolak', 'urutan' => 3],

            // Status Disposal
            ['tipe' => 'status_disposal', 'kode' => 'diajukan', 'nama' => 'Menunggu Persetujuan', 'urutan' => 1],
            ['tipe' => 'status_disposal', 'kode' => 'disetujui', 'nama' => 'Disetujui Dihapus', 'urutan' => 2],
            ['tipe' => 'status_disposal', 'kode' => 'ditolak', 'nama' => 'Usulan Ditolak', 'urutan' => 3],
        ];

        foreach ($items as $item) {
            MasterReferensi::firstOrCreate(
                ['tipe' => $item['tipe'], 'kode' => $item['kode'], 'modul' => 'sinapra'],
                ['nama' => $item['nama'], 'urutan' => $item['urutan'], 'is_active' => true]
            );
        }
    }
}
