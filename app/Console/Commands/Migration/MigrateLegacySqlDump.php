<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MigrateLegacySqlDump extends Command
{
    protected $signature = 'migrate:sikeu-legacy-sql {--dry-run} {--sql-file=} {--skip-existing} {--only=}';
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

        $sqlContent = File::get($sqlFile);
        
        if (!$isDryRun) {
            try {
                $this->runLogId = DB::table('sikeu_mig_run_log')->insertGetId([
                    'command_name' => 'migrate:sikeu-legacy-sql',
                    'source' => 'SQL_DUMP',
                    'status' => 'running',
                    'started_at' => now(),
                    'total_records' => 0,
                    'success_count' => 0,
                    'skipped_count' => 0,
                    'failed_count' => 0,
                    'total_nominal_migrated' => 0,
                ]);
            } catch (\Exception $e) {}
        }

        if (!$isDryRun && !Schema::hasTable('sikeu_mig_bayar_mapping')) {
            Schema::create('sikeu_mig_bayar_mapping', function ($table) {
                $table->string('kode_bayar_lama')->primary();
                $table->integer('bdaftar')->default(0);
                $table->integer('batribut')->default(0);
                $table->integer('bherreg')->default(0);
                $table->integer('bikam')->default(0);
                $table->integer('bspp')->default(0);
                $table->integer('lama')->default(0);
                $table->integer('bkp')->default(0);
                $table->integer('bskripsi')->default(0);
                $table->integer('bwisuda')->default(0);
                $table->integer('setspp')->default(0);
                $table->timestamps();
            });
        }

        DB::beginTransaction();
        try {
            $only = $this->option('only');
            if (!$only || $only === 'mahasiswa') {
                $this->migrateMahasiswa($this->parseSqlInsertsRobust($sqlContent, 'mahasiswa'));
            }
            if (!$only || $only === 'bayar') {
                $this->migrateBayarMaster($this->parseSqlInsertsRobust($sqlContent, 'bayar'));
            }
            if (!$only || $only === 'biayalain') {
                $this->migrateBiayaLain($this->parseSqlInsertsRobust($sqlContent, 'biayalain'));
            }
            if (!$only || $only === 'trbyr') {
                $this->migrateTrbyr($this->parseSqlInsertsRobust($sqlContent, 'trbyr'));
            }
            if (!$only || $only === 'trkas') {
                $this->migrateTrkas($this->parseSqlInsertsRobust($sqlContent, 'trkas'));
            }
            if (!$only || $only === 'dispensasi') {
                $this->migrateDispensasi($this->parseSqlInsertsRobust($sqlContent, 'dispensasi'));
            }
            if (!$only || $only === 'rkpbyr') {
                $this->migrateRkpbyr($this->parseSqlInsertsRobust($sqlContent, 'rkpbyr'));
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info('Rollback dilakukan karena Dry Run.');
            } else {
                DB::commit();
                $this->info('Commit transaksi berhasil.');
                if ($this->runLogId) {
                    try {
                        DB::table('sikeu_mig_run_log')->where('id', $this->runLogId)->update([
                            'status' => 'completed',
                            'finished_at' => now(),
                            'total_records' => $this->success + $this->failed + $this->skipped,
                            'success_count' => $this->success,
                            'skipped_count' => $this->skipped,
                            'failed_count' => $this->failed,
                            'total_nominal_migrated' => $this->nominalMigrated,
                        ]);
                    } catch (\Exception $e) {}
                }
            }

            $this->info("Migrasi Selesai! Success: {$this->success}, Skipped: {$this->skipped}, Failed: {$this->failed}, Nominal: " . number_format($this->nominalMigrated));
        } catch (\Exception $e) {
            DB::rollBack();
            if (!$isDryRun && $this->runLogId) {
                try {
                    DB::table('sikeu_mig_run_log')->where('id', $this->runLogId)->update([
                        'status' => 'failed',
                        'notes' => substr($e->getMessage(), 0, 500),
                        'finished_at' => now(),
                    ]);
                } catch (\Exception $e2) {}
            }
            $this->error('Terjadi Kesalahan: ' . $e->getMessage());
        }
    }

    private function parseSqlInsertsRobust(string $sql, string $tableName): array
    {
        $this->info("Parsing tabel {$tableName}...");
        $pattern = "/INSERT\s+INTO\s+`" . preg_quote($tableName, '/') . "`\s+.*?VALUES\s*(.*?);/is";
        if (!preg_match_all($pattern, $sql, $matches)) {
            $pattern = "/INSERT\s+INTO\s+" . preg_quote($tableName, '/') . "\s+.*?VALUES\s*(.*?);/is";
            preg_match_all($pattern, $sql, $matches);
        }
        if (empty($matches[1])) {
            $this->warn("Tidak ditemukan data INSERT untuk tabel {$tableName}");
            return [];
        }
        $rows = [];
        foreach ($matches[1] as $valuesString) {
            preg_match_all("/\((?:[^()]|(?R))*\)/s", $valuesString, $valueGroups);
            if (empty($valueGroups[0])) {
                preg_match_all("/\((.*?)\)/s", $valuesString, $valueGroups);
                if (!empty($valueGroups[1])) {
                    foreach ($valueGroups[1] as $valueStr) {
                        $parsed = str_getcsv($valueStr, ",", "'");
                        $rows[] = $parsed;
                    }
                    continue;
                }
            }
            if (!empty($valueGroups[0])) {
                foreach ($valueGroups[0] as $group) {
                    $inner = substr($group, 1, -1);
                    $parsed = str_getcsv($inner, ",", "'");
                    $rows[] = $parsed;
                }
            }
        }
        $this->info("Ditemukan " . count($rows) . " baris untuk tabel {$tableName}");
        return $rows;
    }

    private function migrateMahasiswa(array $rows): void
    {
        $this->info('Migrating Mahasiswa (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        foreach ($rows as $row) {
            if (count($row) < 1) { $bar->advance(); continue; }
            $nimLama = trim($row[0]);
            if ($nimLama === '') { $bar->advance(); continue; }
            if (!$this->isDryRun()) {
                try {
                    DB::table('sikeu_mig_mahasiswa_mapping')->updateOrInsert(
                        ['nim_lama' => $nimLama],
                        ['nama_lama' => trim($row[1] ?? 'Unknown'), 'kode_lama' => trim($row[6] ?? ''), 'mahasiswa_id_baru' => null, 'nim_baru' => null, 'status' => 'not_found', 'updated_at' => now()]
                    );
                } catch (\Exception $e) {}
            }
            $this->success++;
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function migrateBayarMaster(array $rows): void
    {
        $this->info('Migrating Master Bayar (' . count($rows) . ' records)...');
        $uniqueBiaya = [
            'bdaftar' => ['nama'=>'Biaya Pendaftaran', 'kode'=>'BIA-DAFTAR', 'tipe'=>'spmb_adm'],
            'batribut'=> ['nama'=>'Biaya Atribut', 'kode'=>'BIA-ATRIBUT', 'tipe'=>'lainnya'],
            'bherreg' => ['nama'=>'Her-Registrasi', 'kode'=>'BIA-HERREG', 'tipe'=>'spp'],
            'bikam'   => ['nama'=>'Biaya IKAM', 'kode'=>'BIA-IKAM', 'tipe'=>'lainnya'],
            'bspp'    => ['nama'=>'SPP', 'kode'=>'BIA-SPP', 'tipe'=>'spp'],
            'bkp'     => ['nama'=>'Biaya KP/Praktikum', 'kode'=>'BIA-KP', 'tipe'=>'praktikum'],
            'bskripsi'=> ['nama'=>'Biaya Skripsi/TA', 'kode'=>'BIA-SKRIPSI', 'tipe'=>'lainnya'],
            'bwisuda' => ['nama'=>'Biaya Wisuda', 'kode'=>'BIA-WISUDA', 'tipe'=>'wisuda'],
        ];
        if (!$this->isDryRun()) {
            foreach ($uniqueBiaya as $key => $data) {
                try {
                    DB::table('sikeu_master_biaya')->updateOrInsert(
                        ['kode' => $data['kode']],
                        ['nama' => $data['nama'], 'tipe' => $data['tipe'], 'legacy_id' => $key, 'is_active' => true, 'is_recurring' => true, 'updated_at' => now(), 'created_at' => now()]
                    );
                } catch (\Exception $e) {}
            }
            foreach ($rows as $r) {
                if (count($r) < 1) continue;
                $kodeBayar = trim($r[0]);
                if ($kodeBayar === '') continue;
                try {
                    DB::table('sikeu_mig_bayar_mapping')->updateOrInsert(['kode_bayar_lama' => $kodeBayar], [
                        'kode_bayar_lama' => $kodeBayar,
                        'bdaftar' => intval($r[1]??0), 'batribut' => intval($r[2]??0), 'bherreg' => intval($r[3]??0),
                        'bikam' => intval($r[4]??0), 'bspp' => intval($r[5]??0), 'lama' => intval($r[6]??0),
                        'bkp' => intval($r[7]??0), 'bskripsi' => intval($r[8]??0), 'bwisuda' => intval($r[9]??0),
                        'setspp' => intval($r[10]??0),
                        'updated_at' => now(), 'created_at' => now()
                    ]);
                } catch (\Exception $e) {}
            }
        }
        $this->success += count($rows);
        $this->info('Master Bayar diproses.');
    }

    private function migrateBiayaLain(array $rows): void
    {
        $this->info('Migrating Biaya Lain (' . count($rows) . ' records)...');
        if (!$this->isDryRun()) {
            foreach ($rows as $r) {
                if (count($r) < 3) continue;
                $kode = trim($r[0]); $nama = trim($r[1]); $jumlah = floatval($r[2] ?? 0);
                if ($kode === '' || $nama === '') continue;
                $kodeRag = 'BIA-LAIN-' . str_pad($kode, 3, '0', STR_PAD_LEFT);
                try {
                    DB::table('sikeu_master_biaya')->updateOrInsert(['kode' => $kodeRag], ['nama' => $nama, 'tipe' => 'lainnya', 'legacy_id' => $kode, 'is_active' => true, 'is_recurring' => false, 'updated_at' => now(), 'created_at' => now()]);
                } catch (\Exception $e) {}
            }
        }
        $this->success += count($rows);
    }

    private function migrateTrbyr(array $rows): void
    {
        $this->info('Migrating Transaksi Bayar (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        foreach ($rows as $row) {
            if (count($row) < 7) { $bar->advance(); continue; }
            $noTran = trim($row[0]); $tglTran = trim($row[1]); $nim = trim($row[7] ?? $row[2]); $jmlBayar = floatval($row[6] ?? 0);
            if ($jmlBayar == 0) { $this->skipped++; $bar->advance(); continue; }
            if (!$this->isDryRun()) {
                try {
                    if (DB::table('sikeu_pembayaran')->where('kode_transaksi', $noTran)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    $mhsMap = DB::table('sikeu_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
                    $tagihanId = null;
                    if ($mhsMap && $mhsMap->mahasiswa_id_baru) {
                        $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->where('mahasiswa_id', $mhsMap->mahasiswa_id_baru)->orderBy('id','desc')->value('id');
                    }
                    if (!$tagihanId) {
                        $this->recordUnresolved('trbyr', $noTran, $row, 'tagihan_not_found');
                        $this->failed++; $bar->advance(); continue;
                    }
                    $tgl = $this->parseDate($tglTran);
                    DB::table('sikeu_pembayaran')->insert([
                        'tagihan_id' => $tagihanId, 'kode_transaksi' => $noTran, 'jumlah_bayar' => $jmlBayar,
                        'waktu_bayar' => $tgl ?: now(), 'channel_bayar' => 'LOKET_TUNAI', 'status' => 'success',
                        'legacy_id' => $noTran, 'legacy_source' => 'LEGACY_STMIK_2016', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->nominalMigrated += $jmlBayar;
                    $this->success++;
                } catch (\Exception $e) {
                    $this->recordUnresolved('trbyr', $noTran, $row, substr($e->getMessage(),0,200));
                    $this->failed++;
                }
            } else { $this->success++; }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function migrateTrkas(array $rows): void
    {
        $this->info('Migrating Kas (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        $unitKasId = null;
        if (!$this->isDryRun()) {
            try {
                $unitKasId = DB::table('sikeu_unit_kas')->value('id');
                if (!$unitKasId) {
                    $unitKasId = DB::table('sikeu_unit_kas')->insertGetId([
                        'nama_kas' => 'Kas Utama STMIK', 'saldo_awal' => 0, 'saldo_saat_ini' => 0, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {}
        }
        foreach ($rows as $row) {
            if (count($row) < 8) { $bar->advance(); continue; }
            $noTran = trim($row[0]); $tglTran = trim($row[1]); $jenis = intval(trim($row[5])); $jumlah = floatval($row[6] ?? 0); $kdPerkiraan = trim($row[8] ?? '');
            if ($jumlah == 0) { $this->skipped++; $bar->advance(); continue; }
            if (!$this->isDryRun()) {
                try {
                    $akunMapping = DB::table('sikeu_mig_akun_mapping')->where('kd_perkiraan_lama', $kdPerkiraan)->first();
                    if (!$akunMapping) {
                        $this->recordUnresolved('trkas', $noTran, $row, 'akun_not_mapped');
                        $this->failed++; $bar->advance(); continue;
                    }
                    $tgl = $this->parseDate($tglTran);
                    DB::table('sikeu_jurnal_umum')->insert([
                        'unit_kas_id' => $unitKasId, 'kode_transaksi' => $noTran, 'tanggal_jurnal' => $tgl ?: now(),
                        'jenis_sumber' => $jenis == 1 ? 'pemasukan' : 'pengeluaran',
                        'total_debet' => $jenis == 1 ? $jumlah : 0, 'total_kredit' => $jenis == 2 ? $jumlah : 0,
                        'keterangan' => 'Migrasi dari trkas lama: ' . substr(trim($row[4] ?? ''),0,200),
                        'legacy_id' => $noTran, 'legacy_source' => 'LEGACY_STMIK_2016', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->success++;
                } catch (\Exception $e) {
                    $this->recordUnresolved('trkas', $noTran, $row, substr($e->getMessage(),0,200));
                    $this->failed++;
                }
            } else { $this->success++; }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function migrateDispensasi(array $rows): void
    {
        $this->info('Migrating Dispensasi (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        foreach ($rows as $row) {
            if (count($row) < 4) { $bar->advance(); continue; }
            $nim = trim($row[2]); $total = floatval($row[12] ?? 0); $statusDispen = strtolower(trim($row[14] ?? 'pending')); $tglBayar = trim($row[15] ?? '');
            if (!$this->isDryRun()) {
                try {
                    $mhs = DB::table('sikeu_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
                    if (!$mhs || !$mhs->mahasiswa_id_baru) {
                        $this->recordUnresolved('dispensasi', $nim, $row, 'nim_not_mapped');
                        $this->failed++; $bar->advance(); continue;
                    }
                    $status = in_array($statusDispen, ['lunas','approved','disetujui']) ? 'approved' : (in_array($statusDispen,['ditolak','tolak']) ? 'rejected' : 'pending');
                    DB::table('sikeu_dispensasi_tagihan')->insert([
                        'mahasiswa_id' => $mhs->mahasiswa_id_baru, 'tagihan_id' => 1,
                        'tipe_dispensasi' => 'penundaan_jatuh_tempo', 'alasan' => 'Migrasi dispensasi legacy',
                        'status' => $status, 'jatuh_tempo_baru' => $this->parseDate($tglBayar) ?: now()->addMonth(),
                        'nominal_per_cicilan' => $total, 'jumlah_cicilan' => 1,
                        'legacy_id' => $nim . '|' . ($row[0]??'') . '|' . ($row[1]??''),
                        'legacy_source' => 'LEGACY_STMIK_2016', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->success++;
                } catch (\Exception $e) {
                    $this->recordUnresolved('dispensasi', $nim, $row, substr($e->getMessage(),0,200));
                    $this->failed++;
                }
            } else { $this->success++; }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function migrateRkpbyr(array $rows): void
    {
        $this->info('Migrating Rekap Tagihan RKPBYR (' . count($rows) . ' records)...');
        $bar = $this->output->createProgressBar(count($rows));
        foreach ($rows as $row) {
            if (count($row) < 15) { $this->skipped++; $bar->advance(); continue; }
            $nim = trim($row[1] ?: $row[0]); $kodeBayar = trim($row[3] ?? ''); $potongan = floatval($row[11] ?? 0);
            if ($nim === '' || $kodeBayar === '') { $this->skipped++; $bar->advance(); continue; }
            if (!$this->isDryRun()) {
                try {
                    $mhs = DB::table('sikeu_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
                    if (!$mhs || !$mhs->mahasiswa_id_baru) {
                        $this->recordUnresolved('rkpbyr', $nim, $row, 'nim_not_mapped');
                        $this->failed++; $bar->advance(); continue;
                    }
                    $legacyKey = $nim . '|' . $kodeBayar;
                    if (DB::table('sikeu_tagihan_mahasiswa')->where('legacy_id', $legacyKey)->exists()) { $this->skipped++; $bar->advance(); continue; }
                    $total = 0;
                    for ($i = 13; $i < count($row); $i++) { if (is_numeric($row[$i])) $total += floatval($row[$i]); }
                    if ($total < $potongan) $total = $potongan;
                    $tagihanId = DB::table('sikeu_tagihan_mahasiswa')->insertGetId([
                        'mahasiswa_id' => $mhs->mahasiswa_id_baru,
                        'nomor_tagihan' => 'TRX-LEG-' . substr(md5($legacyKey . microtime()),0,12),
                        'total_tagihan' => max($total - $potongan, 0),
                        'total_potongan' => $potongan,
                        'total_denda' => 0,
                        'total_bayar' => 0,
                        'status' => 'belum_bayar',
                        'requires_approval' => false,
                        'status_approval' => 'approved',
                        'source_system' => 'LEGACY_MIGRASI',
                        'legacy_id' => $legacyKey,
                        'legacy_source' => 'LEGACY_STMIK_2016',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->success++;
                    $this->nominalMigrated += max($total - $potongan, 0);
                } catch (\Exception $e) {
                    $this->recordUnresolved('rkpbyr', $nim, $row, substr($e->getMessage(),0,200));
                    $this->failed++;
                }
            } else { $this->success++; }
            $bar->advance();
        }
        $bar->finish(); $this->newLine();
    }

    private function parseDate($d): ?string
    {
        if (!$d) return null;
        $d = trim($d);
        if ($d === '' || $d === '0000-00-00') return null;
        try { return Carbon::parse($d)->format('Y-m-d H:i:s'); } catch (\Exception $e) { return null; }
    }

    private function recordUnresolved(string $table, string $key, array $data, string $reason): void
    {
        if ($this->isDryRun()) return;
        try {
            DB::table('sikeu_mig_unresolved')->insert([
                'source_table' => $table,
                'legacy_key' => substr($key,0,191),
                'raw_data' => json_encode($data),
                'failure_reason' => substr($reason,0,255),
                'status' => 'pending_review',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {}
    }

    private function isDryRun(): bool { return (bool)$this->option('dry-run'); }
}
