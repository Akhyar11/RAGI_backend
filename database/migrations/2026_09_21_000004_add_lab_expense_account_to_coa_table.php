<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Akun beban laboratorium terpisah agar biaya lab/magang tidak
     * menumpuk di pemeliharaan dan laporan mendukung bucket laboratorium.
     */
    public function up(): void
    {
        if (DB::table('sikeu_akun_keuangan')->where('kode_akun', '502.03')->doesntExist()) {
            DB::table('sikeu_akun_keuangan')->insert([
                'kode_akun' => '502.03',
                'nama_akun' => 'Beban Laboratorium & Praktikum',
                'kelompok' => 'beban',
                'saldo_normal' => 'debet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sikeu_akun_keuangan')->where('kode_akun', '502.03')->delete();
    }
};
