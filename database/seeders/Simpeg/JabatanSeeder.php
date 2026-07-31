<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\Jabatan;
use App\Models\Simpeg\UnitKerja;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        $rektorat = UnitKerja::where('kode', 'REK')->first();
        $ft = UnitKerja::where('kode', 'FT')->first();
        $prodiIf = UnitKerja::where('kode', 'IF')->first();
        $biroKeuangan = UnitKerja::where('kode', 'KU')->first();

        Jabatan::firstOrCreate(['nama' => 'Rektor'], [
            'unit_kerja_id' => $rektorat?->id,
            'tipe' => 'struktural',
            'level_jabatan' => 1,
            'is_active' => true,
        ]);

        Jabatan::firstOrCreate(['nama' => 'Dekan Fakultas Teknik'], [
            'unit_kerja_id' => $ft?->id,
            'tipe' => 'struktural',
            'level_jabatan' => 2,
            'is_active' => true,
        ]);

        Jabatan::firstOrCreate(['nama' => 'Ketua Program Studi Informatika'], [
            'unit_kerja_id' => $prodiIf?->id,
            'tipe' => 'struktural',
            'level_jabatan' => 3,
            'is_active' => true,
        ]);

        Jabatan::firstOrCreate(['nama' => 'Kepala Biro Keuangan'], [
            'unit_kerja_id' => $biroKeuangan?->id,
            'tipe' => 'struktural',
            'level_jabatan' => 2,
            'is_active' => true,
        ]);

        Jabatan::firstOrCreate(['nama' => 'Dosen Pengajar'], [
            'unit_kerja_id' => $prodiIf?->id,
            'tipe' => 'fungsional',
            'level_jabatan' => 4,
            'is_active' => true,
        ]);

        Jabatan::firstOrCreate(['nama' => 'Staf Administrasi Keuangan'], [
            'unit_kerja_id' => $biroKeuangan?->id,
            'tipe' => 'teknis',
            'level_jabatan' => 4,
            'is_active' => true,
        ]);
    }
}
