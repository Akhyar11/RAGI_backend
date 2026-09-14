<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateLegacySqlDump extends Command
{
    protected $signature = 'migrate:sikeu-legacy-sql {--dry-run} {--sql-file=} {--skip-existing}';
    protected $description = 'Migrasi data SIKEU dari SQL dump sistem lama (STMIK 2006-2016)';
    
    private int $success = 0, $skipped = 0, $failed = 0;
    private float $nominalMigrated = 0;
    private int $runLogId = 0;
    
    public function handle(): void
    {
        $this->info('Memulai migrasi SIKEU dari Legacy SQL Dump...');
        
        $sqlFile = $this->option('sql-file') ?: '/Users/it/Project/indonusa/SIK_indonusa/localhost.sql';
        
        if (!File::exists($sqlFile)) {
            $this->error("File SQL tidak ditemukan di: {$sqlFile}");
            return;
        }

        $isDryRun = $this->isDryRun();
        if ($isDryRun) {
            $this->warn('DRY RUN MODE AKTIF - Tidak ada data yang akan disimpan ke database.');
        }

        $this->info("Membaca file SQL: {$sqlFile}");
        $sqlContent = File::get($sqlFile);
        
        // Log awal run
        if (!$isDryRun) {
            $this->runLogId = DB::table('_mig_run_log')->insertGetId([
                'command' => $this->signature,
                'status' => 'running',
                'started_at' => now(),
            ]);
        }

        DB::beginTransaction();
        try {
            // Ekstrak dan proses setiap tabel
            $this->migrateMahasiswa($this->parseSqlInserts($sqlContent, 'mahasiswa'));
            $this->migrateBayarMaster($this->parseSqlInserts($sqlContent, 'bayar'));
            $this->migrateTrbyr($this->parseSqlInserts($sqlContent, 'trbyr'));
            $this->migrateTrkas($this->parseSqlInserts($sqlContent, 'trkas'));
            $this->migrateDispensasi($this->parseSqlInserts($sqlContent, 'dispensasi'));

            if ($isDryRun) {
                DB::rollBack();
                $this->info('Rollback dilakukan karena Dry Run.');
            } else {
                DB::commit();
                $this->info('Commit transaksi berhasil.');
                
                DB::table('_mig_run_log')->where('id', $this->runLogId)->update([
                    'status' => 'success',
                    'finished_at' => now(),
                    'records_processed' => $this->success + $this->failed + $this->skipped
                ]);
            }

            $this->info("Migrasi Selesai! Success: {$this->success}, Skipped: {$this->skipped}, Failed: {$this->failed}");
        } catch (\Exception $e) {
            DB::rollBack();
            if (!$isDryRun && $this->runLogId) {
                DB::table('_mig_run_log')->where('id', $this->runLogId)->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }
            $this->error('Terjadi Kesalahan: ' . $e->getMessage());
        }
    }

    private function parseSqlInserts(string $sql, string $tableName): array
    {
        $this->info("Parsing tabel {$tableName}...");
        // Match standard mysqldump multi-value inserts
        $pattern = "/INSERT INTO `?{$tableName}`? VALUES\s*(.*?);/is";
        if (preg_match_all($pattern, $sql, $matches)) {
            $rows = [];
            foreach ($matches[1] as $valuesString) {
                // Split multiple (value),(value) groups
                preg_match_all("/\((.*?)\)/s", $valuesString, $valueGroups);
                foreach ($valueGroups[1] as $valueStr) {
                    $rows[] = str_getcsv($valueStr, ",", "'");
                }
            }
            return $rows;
        }
        return [];
    }

    private function migrateMahasiswa(array $rows): void
    {
        $this->info('Migrating Mahasiswa (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        
        foreach ($rows as $row) {
            if (count($row) < 2) {
                $bar->advance();
                continue;
            }
            $nimLama = trim($row[0]);
            
            if (!$this->isDryRun()) {
                DB::table('_mig_mahasiswa_mapping')->updateOrInsert(
                    ['nim_lama' => $nimLama],
                    [
                        'nama' => trim($row[1] ?? 'Unknown'),
                        'kode' => trim($row[2] ?? ''),
                        'status' => 'not_found',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            $this->success++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function migrateBayarMaster(array $rows): void
    {
        $this->info('Migrating Master Bayar (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        
        $uniqueBiaya = [
            'bdaftar' => ['nama'=>'Biaya Pendaftaran', 'kode'=>'BIA-DAFTAR', 'tipe'=>'spmb_adm'],
            'bherreg' => ['nama'=>'Her-Registrasi / UKT', 'kode'=>'BIA-HERREG', 'tipe'=>'spp'],
            'bspp'    => ['nama'=>'SPP Bulanan', 'kode'=>'BIA-SPP', 'tipe'=>'spp'],
            'bkp'     => ['nama'=>'Kemahasiswaan & Praktikum', 'kode'=>'BIA-KP', 'tipe'=>'praktikum'],
            'bskripsi'=> ['nama'=>'Biaya Skripsi / TA', 'kode'=>'BIA-SKRIPSI', 'tipe'=>'lainnya'],
            'bwisuda' => ['nama'=>'Biaya Wisuda', 'kode'=>'BIA-WISUDA', 'tipe'=>'wisuda'],
            'bikam'   => ['nama'=>'Biaya Ikatan Alumni/IKAM', 'kode'=>'BIA-IKAM', 'tipe'=>'lainnya'],
            'batribut'=> ['nama'=>'Biaya Atribut', 'kode'=>'BIA-ATRIBUT', 'tipe'=>'lainnya'],
        ];

        foreach ($uniqueBiaya as $key => $data) {
            if (!$this->isDryRun()) {
                DB::table('sikeu_master_biaya')->updateOrInsert(
                    ['kode' => $data['kode']],
                    [
                        'nama' => $data['nama'],
                        'tipe' => $data['tipe'],
                        'legacy_id' => $key,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
        $bar->advance(count($rows)); // Simulating progress for bayar parsing
        $bar->finish();
        $this->newLine();
    }

    private function migrateTrbyr(array $rows): void
    {
        $this->info('Migrating Transaksi Bayar (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        
        foreach ($rows as $row) {
            if (count($row) < 5) continue;
            
            $nim = trim($row[0]);
            $noTran = trim($row[1]);
            $jmlBayar = (float) trim($row[2] ?? 0);
            $tglTran = trim($row[3] ?? now()->toDateString());
            $jenis = trim($row[4] ?? 'bspp');

            if ($this->isDryRun()) {
                $bar->advance();
                continue;
            }

            $mhs = DB::table('_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
            if (!$mhs) {
                $this->recordUnresolved('trbyr', $noTran, $row, 'nim_not_mapped');
                $this->failed++;
                $bar->advance();
                continue;
            }

            $masterBiaya = DB::table('sikeu_master_biaya')->where('legacy_id', $jenis)->first();
            if (!$masterBiaya) {
                $this->recordUnresolved('trbyr', $noTran, $row, 'master_biaya_not_found');
                $this->failed++;
                $bar->advance();
                continue;
            }

            // Create tagihan
            $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->insertGetId([
                'mahasiswa_id' => $mhs->id,
                'nominal_total' => $jmlBayar,
                'status' => 'lunas',
                'waktu_terbit' => $tglTran,
                'legacy_source' => 'LEGACY_STMIK_2016',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create pembayaran
            DB::table('sikeu_pembayaran')->insert([
                'tagihan_id' => $tagihanId,
                'kode_transaksi' => $noTran,
                'jumlah_bayar' => $jmlBayar,
                'waktu_bayar' => $tglTran,
                'channel_bayar' => 'LOKET_TUNAI',
                'status' => 'success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->success++;
            $this->nominalMigrated += $jmlBayar;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function migrateTrkas(array $rows): void
    {
        $this->info('Migrating Kas (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));

        $unitKasId = DB::table('sikeu_unit_kas')->value('id') ?? DB::table('sikeu_unit_kas')->insertGetId([
            'nama' => 'Kas Utama STMIK',
            'kode' => 'KAS-STMIK',
            'saldo_sekarang' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        foreach ($rows as $row) {
            if (count($row) < 5) continue;
            
            $noTran = trim($row[0]);
            $tglTran = trim($row[1] ?? now()->toDateString());
            $jenis = trim($row[2]); // 1=masuk, 2=keluar
            $jumlah = (float) trim($row[3] ?? 0);
            $kdPerkiraan = trim($row[4]);

            if ($this->isDryRun()) {
                $bar->advance();
                continue;
            }

            $akunMapping = DB::table('_mig_akun_mapping')->where('kd_perkiraan_lama', $kdPerkiraan)->first();
            if (!$akunMapping) {
                $this->recordUnresolved('trkas', $noTran, $row, 'akun_not_mapped');
                $this->failed++;
                $bar->advance();
                continue;
            }

            DB::table('sikeu_jurnal_umum')->insert([
                'unit_kas_id' => $unitKasId,
                'kode_transaksi' => $noTran,
                'tanggal_jurnal' => $tglTran,
                'jenis_sumber' => $jenis == '1' ? 'pemasukan' : 'pengeluaran',
                'total_debet' => $jenis == '1' ? $jumlah : 0,
                'total_kredit' => $jenis == '2' ? $jumlah : 0,
                'keterangan' => 'Migrasi dari trkas lama',
                'legacy_source' => 'LEGACY_STMIK_2016',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->success++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function migrateDispensasi(array $rows): void
    {
        $this->info('Migrating Dispensasi (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        
        foreach ($rows as $row) {
            if (count($row) < 5) continue;
            
            $nim = trim($row[0]);
            $semester = trim($row[1]);
            $thAkademik = trim($row[2]);
            $total = (float) trim($row[3] ?? 0);
            $statusDispen = strtolower(trim($row[4] ?? 'pending'));
            $tglBayar = trim($row[5] ?? now()->addMonth()->toDateString());

            if ($this->isDryRun()) {
                $bar->advance();
                continue;
            }

            $mhs = DB::table('_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
            if (!$mhs) {
                $this->recordUnresolved('dispensasi', $nim.'-'.$semester, $row, 'nim_not_mapped');
                $this->failed++;
                $bar->advance();
                continue;
            }

            $status = ($statusDispen === 'lunas' || $statusDispen === 'approved') ? 'approved' : 'pending';

            DB::table('sikeu_dispensasi_tagihan')->insert([
                'mahasiswa_id' => $mhs->id,
                'periode' => $thAkademik . $semester,
                'nominal_tagihan' => $total,
                'status' => $status,
                'jatuh_tempo_baru' => $tglBayar,
                'alasan' => 'Dispensasi Migrasi',
                'legacy_source' => 'LEGACY_STMIK_2016',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->success++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function recordUnresolved(string $table, string $key, array $data, string $reason): void
    {
        if ($this->isDryRun()) return;
        
        DB::table('_mig_unresolved')->insert([
            'source_table' => $table,
            'source_key' => $key,
            'raw_data' => json_encode($data),
            'failure_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function isDryRun(): bool
    {
        return $this->option('dry-run');
    }
}
