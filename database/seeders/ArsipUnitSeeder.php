<?php

namespace Database\Seeders;

use App\Models\Arsip\KlasifikasiSurat;
use Illuminate\Database\Seeder;

/**
 * Master Kode Unit Pengolah untuk modul Arsip.
 * Data ini dapat dikelola/diedit admin melalui halaman Master Klasifikasi Arsip.
 */
class ArsipUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['kode' => 'REK', 'nama' => 'Rektorat'],
            ['kode' => 'BAA', 'nama' => 'Biro Administrasi Akademik & Kemahasiswaan'],
            ['kode' => 'BAK', 'nama' => 'Biro Administrasi Keuangan'],
            ['kode' => 'BAU', 'nama' => 'Biro Administrasi Umum'],
            ['kode' => 'SPMB', 'nama' => 'Panitia Penerimaan Mahasiswa Baru'],
            ['kode' => 'LPPM', 'nama' => 'Lembaga Penelitian & Pengabdian Masyarakat'],
        ];

        foreach ($units as $unit) {
            KlasifikasiSurat::updateOrCreate(
                ['kode' => $unit['kode']],
                [
                    'nama' => $unit['nama'],
                    'kategori' => 'unit',
                    'keterangan' => 'Kode unit pengolah Arsip',
                    'is_active' => true,
                ]
            );
        }
    }
}
