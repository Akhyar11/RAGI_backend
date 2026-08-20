<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Spmb\TipeUjianSpmb;

class TipeUjianSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'kode' => 'tulis',
                'nama' => 'Ujian Tulis Komputer (CBT)',
                'deskripsi' => 'Ujian seleksi berbasis Komputer Assisted Test (CAT/CBT)',
                'is_active' => true,
            ],
            [
                'kode' => 'praktik',
                'nama' => 'Ujian Praktik / Keterampilan',
                'deskripsi' => 'Ujian tes unjuk kerja, minat, bakat, dan keterampilan khusus',
                'is_active' => true,
            ],
            [
                'kode' => 'wawancara',
                'nama' => 'Wawancara / Wawancara Online',
                'deskripsi' => 'Sesi wawancara tatap muka atau video conference interaktif',
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            TipeUjianSpmb::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
