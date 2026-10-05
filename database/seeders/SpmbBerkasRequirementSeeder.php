<?php

namespace Database\Seeders;

use App\Models\Spmb\BerkasRequirement;
use App\Models\Spmb\JalurMasuk;
use Illuminate\Database\Seeder;

class SpmbBerkasRequirementSeeder extends Seeder
{
    /**
     * Persyaratan berkas standar per jalur masuk SPMB.
     * `jenis_dokumen` diselaraskan dengan key yang dipakai form registrasi
     * (ktp, kk, pas_foto, ijazah, rapor). Idempoten (updateOrCreate).
     */
    public function run(): void
    {
        $items = [
            ['jenis_dokumen' => 'ktp', 'label' => 'KTP / Kartu Identitas', 'wajib' => true, 'urutan' => 1],
            ['jenis_dokumen' => 'kk', 'label' => 'Kartu Keluarga (KK)', 'wajib' => true, 'urutan' => 2],
            ['jenis_dokumen' => 'pas_foto', 'label' => 'Pas Foto Resmi (3x4)', 'wajib' => true, 'urutan' => 3],
            ['jenis_dokumen' => 'ijazah', 'label' => 'Ijazah / SKL', 'wajib' => true, 'urutan' => 4],
            ['jenis_dokumen' => 'rapor', 'label' => 'Transkrip Nilai / Rapor Semester 1-5', 'wajib' => false, 'urutan' => 5],
        ];

        $jalurList = JalurMasuk::all();

        if ($jalurList->isEmpty()) {
            $this->command->warn('Tidak ada jalur masuk. Jalankan seeder jalur masuk terlebih dahulu.');
            return;
        }

        foreach ($jalurList as $jalur) {
            foreach ($items as $item) {
                BerkasRequirement::updateOrCreate(
                    [
                        'jalur_masuk_id' => $jalur->id,
                        'jenis_dokumen' => $item['jenis_dokumen'],
                    ],
                    [
                        'label' => $item['label'],
                        'wajib' => $item['wajib'],
                        'urutan' => $item['urutan'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Seeder persyaratan berkas SPMB berhasil dijalankan.');
    }
}
