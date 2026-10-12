<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $accounts = [
            // Beban Penggajian & Tunjangan SDM
            [
                'kode_akun' => '501.01',
                'nama_akun' => 'Beban Gaji & Honorarium',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            [
                'kode_akun' => '501.02',
                'nama_akun' => 'Beban Tunjangan & Insentif SDM',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            // Beban Operasional Umum & Beasiswa
            [
                'kode_akun' => '502.01',
                'nama_akun' => 'Beban Operasional Umum',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            [
                'kode_akun' => '504.01',
                'nama_akun' => 'Beban Beasiswa & Potongan Mahasiswa',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            // Utang Potongan & Pajak Payroll
            [
                'kode_akun' => '201.01',
                'nama_akun' => 'Utang Potongan & Titipan Karyawan',
                'kelompok' => 'liabilitas',
                'saldo_normal' => 'kredit',
                'is_active' => true,
            ],
            [
                'kode_akun' => '202.01',
                'nama_akun' => 'Utang PPh Pasal 21',
                'kelompok' => 'liabilitas',
                'saldo_normal' => 'kredit',
                'is_active' => true,
            ],
            // Kas & Bank
            [
                'kode_akun' => '101.01',
                'nama_akun' => 'Kas Tunai Kampus',
                'kelompok' => 'aset',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            [
                'kode_akun' => '102.01',
                'nama_akun' => 'Bank Operasional Utama',
                'kelompok' => 'aset',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            [
                'kode_akun' => '103.01',
                'nama_akun' => 'Piutang UKT / Mahasiswa',
                'kelompok' => 'aset',
                'saldo_normal' => 'debet',
                'is_active' => true,
            ],
            // Pendapatan
            [
                'kode_akun' => '401.01',
                'nama_akun' => 'Pendapatan Pendidikan (UKT)',
                'kelompok' => 'pendapatan',
                'saldo_normal' => 'kredit',
                'is_active' => true,
            ],
            [
                'kode_akun' => '401.02',
                'nama_akun' => 'Pendapatan Seleksi & Registrasi PMB',
                'kelompok' => 'pendapatan',
                'saldo_normal' => 'kredit',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            $existing = DB::table('sikeu_akun_keuangan')->where('kode_akun', $acc['kode_akun'])->first();
            if ($existing) {
                DB::table('sikeu_akun_keuangan')
                    ->where('id', $existing->id)
                    ->update(array_merge($acc, ['updated_at' => now()]));
            } else {
                DB::table('sikeu_akun_keuangan')->insert(array_merge($acc, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sikeu_akun_keuangan')->whereIn('kode_akun', [
            '501.01', '501.02', '502.01', '504.01',
            '201.01', '202.01', '101.01', '102.01',
            '103.01', '401.01', '401.02',
        ])->delete();
    }
};
