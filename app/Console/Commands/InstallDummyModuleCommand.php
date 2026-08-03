<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallDummyModuleCommand extends Command
{
    protected $signature = 'module:install-dummy {modul=all}';
    protected $description = 'Instalasi struktur database, master data, dan sampel data DUMMY untuk pengujian/testing';

    public function handle()
    {
        $modul = strtolower($this->argument('modul'));

        $this->info("=================================================");
        $this->info("🧪 Memulai Instalasi DUMMY Data: [" . strtoupper($modul) . "]");
        $this->info("=================================================");

        // 1. Run Base Install
        $this->call(InstallModuleCommand::class, ['modul' => $modul]);

        // 2. Dummy Data Seeding
        if (in_array($modul, ['simpeg', 'all'])) {
            $this->info("▸ Seed Dummy Data SIMPEG (Pegawai & Enterprise Sample)...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Simpeg\PegawaiSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => '\Database\Seeders\Simpeg\EnterpriseSimpegSeeder', '--force' => true]);
        }

        if (in_array($modul, ['sippm', 'all'])) {
            $this->info("▸ Seed Dummy Data SIPPM (Sample Proposal & Reviewer)...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sippm\SippmSampleDataSeeder', '--force' => true]);
        }

        if (in_array($modul, ['sikeu', 'all'])) {
            $this->info("▸ Seed Dummy Data SIKEU (Sample Tagihan, Dispensasi, Pemasukan, Pengeluaran & Jurnal)...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sikeu\SikeuDummySeeder', '--force' => true]);
        }

        $this->newLine();
        $this->info("✅ Instalasi Data DUMMY [" . strtoupper($modul) . "] Berhasil!");
        return 0;
    }
}
