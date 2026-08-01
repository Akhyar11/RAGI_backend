<?php

namespace Database\Seeders\Sippm;

use Illuminate\Database\Seeder;
use App\Models\Sippm\PeriodeHibah;

class SippmPeriodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $periodeList = [
            [
                'tahun_anggaran' => '2025/2026',
                'nama_gelombang' => 'Hibah Internal TA 2025/2026',
                'tgl_buka_proposal' => '2025-01-10',
                'tgl_tutup_proposal' => '2025-03-30',
                'tgl_tutup_monev' => '2025-07-15',
                'tgl_tutup_laporan' => '2025-11-30',
                'is_active' => true,
            ],
            [
                'tahun_anggaran' => '2024/2025',
                'nama_gelombang' => 'Hibah Internal TA 2024/2025',
                'tgl_buka_proposal' => '2024-01-10',
                'tgl_tutup_proposal' => '2024-03-30',
                'tgl_tutup_monev' => '2024-07-15',
                'tgl_tutup_laporan' => '2024-11-30',
                'is_active' => true,
            ],
        ];

        foreach ($periodeList as $periode) {
            PeriodeHibah::updateOrCreate(
                [
                    'tahun_anggaran' => $periode['tahun_anggaran'],
                ],
                $periode
            );
        }
    }
}
