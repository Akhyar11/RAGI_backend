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

        Jabatan::create([
            'unit_kerja_id' => $rektorat?->id,
            'nama' => 'Rektor',
            'tipe' => 'struktural',
            'level_jabatan' => 1,
            'is_active' => true,
        ]);

        Jabatan::create([
            'unit_kerja_id' => $ft?->id,
            'nama' => 'Dekan Fakultas Teknik',
            'tipe' => 'struktural',
            'level_jabatan' => 2,
            'is_active' => true,
        ]);

        Jabatan::create([
            'unit_kerja_id' => $prodiIf?->id,
            'nama' => 'Ketua Program Studi Informatika',
            'tipe' => 'struktural',
            'level_jabatan' => 3,
            'is_active' => true,
        ]);

        Jabatan::create([
            'unit_kerja_id' => $biroKeuangan?->id,
            'nama' => 'Kepala Biro Keuangan',
            'tipe' => 'struktural',
            'level_jabatan' => 2,
            'is_active' => true,
        ]);

        Jabatan::create([
            'unit_kerja_id' => $prodiIf?->id,
            'nama' => 'Dosen Pengajar',
            'tipe' => 'fungsional',
            'level_jabatan' => 4,
            'is_active' => true,
        ]);

        Jabatan::create([
            'unit_kerja_id' => $biroKeuangan?->id,
            'nama' => 'Staf Administrasi Keuangan',
            'tipe' => 'teknis',
            'level_jabatan' => 4,
            'is_active' => true,
        ]);
    }
}
