<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Siakad\TahunAkademik;

class SiakadTahunAkademikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'kode' => '20241',
                'nama' => '2024/2025 Ganjil',
                'tahun_mulai' => 2024,
                'tahun_selesai' => 2025,
                'is_active' => false,
                'mode_penilaian' => 'persentase',
            ],
            [
                'kode' => '20242',
                'nama' => '2024/2025 Genap',
                'tahun_mulai' => 2024,
                'tahun_selesai' => 2025,
                'is_active' => false,
                'mode_penilaian' => 'persentase',
            ],
            [
                'kode' => '20251',
                'nama' => '2025/2026 Ganjil',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'is_active' => false,
                'mode_penilaian' => 'persentase',
            ],
            [
                'kode' => '20252',
                'nama' => '2025/2026 Genap',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'is_active' => false,
                'mode_penilaian' => 'persentase',
            ],
            [
                'kode' => '20261',
                'nama' => '2026/2027 Ganjil',
                'tahun_mulai' => 2026,
                'tahun_selesai' => 2027,
                'is_active' => true,
                'mode_penilaian' => 'persentase',
            ],
        ];

        foreach ($data as $item) {
            TahunAkademik::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
