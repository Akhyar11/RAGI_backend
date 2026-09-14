<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSikeuKas extends Command
{
    protected $signature = 'migrate:sikeu-kas {--dry-run} {--skip-existing}';
    protected $description = 'Migrate transaksi kas';

    public function handle()
    {
        $this->info('Memulai Migrasi SIKEU Kas...');
        $runId = $this->logRunStart('migrate:sikeu-kas');

        $filePath = storage_path('migration_data/transaksi_kas.json');
        if (!File::exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            $this->logRunEnd($runId, 'failed', 'File json tidak ditemukan');
            return;
        }

        $data = json_decode(File::get($filePath), true) ?? [];
        $db = DB::connection('sqlite');
        
        $skipExisting = $this->option('skip-existing');
        $dryRun = $this->option('dry-run');

        $chunks = array_chunk($data, 100);
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($chunks as $chunk) {
            $db->transaction(function () use ($chunk, $db, $skipExisting, $dryRun, &$bar) {
                foreach ($chunk as $item) {
                    if ($skipExisting && $db->table('sikeu_jurnal_umum')->where('legacy_id', $item['id'])->exists()) {
                        $bar->advance();
                        continue;
                    }

                    // Cari atau buat Unit Kas
                    $unitKasName = $item['divisi'] ?? 'Default';
                    $unitKas = $db->table('sikeu_unit_kas')->where('nama', $unitKasName)->first();
                    $unitKasId = $unitKas ? $unitKas->id : null;
                    if (!$unitKasId && !$dryRun) {
                        $unitKasId = $db->table('sikeu_unit_kas')->insertGetId([
                            'nama' => $unitKasName,
                            'created_at' => now(),
                        ]);
                    }

                    $jenisSumber = ($item['jenis_kas'] ?? 1) == 2 ? 'pengeluaran_manual' : 'pemasukan_hibah';
                    $tanggal = $item['tanggal'] ?? now();

                    if (!$dryRun) {
                        $jurnalId = $db->table('sikeu_jurnal_umum')->insertGetId([
                            'nomor_jurnal' => 'JU-LEGACY-' . ($item['kode'] ?? uniqid()),
                            'tanggal_jurnal' => $tanggal,
                            'jenis_sumber' => $jenisSumber,
                            'keterangan' => $item['keterangan'] ?? '-',
                            'unit_kas_id' => $unitKasId,
                            'legacy_id' => $item['id'],
                            'created_at' => $tanggal,
                            'updated_at' => $tanggal,
                        ]);

                        // dummy balanced journal entry mapping based on _mig_akun_mapping
                        $akunMapping = $db->table('_mig_akun_mapping')->where('kd_perkiraan', $item['kd_perkiraan'] ?? '')->first();
                        $akunId = $akunMapping ? $akunMapping->akun_id : 1; 

                        if ($jenisSumber === 'pemasukan_hibah') {
                            $db->table('sikeu_detail_jurnal_umum')->insert([
                                ['jurnal_id' => $jurnalId, 'akun_id' => 1, 'posisi' => 'debet', 'nominal' => $item['nominal'] ?? 0, 'created_at' => $tanggal],
                                ['jurnal_id' => $jurnalId, 'akun_id' => $akunId, 'posisi' => 'kredit', 'nominal' => $item['nominal'] ?? 0, 'created_at' => $tanggal],
                            ]);
                        } else {
                            $db->table('sikeu_detail_jurnal_umum')->insert([
                                ['jurnal_id' => $jurnalId, 'akun_id' => $akunId, 'posisi' => 'debet', 'nominal' => $item['nominal'] ?? 0, 'created_at' => $tanggal],
                                ['jurnal_id' => $jurnalId, 'akun_id' => 1, 'posisi' => 'kredit', 'nominal' => $item['nominal'] ?? 0, 'created_at' => $tanggal],
                            ]);
                        }
                    }
                    $bar->advance();
                }
            });
        }
        
        $bar->finish();
        $this->logRunEnd($runId, 'completed');
        $this->info("\nMigrasi Kas Selesai.");
    }

    private function logRunStart($command)
    {
        return DB::connection('sqlite')->table('_mig_run_log')->insertGetId([
            'command_name' => $command,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function logRunEnd($runId, $status, $notes = null)
    {
        DB::connection('sqlite')->table('_mig_run_log')->where('id', $runId)->update([
            'status' => $status,
            'ended_at' => now(),
            'notes' => $notes,
            'updated_at' => now(),
        ]);
    }
}
