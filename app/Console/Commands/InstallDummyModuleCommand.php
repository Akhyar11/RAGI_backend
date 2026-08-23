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
        $seeders = [
            'simpeg' => [
                '\Database\Seeders\Simpeg\PegawaiSeeder',
                '\Database\Seeders\Simpeg\EnterpriseSimpegSeeder'
            ],
            'sippm' => [
                '\Database\Seeders\Sippm\SippmSampleDataSeeder'
            ],
            'sikeu' => [
                '\Database\Seeders\Sikeu\SikeuDummySeeder'
            ],
            'spmb' => [
                '\Database\Seeders\Spmb\MasterProgramStudiSeeder'
            ]
        ];

        foreach ($seeders as $modKey => $seederList) {
            if ($modul === $modKey || $modul === 'all') {
                $this->info("▸ Seed Dummy Data " . strtoupper($modKey) . "...");
                foreach ($seederList as $seeder) {
                    $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
            }
        }

        $this->newLine();
        $this->info("✅ Instalasi Data DUMMY [" . strtoupper($modul) . "] Berhasil!");
        return 0;
    }
}
