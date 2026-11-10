<?php

namespace Database\Seeders\Sikeu;

use App\Models\Sikeu\UnitKas;
use Illuminate\Database\Seeder;

class UnitKasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mendaftarkan master unit kas awal secara idempoten untuk seluruh kanal transaksi kampus.
     */
    public function run(): void
    {
        $unitKasList = [
            [
                'nama_kas'       => 'Kas Tunai Rektorat',
                'tipe_kas'       => 'operasional',
                'kanal'          => 'tunai',
                'bank_name'      => null,
                'status'         => true,
            ],
            [
                'nama_kas'       => 'Bank BNI Manual Kampus',
                'tipe_kas'       => 'operasional',
                'kanal'          => 'bank_manual',
                'bank_name'      => 'BNI',
                'status'         => true,
            ],
            [
                'nama_kas'       => 'Bank BSN Host-to-Host Kampus',
                'tipe_kas'       => 'operasional',
                'kanal'          => 'bank_h2h',
                'bank_name'      => 'BSN',
                'status'         => true,
            ],
            [
                'nama_kas'       => 'Xendit Disbursement Kampus',
                'tipe_kas'       => 'operasional',
                'kanal'          => 'xendit',
                'bank_name'      => 'Xendit',
                'status'         => true,
            ],
            [
                'nama_kas'       => 'Midtrans Disbursement Kampus',
                'tipe_kas'       => 'operasional',
                'kanal'          => 'midtrans',
                'bank_name'      => 'Midtrans',
                'status'         => true,
            ],
        ];

        foreach ($unitKasList as $data) {
            UnitKas::firstOrCreate(
                ['kanal' => $data['kanal']],
                array_merge($data, [
                    'saldo_awal'     => 0,
                    'saldo_saat_ini' => 0,
                ])
            );
        }
    }
}
