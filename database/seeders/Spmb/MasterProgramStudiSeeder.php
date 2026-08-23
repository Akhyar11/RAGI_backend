<?php

namespace Database\Seeders\Spmb;

use Illuminate\Database\Seeder;
use App\Models\Spmb\MasterProgramStudi;

class MasterProgramStudiSeeder extends Seeder
{
    public function run(): void
    {
        $prodis = [
            ['kode_prodi' => 'TI-S1', 'kode_prodi_dikti' => '55201', 'nama' => 'S1 Teknik Informatika', 'jenjang' => 'S1', 'akreditasi' => 'Unggul', 'is_active' => true],
            ['kode_prodi' => 'SI-S1', 'kode_prodi_dikti' => '57201', 'nama' => 'S1 Sistem Informasi', 'jenjang' => 'S1', 'akreditasi' => 'A', 'is_active' => true],
            ['kode_prodi' => 'DKV-S1', 'kode_prodi_dikti' => '90241', 'nama' => 'S1 Desain Komunikasi Visual', 'jenjang' => 'S1', 'akreditasi' => 'B', 'is_active' => true],
            ['kode_prodi' => 'MI-D3', 'kode_prodi_dikti' => '57401', 'nama' => 'D3 Manajemen Informatika', 'jenjang' => 'D3', 'akreditasi' => 'B', 'is_active' => true],
            ['kode_prodi' => 'AK-S1', 'kode_prodi_dikti' => '62201', 'nama' => 'S1 Akuntansi', 'jenjang' => 'S1', 'akreditasi' => 'A', 'is_active' => true],
            ['kode_prodi' => 'MJ-S1', 'kode_prodi_dikti' => '61201', 'nama' => 'S1 Manajemen', 'jenjang' => 'S1', 'akreditasi' => 'A', 'is_active' => true],
        ];

        foreach ($prodis as $prodi) {
            MasterProgramStudi::updateOrCreate(
                ['kode_prodi' => $prodi['kode_prodi']],
                $prodi
            );
        }
    }
}