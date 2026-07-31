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

        $fakultasTeknik = UnitKerja::updateOrCreate(['kode' => 'FT'], [
            'induk_id' => $rektorat->id,
            'nama' => 'Fakultas Teknik',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'IF'], [
            'induk_id' => $fakultasTeknik->id,
            'nama' => 'Program Studi Informatika',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::updateOrCreate(['kode' => 'SI'], [
            'induk_id' => $fakultasTeknik->id,
            'nama' => 'Program Studi Sistem Informasi',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);
    }
}
