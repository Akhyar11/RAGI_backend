<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Akun kas-bank per kanal sumber dana (BSN manual, BSN H2H, Xendit).
     * 102.01 tetap Bank BNI manual, 102.02 tetap Mandiri UKT.
     */
    public function up(): void
    {
        $akun = [
            ['kode_akun' => '102.03', 'nama_akun' => 'Bank BSN Manual Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.04', 'nama_akun' => 'Bank BSN Host-to-Host Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
            ['kode_akun' => '102.05', 'nama_akun' => 'Xendit Penampungan Disbursement', 'kelompok' => 'aset', 'saldo_normal' => 'debet'],
        ];

        foreach ($akun as $a) {
            if (DB::table('sikeu_akun_keuangan')->where('kode_akun', $a['kode_akun'])->doesntExist()) {
                DB::table('sikeu_akun_keuangan')->insert(array_merge($a, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('sikeu_akun_keuangan')->whereIn('kode_akun', ['102.03', '102.04', '102.05'])->delete();
    }
};
