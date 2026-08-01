<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $rektorat = UnitKerja::updateOrCreate(['kode' => 'REK'], [
            'nama' => 'Rektorat Universitas',
            'tipe' => 'rektorat',
            'is_active' => true,
        ]);

        $biroKeuangan = UnitKerja::updateOrCreate(['kode' => 'KU'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Biro Keuangan & Administrasi Umum',
            'tipe' => 'biro',
            'is_active' => true,
        ]);

        $lp3m = UnitKerja::updateOrCreate(['kode' => 'LP3M'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Lembaga Penjaminan Mutu & Pengabdian (LP3M)',
            'tipe' => 'lp3m',
            'is_active' => true,
        ]);

        // Fakultas
        $fasilkom = UnitKerja::updateOrCreate(['kode' => 'FASILKOM'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Fakultas Ilmu Komputer',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $fakultasTeknik = UnitKerja::updateOrCreate(['kode' => 'FT'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Fakultas Teknik',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $fakultasDesain = UnitKerja::updateOrCreate(['kode' => 'FDS'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Fakultas Desain & Seni',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        // 6 Program Studi
        UnitKerja::updateOrCreate(['kode' => 'IF'], [
            'induk_id' => $fasilkom->id,
            'nama' => 'S1 Teknik Informatika',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'SI'], [
            'induk_id' => $fasilkom->id,
            'nama' => 'S1 Sistem Informasi',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'DKV'], [
            'induk_id' => $fakultasDesain->id,
            'nama' => 'S1 Desain Komunikasi Visual',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'TE'], [
            'induk_id' => $fakultasTeknik->id,
            'nama' => 'S1 Teknik Elektro',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'MI'], [
            'induk_id' => $fasilkom->id,
            'nama' => 'S1 Manajemen Informatika',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'D3SI'], [
            'induk_id' => $fasilkom->id,
            'nama' => 'D3 Sistem Informasi',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);
    }
}
