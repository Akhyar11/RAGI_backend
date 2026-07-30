<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $rektorat = UnitKerja::create([
            'kode' => 'REK',
            'nama' => 'Rektorat Universitas',
            'tipe' => 'rektorat',
            'is_active' => true,
        ]);

        $biroKeuangan = UnitKerja::create([
            'induk_id' => $rektorat->id,
            'kode' => 'KU',
            'nama' => 'Biro Keuangan & Administrasi Umum',
            'tipe' => 'biro',
            'is_active' => true,
        ]);

        $lp3m = UnitKerja::create([
            'induk_id' => $rektorat->id,
            'kode' => 'LP3M',
            'nama' => 'Lembaga Penjaminan Mutu & Pengabdian (LP3M)',
            'tipe' => 'lp3m',
            'is_active' => true,
        ]);

        $fakultasTeknik = UnitKerja::create([
            'induk_id' => $rektorat->id,
            'kode' => 'FT',
            'nama' => 'Fakultas Teknik',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        UnitKerja::create([
            'induk_id' => $fakultasTeknik->id,
            'kode' => 'IF',
            'nama' => 'Program Studi Informatika',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        UnitKerja::create([
            'induk_id' => $fakultasTeknik->id,
            'kode' => 'SI',
            'nama' => 'Program Studi Sistem Informasi',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);
    }
}
