<?php

namespace Database\Seeders\Sippm;

use App\Models\Simpeg\UnitKerja;
use App\Models\Sippm\StandarIku5Prodi;
use Illuminate\Database\Seeder;

class StandarIku5ProdiSeeder extends Seeder
{
    public function run(): void
    {
        $tahunAkademik = '2025/2026';

        $prodiTargets = [
            'IF' => [
                'target_publikasi_scopus' => 8,
                'target_publikasi_sinta' => 15,
                'target_hki_paten' => 6,
                'target_buku_isbn' => 4,
            ],
            'SI' => [
                'target_publikasi_scopus' => 6,
                'target_publikasi_sinta' => 12,
                'target_hki_paten' => 5,
                'target_buku_isbn' => 4,
            ],
            'DKV' => [
                'target_publikasi_scopus' => 4,
                'target_publikasi_sinta' => 10,
                'target_hki_paten' => 8,
                'target_buku_isbn' => 5,
            ],
            'TE' => [
                'target_publikasi_scopus' => 7,
                'target_publikasi_sinta' => 10,
                'target_hki_paten' => 5,
                'target_buku_isbn' => 3,
            ],
            'MI' => [
                'target_publikasi_scopus' => 5,
                'target_publikasi_sinta' => 10,
                'target_hki_paten' => 4,
                'target_buku_isbn' => 3,
            ],
            'D3SI' => [
                'target_publikasi_scopus' => 4,
                'target_publikasi_sinta' => 8,
                'target_hki_paten' => 4,
                'target_buku_isbn' => 2,
            ],
        ];

        foreach ($prodiTargets as $kodeProdi => $target) {
            $unitKerja = UnitKerja::where('kode', $kodeProdi)->first();

            if ($unitKerja) {
                StandarIku5Prodi::updateOrCreate(
                    [
                        'unit_kerja_id' => $unitKerja->id,
                        'tahun_akademik' => $tahunAkademik,
                    ],
                    [
                        'target_publikasi_scopus' => $target['target_publikasi_scopus'],
                        'target_publikasi_sinta' => $target['target_publikasi_sinta'],
                        'target_hki_paten' => $target['target_hki_paten'],
                        'target_buku_isbn' => $target['target_buku_isbn'],
                    ]
                );
            }
        }
    }
}
