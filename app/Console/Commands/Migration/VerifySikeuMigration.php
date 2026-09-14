<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class VerifySikeuMigration extends Command
{
    protected $signature = 'migrate:sikeu-verify {--report-file=}';
    protected $description = 'Laporan rekonsiliasi pasca migrasi SIKEU';

    public function handle()
    {
        $db = DB::connection('sqlite');
        $this->info('Memulai Verifikasi & Rekonsiliasi Migrasi SIKEU...');

        // 1. Summary Umum
        $summary = [
            ['Entitas' => 'Tagihan', 'Total' => $db->table('sikeu_tagihan_mahasiswa')->count()],
            ['Entitas' => 'Pembayaran', 'Total' => $db->table('sikeu_pembayaran')->count()],
            ['Entitas' => 'Jurnal Kas', 'Total' => $db->table('sikeu_jurnal_umum')->count()],
            ['Entitas' => 'Dispensasi', 'Total' => $db->table('sikeu_dispensasi_tagihan')->count()],
        ];
        $this->table(['Entitas', 'Total Berhasil Migrasi'], $summary);

        // 2. Unresolved Records
        $unresolved = $db->table('_mig_unresolved')
            ->select('source_table', 'failure_reason', DB::raw('count(*) as total'))
            ->groupBy('source_table', 'failure_reason')
            ->get()->map(function ($item) {
                return (array)$item;
            })->toArray();
        if (count($unresolved) > 0) {
            $this->warn("\nUnresolved Records:");
            $this->table(['Source Table', 'Failure Reason', 'Total'], $unresolved);
        }

        // 3. Rekonsiliasi Nominal
        $totalMigratedNominal = $db->table('sikeu_pembayaran')->whereNotNull('legacy_source')->sum('jumlah_bayar');
        $this->info("\nRekonsiliasi Nominal:");
        $this->line("Total Nominal Pembayaran (Legacy): Rp " . number_format($totalMigratedNominal, 0, ',', '.'));

        // 4. Run History
        $history = $db->table('_mig_run_log')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()->map(function ($item) {
                return ['ID' => $item->id, 'Command' => $item->command_name, 'Status' => $item->status, 'Started' => $item->started_at];
            })->toArray();
        $this->info("\nRun History (Last 5):");
        $this->table(['ID', 'Command', 'Status', 'Started'], $history);

        // 5. Unresolved instructions
        $totalUnresolved = $db->table('_mig_unresolved')->where('status', 'pending_review')->count();
        if ($totalUnresolved > 0) {
            $this->warn("\nData yang tidak termigrasi otomatis:");
            $this->line("- Total: {$totalUnresolved} record");
            $this->line("- Cara resolve: masuk ke table _mig_unresolved, ubah status ke resolved_manual dan isi notes");
        }

        if ($reportFile = $this->option('report-file')) {
            $content = "# Laporan Rekonsiliasi Migrasi SIKEU\n\n";
            $content .= "## Summary\n\n";
            foreach ($summary as $row) {
                $content .= "- {$row['Entitas']}: {$row['Total']}\n";
            }
            $content .= "\n## Unresolved Records\n\n";
            foreach ($unresolved as $row) {
                $content .= "- {$row['source_table']} - {$row['failure_reason']}: {$row['total']}\n";
            }
            $content .= "\n## Total Nominal: Rp " . number_format($totalMigratedNominal, 0, ',', '.') . "\n";

            File::put($reportFile, $content);
            $this->info("\nLaporan disimpan ke: {$reportFile}");
        }
    }
}
