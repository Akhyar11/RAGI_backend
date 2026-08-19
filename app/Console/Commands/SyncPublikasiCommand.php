<?php

namespace App\Console\Commands;

use App\Models\Simpeg\Pegawai;
use App\Services\Sippm\PublikasiSyncService;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPublikasiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sippm:sync-publikasi {--pegawai_id= : Sync specific lecturer ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi otomatis metadata & sitasi publikasi ilmiah dari SINTA & Scopus';

    /**
     * Execute the console command.
     */
    public function handle(PublikasiSyncService $syncService): int
    {
        $this->info('🚀 Memulai proses sinkronisasi publikasi SINTA & Scopus...');
        $startTime = microtime(true);

        $specificPegawaiId = $this->option('pegawai_id');

        $query = Pegawai::where('jenis_pegawai', 'dosen');

        if ($specificPegawaiId) {
            $query->where('id', $specificPegawaiId);
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('scopus_id')
                  ->orWhereNotNull('sinta_id');
            });
        }

        $dosenList = $query->get();

        if ($dosenList->isEmpty()) {
            $this->warn('⚠️ Tidak ada dosen dengan ID SINTA / Scopus yang ditemukan.');
            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalImported = 0;

        foreach ($dosenList as $dosen) {
            $this->line("📌 Memproses Dosen: {$dosen->nama_lengkap} (NIP: {$dosen->nip})");

            // 1. Sync Scopus if Scopus ID exists
            if (!empty($dosen->scopus_id)) {
                try {
                    $scopusItems = $syncService->fetchExternalData('scopus', $dosen->scopus_id);
                    foreach ($scopusItems as $item) {
                        $syncService->importExternalPublikasi($dosen, $item);
                        $totalImported++;
                    }
                    $this->info("   ✅ Scopus Sync: " . count($scopusItems) . " artikel diproses.");
                } catch (\Exception $e) {
                    $this->error("   ❌ Scopus Sync Gagal: " . $e->getMessage());
                }
            }

            // 2. Sync SINTA if SINTA ID exists
            if (!empty($dosen->sinta_id)) {
                try {
                    $sintaItems = $syncService->fetchExternalData('sinta', $dosen->sinta_id);
                    foreach ($sintaItems as $item) {
                        $syncService->importExternalPublikasi($dosen, $item);
                        $totalImported++;
                    }
                    $this->info("   ✅ SINTA Sync: " . count($sintaItems) . " artikel diproses.");
                } catch (\Exception $e) {
                    $this->error("   ❌ SINTA Sync Gagal: " . $e->getMessage());
                }
            }

            $totalProcessed++;
        }

        $executionTime = round(microtime(true) - $startTime, 2);

        $this->info("🎉 Sinkronisasi Selesai dalam {$executionTime} detik! Total Dosen: {$totalProcessed}, Artikel Diproses: {$totalImported}");

        // Record Audit Log for Scheduled Command
        AuditLogService::record(
            module: 'SIPPM',
            action: 'SCHEDULED_SYNC_PUBLIKASI',
            tableName: 'publikasi_ilmiah',
            recordId: null,
            oldValues: null,
            newValues: [
                'total_dosen_processed' => $totalProcessed,
                'total_articles_imported' => $totalImported,
                'execution_time_seconds' => $executionTime,
            ]
        );

        return self::SUCCESS;
    }
}
