<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSikeuMasterBiaya extends Command
{
    protected $signature = 'migrate:sikeu-master-biaya {--source=json} {--dry-run} {--setting-tarif}';
    protected $description = 'Migrasi master biaya dari file JSON export sikeudb modern';

    public function handle(): void
    {
        $this->info('Memulai migrasi Master Biaya...');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE AKTIF - Tidak ada data yang disimpan.');
        }

        $jsonPath = storage_path('migration_data/master_biaya_mhs.json');
        if (!File::exists($jsonPath)) {
            $this->warn("File {$jsonPath} tidak ditemukan.");
            $this->generateExportInstructions();
            return;
        }

        $data = json_decode(File::get($jsonPath), true) ?? [];
        if (empty($data)) {
            $this->warn('File JSON kosong atau tidak valid.');
            return;
        }

        DB::beginTransaction();
        try {
            $this->info('Memproses master_biaya_mhs.json ...');
            $bar = $this->output->createProgressBar(count($data));

            foreach ($data as $item) {
                if ($isDryRun) {
                    $bar->advance();
                    continue;
                }

                $tipe = 'lainnya';
                $nama = strtolower($item['nama_biaya'] ?? '');
                if (str_contains($nama, 'spp')) $tipe = 'spp';
                elseif (str_contains($nama, 'praktikum')) $tipe = 'praktikum';
                elseif (str_contains($nama, 'wisuda')) $tipe = 'wisuda';
                elseif (str_contains($nama, 'pendaftaran') || str_contains($nama, 'pmb')) $tipe = 'spmb_adm';

                $kode = substr(strtoupper(str_replace(' ', '-', $item['nama_biaya'] ?? 'BIAYA')), 0, 20);

                DB::table('sikeu_master_biaya')->updateOrInsert(
                    ['legacy_id' => $item['id']],
                    [
                        'nama' => $item['nama_biaya'],
                        'kode' => $kode,
                        'tipe' => $tipe,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $bar->advance();
            }
            $bar->finish();
            $this->newLine();

            if ($this->option('setting-tarif')) {
                $this->processSettingTarif($isDryRun);
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info('Rollback dilakukan karena Dry Run.');
            } else {
                DB::commit();
                $this->info('Migrasi Selesai.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Terjadi Kesalahan: ' . $e->getMessage());
        }
    }

    private function processSettingTarif(bool $isDryRun): void
    {
        $this->info('Memproses Setting Tarif dari master_tagihan_krs.json...');
        $jsonPath = storage_path('migration_data/master_tagihan_krs.json');
        if (!File::exists($jsonPath)) {
            $this->warn("File {$jsonPath} tidak ditemukan, melewatinya.");
            return;
        }

        $data = json_decode(File::get($jsonPath), true) ?? [];
        $bar = $this->output->createProgressBar(count($data));

        $jalurMap = [
            1 => 'Reguler',
            2 => 'Karyawan',
            3 => 'Internasional',
            4 => 'Karyawan'
        ];

        foreach ($data as $item) {
            if ($isDryRun) {
                $bar->advance();
                continue;
            }

            $prodiId = DB::table('siakad_program_studi')
                ->where('kode', $item['kode_prodi'])
                ->value('id');

            DB::table('sikeu_setting_tarif')->insert([
                'tahun_angkatan' => $item['angkatan'] ?? date('Y'),
                'program_studi_id' => $prodiId,
                'semester' => $item['smt'] ?? 1,
                'jalur_kelas' => $jalurMap[$item['jenis_daftar'] ?? 1] ?? 'Reguler',
                'nominal' => $item['jumlah'] ?? 0,
                'master_biaya_id' => null, // Placeholder or map if available
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    public function generateExportInstructions(): void
    {
        $this->info("\n--- INSTRUKSI EXPORT DATA DARI MYSQL LAMA ---");
        $this->line("Harap jalankan query berikut pada database MySQL lama untuk mendapatkan file ekspor:");
        $this->line("");
        $this->line("-- Export master_biaya_mhs");
        $this->line("SELECT id, akun_id, kode_prodi, jenis_daftar, tahun_angkatan, nama_biaya, jumlah, smt, tambahan");
        $this->line("FROM master_biaya_mhs WHERE deleted_at IS NULL");
        $this->line("INTO OUTFILE '/tmp/master_biaya_mhs.csv' FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\\n';");
        $this->line("");
        $this->line("Setelah file didapat, letakkan file CSV/JSON tersebut di `storage/migration_data/` pada project RAG Anda.");
    }
}
