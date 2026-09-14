<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSikeuTagihan extends Command
{
    protected $signature = 'migrate:sikeu-tagihan {--dry-run} {--skip-existing} {--source=json}';
    protected $description = 'Migrate transaksi pembayaran dari sistem Sequelize modern ke sikeu_tagihan_mahasiswa';

    public function handle()
    {
        $this->info('Memulai Migrasi SIKEU Tagihan & Pembayaran...');
        $runId = $this->logRunStart('migrate:sikeu-tagihan');

        $filePath = storage_path('migration_data/transaksi_bayar.json');
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

        $migratedTotal = 0;
        $unresolvedCount = 0;

        foreach ($chunks as $chunk) {
            $db->transaction(function () use ($chunk, $db, $skipExisting, $dryRun, &$bar, &$migratedTotal, &$unresolvedCount) {
                foreach ($chunk as $item) {
                    if ($skipExisting && $db->table('sikeu_tagihan_mahasiswa')->where('legacy_id', $item['id'])->exists()) {
                        $bar->advance();
                        continue;
                    }

                    // Cari mahasiswa mapping
                    $mhsMapping = $db->table('_mig_mahasiswa_mapping')->where('nim_lama', $item['no_pend'])->first();
                    $mhsId = $mhsMapping ? $mhsMapping->mahasiswa_id : null;

                    if (!$mhsId) {
                        $siakadMhs = $db->table('siakad_mahasiswa')->where('nim', $item['no_pend'])->first();
                        $mhsId = $siakadMhs ? $siakadMhs->id : null;
                    }

                    if (!$mhsId) {
                        if (!$dryRun) {
                            $db->table('_mig_unresolved')->insert([
                                'source_table' => 'transaksi_bayar',
                                'legacy_id' => $item['id'],
                                'failure_reason' => 'Mahasiswa not found for no_pend: ' . $item['no_pend'],
                                'status' => 'pending_review',
                                'created_at' => now(),
                            ]);
                        }
                        $unresolvedCount++;
                        $bar->advance();
                        continue;
                    }

                    // Cari User
                    $user = $db->table('users')->where('username', $item['username'] ?? '')->first();
                    $userId = $user ? $user->id : null;

                    // Cari Unit Kas
                    $unitKas = $db->table('sikeu_unit_kas')->where('nama', $item['divisi'] ?? '')->first();
                    $unitKasId = $unitKas ? $unitKas->id : null;

                    $status = (isset($item['total_bayar']) && $item['total_bayar'] >= $item['total']) ? 'lunas' : 'sebagian';
                    $tanggal = $item['tanggal'] ?? now();

                    if (!$dryRun) {
                        $tagihanId = $db->table('sikeu_tagihan_mahasiswa')->insertGetId([
                            'mahasiswa_id' => $mhsId,
                            'nomor_tagihan' => 'TGH-LEGACY-' . $item['kode'],
                            'total_tagihan' => $item['total'] ?? 0,
                            'status' => $status,
                            'jatuh_tempo' => $tanggal,
                            'legacy_id' => $item['id'],
                            'legacy_source' => 'SEQUELIZE_MODERN',
                            'created_at' => $tanggal,
                            'updated_at' => $tanggal,
                        ]);

                        $channel = 'VA_BANK';
                        if (($item['metode_bayar'] ?? '') === 'TUNAI') $channel = 'LOKET_TUNAI';
                        elseif (($item['metode_bayar'] ?? '') === 'TRANSFER') $channel = 'LOKET_TRANSFER';

                        $db->table('sikeu_pembayaran')->insert([
                            'tagihan_id' => $tagihanId,
                            'kode_transaksi' => 'TRX-LEGACY-' . $item['kode'],
                            'jumlah_bayar' => $item['total'] ?? 0,
                            'waktu_bayar' => $tanggal,
                            'channel_bayar' => $channel,
                            'status' => 'success',
                            'user_id' => $userId,
                            'unit_kas_id' => $unitKasId,
                            'legacy_id' => $item['id'],
                            'legacy_source' => 'SEQUELIZE_MODERN',
                            'created_at' => $tanggal,
                            'updated_at' => $tanggal,
                        ]);
                    }
                    $migratedTotal += ($item['total'] ?? 0);
                    $bar->advance();
                }
            });
        }
        
        $bar->finish();
        
        $this->info("\nMemproses transaksi_bayar_details...");
        $detailsPath = storage_path('migration_data/transaksi_bayar_details.json');
        if (File::exists($detailsPath)) {
            $details = json_decode(File::get($detailsPath), true) ?? [];
            $detailBar = $this->output->createProgressBar(count($details));
            $detailBar->start();
            
            $detailChunks = array_chunk($details, 100);
            foreach ($detailChunks as $chunk) {
                $db->transaction(function () use ($chunk, $db, $dryRun, &$detailBar) {
                    foreach ($chunk as $dt) {
                        if (!$dryRun) {
                            $tagihan = $db->table('sikeu_tagihan_mahasiswa')->where('legacy_id', $dt['transaksi_bayar_id'])->first();
                            if ($tagihan) {
                                // Dummy insert for detail tagihan
                                $db->table('sikeu_detail_tagihan')->insert([
                                    'tagihan_id' => $tagihan->id,
                                    'nama_biaya' => $dt['jenis_biaya'] ?? 'Biaya Lainnya',
                                    'nominal' => $dt['nominal'] ?? 0,
                                    'potongan' => $dt['potongan'] ?? 0,
                                    'nominal_bersih' => ($dt['nominal'] ?? 0) - ($dt['potongan'] ?? 0),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                        $detailBar->advance();
                    }
                });
            }
            $detailBar->finish();
        }

        $this->logRunEnd($runId, 'completed', "Migrated total nominal: {$migratedTotal}. Unresolved: {$unresolvedCount}");
        $this->info("\nMigrasi Selesai.");
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
