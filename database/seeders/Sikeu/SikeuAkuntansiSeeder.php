<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\AkunKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SikeuAkuntansiSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('akun_keuangan')->truncate();
        Schema::enableForeignKeyConstraints();

        $akuns = [
            // 1. ASET
            ['kode_akun' => '101.01', 'nama_akun' => 'Kas Utama Kampus (Rektorat)', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '101.02', 'nama_akun' => 'Kas Unit Fakultas / Petty Cash', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.01', 'nama_akun' => 'Bank Operasional BNI Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.02', 'nama_akun' => 'Bank Penerimaan UKT Mandiri', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '103.01', 'nama_akun' => 'Piutang UKT / SPP Mahasiswa', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '103.02', 'nama_akun' => 'Piutang Dana Hibah Riset (SIPPM)', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '150.01', 'nama_akun' => 'Aset Tetap Gedung & Fasilitas Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '150.02', 'nama_akun' => 'Aset Tetap Peralatan Laboratorium & TI', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],

            // 2. LIABILITAS
            ['kode_akun' => '201.01', 'nama_akun' => 'Utang Jangka Pendek Operasional', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.01', 'nama_akun' => 'Utang Pajak PPh 21 Pegawai/Dosen', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.02', 'nama_akun' => 'Utang Pajak PPh 23 Jasa Vendor', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '202.03', 'nama_akun' => 'Utang Pajak PPN 11%', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '203.01', 'nama_akun' => 'Titipan Beasiswa Mahasiswa', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],

            // 3. EKUITAS
            ['kode_akun' => '301.01', 'nama_akun' => 'Ekuitas Dana Terikat Institusi', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '301.02', 'nama_akun' => 'Saldo Laba / Defisit Ditahan (Surplus)', 'kelompok' => 'ekuitas', 'saldo_normal' => 'kredit'],

            // 4. PENDAPATAN
            ['kode_akun' => '401.01', 'nama_akun' => 'Pendapatan UKT / SPP Mahasiswa', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '401.02', 'nama_akun' => 'Pendapatan Registrasi SPMB', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '402.01', 'nama_akun' => 'Pendapatan Dana Hibah Riset & PkM (SIPPM)', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '402.02', 'nama_akun' => 'Pendapatan Kerjasama Donatur & Instansi', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode_akun' => '403.01', 'nama_akun' => 'Pendapatan Wisuda & Legalisir', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit'],

            // 5. BEBAN
            ['kode_akun' => '501.01', 'nama_akun' => 'Beban Gaji & Honorarium (SIMPEG)', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '501.02', 'nama_akun' => 'Beban Tunjangan & Insentif SDM', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.01', 'nama_akun' => 'Beban Operasional Listrik, Air, & Internet', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '502.02', 'nama_akun' => 'Beban Pemeliharaan Sarana & Prasarana', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '503.01', 'nama_akun' => 'Beban Hibah Penelitian & PkM (SIPPM)', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '504.01', 'nama_akun' => 'Beban Beasiswa & Potongan Mahasiswa', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
            ['kode_akun' => '505.01', 'nama_akun' => 'Beban Administrasi Bank & Payment Gateway', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
        ];

        foreach ($akuns as $a) {
            AkunKeuangan::create([
                'kode_akun' => $a['kode_akun'],
                'nama_akun' => $a['nama_akun'],
                'kelompok' => $a['kelompok'],
                'saldo_normal' => $a['saldo_normal'],
                'is_active' => true,
            ]);
        }
    }
}
