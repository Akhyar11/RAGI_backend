<?php

namespace App\Console\Commands\Migration;

use App\Services\Sikeu\SaldoAwalService;
use Carbon\Carbon;
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
                    $unitKas = $db->table('sikeu_unit_kas')->where('nama_kas', $unitKasName)->first();
                    $unitKasId = $unitKas ? $unitKas->id : null;
                    if (! $unitKasId && ! $dryRun) {
                        $unitKasId = $db->table('sikeu_unit_kas')->insertGetId([
                            'nama_kas' => $unitKasName,
                            'saldo_awal' => 0,
                            'saldo_saat_ini' => 0,
                            'status' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $jenisSumber = ($item['jenis_kas'] ?? 1) == 2 ? 'pengeluaran_manual' : 'pemasukan_hibah';
                    $tanggal = $item['tanggal'] ?? now();
                    $nominal = (float) ($item['nominal'] ?? 0);

                    if (! $dryRun) {
                        try {
                            // Jangan masukkan jurnal legacy yang sudah tercakup
                            // dalam saldo awal (tanggal <= cut-off).
                            app(SaldoAwalService::class)->assertTanggalBolehDicatat(
                                Carbon::parse($tanggal)->toDateString()
                            );
                        } catch (\DomainException $e) {
                            $this->warn('[SKIP] ' . ($item['kode'] ?? $item['id'] ?? '-') . ' — ' . $e->getMessage());
                            $bar->advance();
                            continue;
                        }

                        $kasId = $db->table('sikeu_akun_keuangan')->where('kode_akun', '101.01')->value('id');
                        $akunMapping = $db->table(config('sikeu.migration.akun_mapping_table'))
                            ->where('kd_perkiraan_lama', $item['kd_perkiraan'] ?? '')
                            ->first();
                        $akunId = $akunMapping?->akun_id_baru;

                        if (! $akunId || ! $kasId) {
                            $this->warn('[SKIP] ' . ($item['kode'] ?? $item['id'] ?? '-') . ' — akun kas/legacy belum dipetakan.');
                            $bar->advance();
                            continue;
                        }

                        $jurnalId = $db->table('sikeu_jurnal_umum')->insertGetId([
                            'nomor_jurnal' => 'JRN-LEGACY-' . ($item['kode'] ?? $item['id'] ?? uniqid()),
                            'tanggal_jurnal' => $tanggal,
                            'jenis_sumber' => $jenisSumber,
                            'keterangan' => $item['keterangan'] ?? '-',
                            'status_posting' => 'posted',
                            'total_debet' => $nominal,
                            'total_kredit' => $nominal,
                            'legacy_id' => $item['id'],
                            'created_at' => $tanggal,
                            'updated_at' => $tanggal,
                        ]);

                        // Debet/kredit mengikuti arus kas: pemasukan menambah kas,
                        // pengeluaran mengurangi kas.
                        $debetAkun = $jenisSumber === 'pemasukan_hibah' ? $kasId : $akunId;
                        $kreditAkun = $jenisSumber === 'pemasukan_hibah' ? $akunId : $kasId;

                        $db->table('sikeu_detail_jurnal_umum')->insert([
                            [
                                'jurnal_id' => $jurnalId,
                                'akun_id' => $debetAkun,
                                'debet' => $nominal,
                                'kredit' => 0,
                                'keterangan' => $item['keterangan'] ?? null,
                                'created_at' => $tanggal,
                                'updated_at' => $tanggal,
                            ],
                            [
                                'jurnal_id' => $jurnalId,
                                'akun_id' => $kreditAkun,
                                'debet' => 0,
                                'kredit' => $nominal,
                                'keterangan' => $item['keterangan'] ?? null,
                                'created_at' => $tanggal,
                                'updated_at' => $tanggal,
                            ],
                        ]);
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
