<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACCOUNTS = [
        ['kode_akun' => '202.02', 'nama_akun' => 'Utang PPh Pasal 23', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
        ['kode_akun' => '202.03', 'nama_akun' => 'Utang PPN', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit'],
        ['kode_akun' => '502.02', 'nama_akun' => 'Beban Pemeliharaan', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
        ['kode_akun' => '505.01', 'nama_akun' => 'Beban Penyusutan Aset Tetap', 'kelompok' => 'beban', 'saldo_normal' => 'debet'],
        ['kode_akun' => '105.01', 'nama_akun' => 'Akumulasi Penyusutan Aset Tetap', 'kelompok' => 'aset', 'saldo_normal' => 'kredit'],
    ];

    public function up(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $exists = DB::table('sikeu_akun_keuangan')
                ->where('kode_akun', $account['kode_akun'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('sikeu_akun_keuangan')->insert($account + [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Master COA may already be referenced by operational journals; keep it on rollback.
    }
};
