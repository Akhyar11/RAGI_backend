<?php

namespace Database\Seeders\Sinapra;

use Illuminate\Database\Seeder;
use App\Models\Gedung;
use App\Models\Ruangan;
use App\Models\KategoriAset;
use App\Models\Aset;

class SinapraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Master Gedung
        $gedungUtama = Gedung::updateOrCreate(
            ['kode' => 'GD-REKTORAT'],
            [
                'nama' => 'Gedung Rektorat & Administrasi Pusat',
                'jumlah_lantai' => 4,
                'alamat' => 'Jl. Kampus Utama No. 1, Surakarta',
                'tahun_bangun' => 2018,
                'luas_m2' => 2500.00,
                'status' => 'aktif',
            ]
        );

        $gedungKuliah = Gedung::updateOrCreate(
            ['kode' => 'GD-KULIAH-A'],
            [
                'nama' => 'Gedung Perkuliahan Terpadu A',
                'jumlah_lantai' => 3,
                'alamat' => 'Jl. Kampus Utama No. 1, Surakarta',
                'tahun_bangun' => 2020,
                'luas_m2' => 1800.00,
                'status' => 'aktif',
            ]
        );

        $gedungLab = Gedung::updateOrCreate(
            ['kode' => 'GD-LAB-SERBAGUNA'],
            [
                'nama' => 'Gedung Laboratorium & RIset',
                'jumlah_lantai' => 2,
                'alamat' => 'Jl. Kampus Utama No. 1, Surakarta',
                'tahun_bangun' => 2021,
                'luas_m2' => 1200.00,
                'status' => 'aktif',
            ]
        );

        // 2. Seed Master Ruangan
        $aulaUtama = Ruangan::updateOrCreate(
            ['kode' => 'R-AULA-01'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama' => 'Aula Utama Nusantara',
                'lantai' => 1,
                'tipe' => 'aula',
                'kapasitas' => 500,
                'ada_ac' => true,
                'ada_proyektor' => true,
                'ada_wifi' => true,
                'status' => 'aktif',
            ]
        );

        $ruangKelasA101 = Ruangan::updateOrCreate(
            ['kode' => 'R-KULIAH-A101'],
            [
                'gedung_id' => $gedungKuliah->id,
                'nama' => 'Ruang Kuliah Teori A101',
                'lantai' => 1,
                'tipe' => 'kelas',
                'kapasitas' => 40,
                'ada_ac' => true,
                'ada_proyektor' => true,
                'ada_wifi' => true,
                'status' => 'aktif',
            ]
        );

        $labKomputer = Ruangan::updateOrCreate(
            ['kode' => 'R-LAB-KOMP-01'],
            [
                'gedung_id' => $gedungLab->id,
                'nama' => 'Laboratorium Komputer & Rekayasa Perangkat Lunak',
                'lantai' => 2,
                'tipe' => 'lab',
                'kapasitas' => 35,
                'ada_ac' => true,
                'ada_proyektor' => true,
                'ada_wifi' => true,
                'status' => 'aktif',
            ]
        );

        // 3. Seed Master Kategori Aset
        $katElektronik = KategoriAset::updateOrCreate(
            ['kode' => 'KAT-ELEKTRONIK'],
            [
                'induk_id' => null,
                'nama' => 'Peralatan Elektronik & IT',
                'masa_manfaat_tahun' => 4,
                'tarif_penyusutan_persen' => 25.00,
            ]
        );

        $katKomputer = KategoriAset::updateOrCreate(
            ['kode' => 'KAT-KOMPUTER'],
            [
                'induk_id' => $katElektronik->id,
                'nama' => 'Komputer & Perangkat Server',
                'masa_manfaat_tahun' => 4,
                'tarif_penyusutan_persen' => 25.00,
            ]
        );

        $katFurniture = KategoriAset::updateOrCreate(
            ['kode' => 'KAT-FURNITURE'],
            [
                'induk_id' => null,
                'nama' => 'Meubelair & Furnitur Kantor',
                'masa_manfaat_tahun' => 8,
                'tarif_penyusutan_persen' => 12.50,
            ]
        );

        // 4. Seed Master Aset
        Aset::updateOrCreate(
            ['kode_aset' => 'AST-2026-PC-001'],
            [
                'kategori_id' => $katKomputer->id,
                'ruangan_id' => $labKomputer->id,
                'nama' => 'PC Workstation Core i7 16GB RAM',
                'merk' => 'DELL',
                'model' => 'OptiPlex 7090',
                'serial_number' => 'SN-DEL-987123',
                'tanggal_perolehan' => '2026-01-15',
                'harga_perolehan' => 18500000.00,
                'nilai_buku' => 18500000.00,
                'kondisi' => 'baik',
                'status' => 'tersedia',
            ]
        );

        Aset::updateOrCreate(
            ['kode_aset' => 'AST-2026-PRJ-001'],
            [
                'kategori_id' => $katElektronik->id,
                'ruangan_id' => $ruangKelasA101->id,
                'nama' => 'Proyektor High-Lumen 4000 Ansi',
                'merk' => 'EPSON',
                'model' => 'EB-X400',
                'serial_number' => 'SN-EPS-456789',
                'tanggal_perolehan' => '2026-02-10',
                'harga_perolehan' => 8200000.00,
                'nilai_buku' => 8200000.00,
                'kondisi' => 'baik',
                'status' => 'tersedia',
            ]
        );
    }
}
