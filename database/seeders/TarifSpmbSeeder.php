<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\TarifSpmb;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;

class TarifSpmbSeeder extends Seeder
{
    public function run(): void
    {
        $jenisSpmb = MasterBiaya::where('kode', 'SPMB_ADM')->first();

        if (!$jenisSpmb) {
            $jenisSpmb = MasterBiaya::create([
                'kode' => 'SPMB_ADM',
                'nama' => 'Biaya Pendaftaran SPMB',
                'tipe' => 'spmb_adm',
                'nominal_standar' => 250000,
                'deskripsi' => 'Biaya formulir & ujian seleksi penerimaan mahasiswa baru',
                'is_recurring' => false,
                'is_active' => true,
            ]);
        }

        $firstJalur = JalurMasuk::first();
        $jalurId = $firstJalur ? $firstJalur->id : 1;

        $firstGel = GelombangPenerimaan::first();
        $gelId = $firstGel ? $firstGel->id : 1;

        TarifSpmb::updateOrCreate(
            [
                'master_biaya_id' => $jenisSpmb->id,
                'jalur_id' => $jalurId,
                'gelombang_id' => $gelId,
            ],
            [
                'nominal' => 250000,
                'is_active' => true,
            ]
        );
    }
}
