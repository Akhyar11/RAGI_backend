<?php

namespace Database\Seeders\SPMB;

use Illuminate\Database\Seeder;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\BerkasRequirement;

class BerkasRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $jalurs = JalurMasuk::all();

        foreach ($jalurs as $jalur) {
            $isPrestasi = str_contains(strtolower($jalur->nama . ' ' . $jalur->kode), 'prestasi');

            $requirements = [
                [
                    'jenis_dokumen' => 'ktp',
                    'label' => 'KTP / Kartu Identitas',
                    'wajib' => true,
                    'urutan' => 1,
                    'is_active' => true,
                ],
                [
                    'jenis_dokumen' => 'kk',
                    'label' => 'Kartu Keluarga (KK)',
                    'wajib' => true,
                    'urutan' => 2,
                    'is_active' => true,
                ],
                [
                    'jenis_dokumen' => 'pas_foto',
                    'label' => 'Pas Foto Resmi (3x4)',
                    'wajib' => true,
                    'urutan' => 3,
                    'is_active' => true,
                ],
                [
                    'jenis_dokumen' => 'ijazah',
                    'label' => 'Ijazah / SKL',
                    'wajib' => true,
                    'urutan' => 4,
                    'is_active' => true,
                ],
            ];

            if ($isPrestasi) {
                $requirements[] = [
                    'jenis_dokumen' => 'prestasi',
                    'label' => 'Sertifikat / Piagam Prestasi',
                    'wajib' => true,
                    'urutan' => 5,
                    'is_active' => true,
                ];
                $requirements[] = [
                    'jenis_dokumen' => 'rapor',
                    'label' => 'Transkrip Nilai / Rapor Semester 1-5',
                    'wajib' => false,
                    'urutan' => 6,
                    'is_active' => true,
                ];
            } else {
                $requirements[] = [
                    'jenis_dokumen' => 'rapor',
                    'label' => 'Transkrip Nilai / Rapor Semester 1-5',
                    'wajib' => false,
                    'urutan' => 5,
                    'is_active' => true,
                ];
            }

            foreach ($requirements as $req) {
                BerkasRequirement::updateOrCreate(
                    [
                        'jalur_masuk_id' => $jalur->id,
                        'jenis_dokumen' => $req['jenis_dokumen'],
                    ],
                    [
                        'label' => $req['label'],
                        'wajib' => $req['wajib'],
                        'urutan' => $req['urutan'],
                        'is_active' => $req['is_active'],
                    ]
                );
            }
        }
    }
}
