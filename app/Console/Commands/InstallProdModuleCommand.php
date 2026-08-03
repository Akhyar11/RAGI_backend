<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallProdModuleCommand extends Command
{
    protected $signature = 'module:install-prod {modul=all}';
    protected $description = 'Instalasi struktur database & master data murni PRODUCTION (tanpa data dummy/percobaan)';

    public function handle()
    {
        $modul = strtolower($this->argument('modul'));

        $this->info("=================================================");
        $this->info("⚡ Memulai Instalasi PRODUCTION: [" . strtoupper($modul) . "]");
        $this->info("=================================================");

        // Run Base Install (Only migrations & master seeders, no dummy seeders)
        $this->call(InstallModuleCommand::class, ['modul' => $modul]);

        $this->newLine();
        $this->info("✅ Instalasi PRODUCTION [" . strtoupper($modul) . "] Berhasil! Database siap dipakai untuk produksi.");
        return 0;
    }
}
