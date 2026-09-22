<?php

namespace Database\Seeders\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\UnitKas;
use Illuminate\Database\Seeder;

/**
 * Unit kas per kanal sumber dana (tunai / bank manual / bank H2H / xendit),
 * masing-masing dipetakan ke satu akun COA kas-bank agar laporan per kanal
 * tidak tercampur. Idempoten via updateOrCreate berdasarkan nama_kas.
 */
class SikeuKanalKasSeeder extends Seeder
{
    public function run(): void
    {
        $kanals = [
            [
                'nama_kas' => 'Kas Tunai Rektorat',
                'kanal' => 'tunai',
                'kode_akun' => '101.01',
                'bank_name' => null,
                'deskripsi' => 'Uang tunai di brankas rektorat (serah terima manual)',
            ],
            [
                'nama_kas' => 'Bank BSN Kampus',
                'kanal' => 'bank_manual',
                'kode_akun' => '102.03',
                'bank_name' => 'BSN',
                'deskripsi' => 'Rekening BSN: transfer manual + VA Host-to-Host',
            ],
            [
                'nama_kas' => 'Bank BNI Manual Kampus',
                'kanal' => 'bank_manual',
                'kode_akun' => '102.01',
                'bank_name' => 'BNI',
                'deskripsi' => 'Transfer manual via m-banking/internet banking BNI',
            ],
            [
                'nama_kas' => 'Bank BSN Host-to-Host Kampus',
                'kanal' => 'bank_h2h',
                'kode_akun' => '102.04',
                'bank_name' => 'BSN',
                'deskripsi' => 'Disbursement batch via koneksi Host-to-Host BSN',
            ],
            [
                'nama_kas' => 'Xendit Disbursement Kampus',
                'kanal' => 'xendit',
                'kode_akun' => '102.05',
                'bank_name' => 'Xendit',
                'deskripsi' => 'Penampungan dana keluar via Xendit Disbursement API',
            ],
        ];

        foreach ($kanals as $k) {
            $akun = AkunKeuangan::where('kode_akun', $k['kode_akun'])->first();

            UnitKas::updateOrCreate(
                ['nama_kas' => $k['nama_kas']],
                [
                    'kanal' => $k['kanal'],
                    'akun_keuangan_id' => $akun?->id,
                    'bank_name' => $k['bank_name'],
                    'deskripsi' => $k['deskripsi'],
                    'status' => true,
                ]
            );
        }
    }
}
