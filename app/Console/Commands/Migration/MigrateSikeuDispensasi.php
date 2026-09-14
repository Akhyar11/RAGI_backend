<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class MigrateSikeuDispensasi extends Command
{
    protected $signature = 'migrate:sikeu-dispensasi {--dry-run} {--skip-existing}';
    protected $description = 'Migrate dispensasi dari master_tagihan_dispen';

    public function handle()
    {
        $this->info('Memulai Migrasi SIKEU Dispensasi...');
        $runId = $this->logRunStart('migrate:sikeu-dispensasi');

        $filePath = storage_path('migration_data/master_tagihan_dispen.json');
        if (!File::exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            $this->logRunEnd($runId, 'failed', 'File json tidak ditemukan');
            return;
        }

        $data = json_decode(File::get($filePath), true) ?? [];
        $db = DB::connection('sqlite');
        
        $dryRun = $this->option('dry-run');
        $skipExisting = $this->option('skip-existing');

        $chunks = array_chunk($data, 100);
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($chunks as $chunk) {
            $db->transaction(function () use ($chunk, $db, $dryRun, $skipExisting, &$bar) {
                foreach ($chunk as $item) {
                    if ($skipExisting && $db->table('sikeu_dispensasi_tagihan')->where('legacy_id', $item['id'])->exists()) {
                        $bar->advance();
                        continue;
                    }

                    $nim = $item['nim'] ?? $item['no_pend'] ?? null;
                    $mhsMapping = $db->table('_mig_mahasiswa_mapping')->where('nim_lama', $nim)->first();
                    $mhsId = $mhsMapping ? $mhsMapping->mahasiswa_id : null;

                    if (!$mhsId) {
                        if (!$dryRun) {
                            $db->table('_mig_unresolved')->insert([
                                'source_table' => 'master_tagihan_dispen',
                                'legacy_id' => $item['id'],
                                'failure_reason' => 'Mahasiswa not found for nim: ' . $nim,
                                'status' => 'pending_review',
                                'created_at' => now(),
                            ]);
                        }
                        $bar->advance();
                        continue;
                    }

                    $tagihan = $db->table('sikeu_tagihan_mahasiswa')
                        ->where('mahasiswa_id', $mhsId)
                        ->first();
                    $tagihanId = $tagihan ? $tagihan->id : null;

                    if (!$dryRun) {
                        $db->table('sikeu_dispensasi_tagihan')->insert([
                            'tagihan_id' => $tagihanId,
                            'tipe_dispensasi' => 'penundaan_jatuh_tempo',
                            'alasan' => 'Dispensasi dari sistem lama (migrasi data)',
                            'status' => !empty($item['ttd']) ? 'approved' : 'pending',
                            'jatuh_tempo_baru' => Carbon::parse($item['created_at'] ?? now())->addMonths(3),
                            'legacy_id' => $item['id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $bar->advance();
                }
            });
        }
        
        $bar->finish();
        $this->logRunEnd($runId, 'completed');
        $this->info("\nMigrasi Dispensasi Selesai.");
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
