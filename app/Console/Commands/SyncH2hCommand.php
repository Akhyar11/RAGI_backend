<?php

namespace App\Console\Commands;

use App\Services\Sikeu\BsnH2hService;
use Illuminate\Console\Command;

class SyncH2hCommand extends Command
{
    protected $signature = 'sikeu:sync-h2h {--limit=100}';
    protected $description = 'Sinkron pembayaran H2H BTN Syariah yang terbayar di bridge ke tagihan RAG';

    public function handle(): int
    {
        $hasil = BsnH2hService::sinkronTerbayar((int) $this->option('limit'));

        if (isset($hasil['error'])) {
            $this->error($hasil['error']);
            return self::FAILURE;
        }

        $this->info("Diproses: {$hasil['diproses']}, dilewati: {$hasil['dilewati']}, gagal: {$hasil['gagal']}");
        foreach ($hasil['detail'] ?? [] as $d) {
            $this->line("- {$d}");
        }

        return $hasil['gagal'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
