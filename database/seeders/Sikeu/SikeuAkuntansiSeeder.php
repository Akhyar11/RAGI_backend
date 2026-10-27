<?php

namespace Database\Seeders\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use Illuminate\Database\Seeder;

/**
 * Bagan akun standar minimal agar jurnal otomatis SIKEU
 * (terbit tagihan, bayar, koreksi, dispensasi) bisa berjalan,
 * termasuk di environment testing.
 *
 * Idempoten: aman dijalankan berulang kali.
 */
class SikeuAkuntansiSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['kode_akun' => '101.01', 'nama_akun' => 'Kas Tunai', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.01', 'nama_akun' => 'Kas Bank', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '103.01', 'nama_akun' => 'Piutang UKT/SPP', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '401.01', 'nama_akun' => 'Pendapatan UKT', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.02', 'nama_akun' => 'Pendapatan SPMB', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '502.01', 'nama_akun' => 'Beban Operasional', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '504.01', 'nama_akun' => 'Beban Beasiswa & Potongan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '301.02', 'nama_akun' => 'Laba Ditahan', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '301.03', 'nama_akun' => 'Ekuitas Saldo Awal', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
        ];

        foreach ($akun as $row) {
            AkunKeuangan::updateOrCreate(
                ['kode_akun' => $row['kode_akun']],
                array_merge($row, ['is_active' => true])
            );
        }
    }
}
