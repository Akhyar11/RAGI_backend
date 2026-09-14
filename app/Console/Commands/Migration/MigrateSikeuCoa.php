<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSikeuCoa extends Command
{
    protected $signature = 'migrate:sikeu-coa {--source=mysql} {--dry-run}';
    protected $description = 'Migrasi Chart of Accounts (COA) dari JSON atau default';
    
    public function handle(): void
    {
        $this->info('Memulai migrasi COA...');
        
        $source = $this->option('source');
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE AKTIF - Tidak ada data yang akan disimpan.');
        }

        DB::beginTransaction();
        try {
            if ($source === 'mysql') {
                $jsonPath = storage_path('migration_data/akun.json');
                if (File::exists($jsonPath)) {
                    $this->info("Membaca dari file JSON: {$jsonPath}");
                    // Logic to parse and insert from JSON would go here
                } else {
                    $this->warn("File JSON tidak ditemukan di {$jsonPath}, beralih ke sumber default.");
                    $this->insertDefaultCoa($isDryRun);
                }
            } else {
                $this->insertDefaultCoa($isDryRun);
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info('Rollback dilakukan karena Dry Run.');
            } else {
                DB::commit();
                $this->info('Migrasi COA Berhasil.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Terjadi Kesalahan: ' . $e->getMessage());
        }
    }

    private function insertDefaultCoa(bool $isDryRun): void
    {
        $this->info('Menggunakan Data COA Default...');
        $coas = [
            ['kode' => '1-1000', 'nama' => 'Kas dan Setara Kas', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-1100', 'nama' => 'Kas Tunai Kampus', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-1200', 'nama' => 'Bank BNI', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-1300', 'nama' => 'Bank BRI', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-2000', 'nama' => 'Piutang Mahasiswa', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-2100', 'nama' => 'Piutang UKT/SPP', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '1-2200', 'nama' => 'Piutang Lainnya', 'kategori' => 'aset', 'saldo_normal' => 'debet'],
            ['kode' => '2-1000', 'nama' => 'Kewajiban Jangka Pendek', 'kategori' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode' => '2-1100', 'nama' => 'Titipan Pembayaran', 'kategori' => 'liabilitas', 'saldo_normal' => 'kredit'],
            ['kode' => '3-1000', 'nama' => 'Ekuitas / Modal', 'kategori' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1000', 'nama' => 'Pendapatan Utama', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1100', 'nama' => 'Pendapatan Her-Registrasi/UKT', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1200', 'nama' => 'Pendapatan SPP', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1300', 'nama' => 'Pendapatan Praktikum', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1400', 'nama' => 'Pendapatan Wisuda', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1500', 'nama' => 'Pendapatan SPMB', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1600', 'nama' => 'Pendapatan Skripsi/TA', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '4-1700', 'nama' => 'Pendapatan Lainnya', 'kategori' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '5-1000', 'nama' => 'Beban Operasional', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
            ['kode' => '5-1100', 'nama' => 'Beban Gaji & Tunjangan', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
            ['kode' => '5-1200', 'nama' => 'Beban Utilitas', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
            ['kode' => '5-1300', 'nama' => 'Beban ATK & Perlengkapan', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
            ['kode' => '5-1400', 'nama' => 'Beban Pemeliharaan', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
            ['kode' => '5-1500', 'nama' => 'Beban Kegiatan Mahasiswa', 'kategori' => 'beban', 'saldo_normal' => 'debet'],
        ];

        $bar = $this->output->createProgressBar(count($coas));
        foreach ($coas as $coa) {
            if (!$isDryRun) {
                DB::table('sikeu_akun_keuangan')->updateOrInsert(
                    ['kode' => $coa['kode']],
                    [
                        'nama' => $coa['nama'],
                        'kategori' => $coa['kategori'],
                        'saldo_normal' => $coa['saldo_normal'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Membuat Mapping Akun Default...');
        $mappings = [
            ['lama' => '10', 'baru' => '1-1100'], // Kas Tunai
            ['lama' => '32', 'baru' => '5-1100'], // Gaji
            ['lama' => '98', 'baru' => '1-1000'], // Saldo Awal
        ];

        if (!$isDryRun) {
            foreach ($mappings as $map) {
                DB::table('_mig_akun_mapping')->updateOrInsert(
                    ['kd_perkiraan_lama' => $map['lama']],
                    [
                        'kode_akun_baru' => $map['baru'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
