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
            ['kode' => 'prioritas_maintenance', 'nama' => 'Prioritas Tiket Perawatan', 'modul' => 'sinapra'],
            ['kode' => 'status_tiket_maintenance', 'nama' => 'Status Pengerjaan Maintenance', 'modul' => 'sinapra'],
            ['kode' => 'status_kelayakan_kalibrasi', 'nama' => 'Status Kelayakan Alat Lab Kalibrasi', 'modul' => 'sinapra'],
            ['kode' => 'sumber_anggaran_pengadaan', 'nama' => 'Sumber Anggaran Pengadaan Barang', 'modul' => 'sinapra'],
            ['kode' => 'kategori_bhp', 'nama' => 'Kategori Bahan Habis Pakai Lab', 'modul' => 'sinapra'],
            ['kode' => 'satuan_barang', 'nama' => 'Satuan Ukuran Barang & BHP', 'modul' => 'sinapra'],
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

            // Prioritas Maintenance
            ['tipe' => 'prioritas_maintenance', 'kode' => 'rendah', 'nama' => 'Prioritas Rendah', 'urutan' => 1],
            ['tipe' => 'prioritas_maintenance', 'kode' => 'sedang', 'nama' => 'Prioritas Sedang', 'urutan' => 2],
            ['tipe' => 'prioritas_maintenance', 'kode' => 'tinggi', 'nama' => 'Prioritas Tinggi', 'urutan' => 3],
            ['tipe' => 'prioritas_maintenance', 'kode' => 'darurat', 'nama' => 'Darurat / Emergency', 'urutan' => 4],

            // Status Pengerjaan Maintenance
            ['tipe' => 'status_tiket_maintenance', 'kode' => 'dilaporkan', 'nama' => 'Dilaporkan / Menunggu Penugasan', 'urutan' => 1],
            ['tipe' => 'status_tiket_maintenance', 'kode' => 'proses', 'nama' => 'Proses Pengerjaan Teknisi', 'urutan' => 2],
            ['tipe' => 'status_tiket_maintenance', 'kode' => 'selesai', 'nama' => 'Selesai Ditangani', 'urutan' => 3],
            ['tipe' => 'status_tiket_maintenance', 'kode' => 'batal', 'nama' => 'Dibatalkan', 'urutan' => 4],

            // Status Kelayakan Kalibrasi
            ['tipe' => 'status_kelayakan_kalibrasi', 'kode' => 'laik', 'nama' => 'Laik Operasional', 'urutan' => 1],
            ['tipe' => 'status_kelayakan_kalibrasi', 'kode' => 'butuh_perbaikan', 'nama' => 'Butuh Perbaikan / Kalibrasi Ulang', 'urutan' => 2],
            ['tipe' => 'status_kelayakan_kalibrasi', 'kode' => 'tidak_laik', 'nama' => 'Tidak Laik (Afkir)', 'urutan' => 3],

            // Sumber Anggaran Pengadaan
            ['tipe' => 'sumber_anggaran_pengadaan', 'kode' => 'apbn', 'nama' => 'Anggaran Pendapatan & Belanja Negara (APBN)', 'urutan' => 1],
            ['tipe' => 'sumber_anggaran_pengadaan', 'kode' => 'yayasan', 'nama' => 'Kas Dana Yayasan Perguruan Tinggi', 'urutan' => 2],
            ['tipe' => 'sumber_anggaran_pengadaan', 'kode' => 'boptn', 'nama' => 'Bantuan Operasional PTN (BOPTN)', 'urutan' => 3],
            ['tipe' => 'sumber_anggaran_pengadaan', 'kode' => 'hibah', 'nama' => 'Hibah Kompetisi / Lembaga Mitra', 'urutan' => 4],
            ['tipe' => 'sumber_anggaran_pengadaan', 'kode' => 'kas_internal', 'nama' => 'Pendapatan Mandiri / Kas Internal', 'urutan' => 5],

            // Kategori BHP Lab
            ['tipe' => 'kategori_bhp', 'kode' => 'komponen_elektronik', 'nama' => 'Komponen Elektronik & Robotika', 'urutan' => 1],
            ['tipe' => 'kategori_bhp', 'kode' => 'reagen_kimia', 'nama' => 'Bahan Kimia & Reagen', 'urutan' => 2],
            ['tipe' => 'kategori_bhp', 'kode' => 'glassware', 'nama' => 'Alat Gelas & Wadah Reaksi', 'urutan' => 3],
            ['tipe' => 'kategori_bhp', 'kode' => 'alat_tulis_kantor', 'nama' => 'Alat Tulis Kantor & Kertas', 'urutan' => 4],
            ['tipe' => 'kategori_bhp', 'kode' => 'consumables_mekanik', 'nama' => 'Consumables Mekanik & Mesin', 'urutan' => 5],
            ['tipe' => 'kategori_bhp', 'kode' => 'lainnya', 'nama' => 'Bahan Habis Pakai Lainnya', 'urutan' => 6],

            // Satuan Barang
            ['tipe' => 'satuan_barang', 'kode' => 'unit', 'nama' => 'Unit', 'urutan' => 1],
            ['tipe' => 'satuan_barang', 'kode' => 'buah', 'nama' => 'Buah', 'urutan' => 2],
            ['tipe' => 'satuan_barang', 'kode' => 'pcs', 'nama' => 'Pcs (Pieces)', 'urutan' => 3],
            ['tipe' => 'satuan_barang', 'kode' => 'box', 'nama' => 'Box / Kotak', 'urutan' => 4],
            ['tipe' => 'satuan_barang', 'kode' => 'rim', 'nama' => 'Rim', 'urutan' => 5],
            ['tipe' => 'satuan_barang', 'kode' => 'botol', 'nama' => 'Botol', 'urutan' => 6],
            ['tipe' => 'satuan_barang', 'kode' => 'roll', 'nama' => 'Roll / Gulung', 'urutan' => 7],
            ['tipe' => 'satuan_barang', 'kode' => 'set', 'nama' => 'Set', 'urutan' => 8],
            ['tipe' => 'satuan_barang', 'kode' => 'paket', 'nama' => 'Paket', 'urutan' => 9],
            ['tipe' => 'satuan_barang', 'kode' => 'liter', 'nama' => 'Liter', 'urutan' => 10],
            ['tipe' => 'satuan_barang', 'kode' => 'meter', 'nama' => 'Meter', 'urutan' => 11],
        ];

        foreach ($items as $item) {
            MasterReferensi::firstOrCreate(
                ['tipe' => $item['tipe'], 'kode' => $item['kode'], 'modul' => 'sinapra'],
                ['nama' => $item['nama'], 'urutan' => $item['urutan'], 'is_active' => true]
            );
        }
    }
}
