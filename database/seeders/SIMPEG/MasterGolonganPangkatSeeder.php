<?php

namespace Database\Seeders\SIMPEG;

use App\Models\Simpeg\MasterGolonganPangkat;
use Illuminate\Database\Seeder;

class MasterGolonganPangkatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'kode' => 'tenaga_pengajar',
                'nama' => 'Tenaga Pengajar',
                'pangkat' => 'Penata Muda',
                'ruang' => 'III/a',
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'kode' => 'asisten_ahli',
                'nama' => 'Asisten Ahli',
                'pangkat' => 'Penata Muda / Penata Muda Tingkat I',
                'ruang' => 'III/a - III/b',
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'kode' => 'lektor',
                'nama' => 'Lektor',
                'pangkat' => 'Penata / Penata Tingkat I',
                'ruang' => 'III/c - III/d',
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'kode' => 'lektor_kepala',
                'nama' => 'Lektor Kepala',
                'pangkat' => 'Pembina / Pembina Tingkat I / Pembina Utama Muda',
                'ruang' => 'IV/a - IV/c',
                'urutan' => 4,
                'is_active' => true,
            ],
            [
                'kode' => 'guru_besar',
                'nama' => 'Guru Besar',
                'pangkat' => 'Pembina Utama Madya / Pembina Utama',
                'ruang' => 'IV/d - IV/e',
                'urutan' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            MasterGolonganPangkat::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
