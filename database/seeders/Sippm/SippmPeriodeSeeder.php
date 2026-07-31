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
                'tahun_anggaran' => 2026,
                'nama_gelombang' => 'Hibah Internal Semester Ganjil TA 2026/2027',
                'tgl_buka_proposal' => '2026-01-10',
                'tgl_tutup_proposal' => '2026-03-30',
                'tgl_tutup_monev' => '2026-07-15',
                'tgl_tutup_laporan' => '2026-11-30',
                'is_active' => true,
            ],
            [
                'tahun_anggaran' => 2026,
                'nama_gelombang' => 'Hibah BIMA Kemendikbudristek Gelombang I 2026',
                'tgl_buka_proposal' => '2026-02-01',
                'tgl_tutup_proposal' => '2026-04-15',
                'tgl_tutup_monev' => '2026-08-30',
                'tgl_tutup_laporan' => '2026-12-15',
                'is_active' => true,
            ],
        ];

        foreach ($periodeList as $periode) {
            PeriodeHibah::updateOrCreate(
                [
                    'tahun_anggaran' => $periode['tahun_anggaran'],
                    'nama_gelombang' => $periode['nama_gelombang'],
                ],
                $periode
            );
        }
    }
}
