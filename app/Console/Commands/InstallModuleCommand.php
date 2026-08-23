<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallModuleCommand extends Command
{
    protected $signature = 'module:install {modul=all}';
    protected $description = 'Instalasi sekali jalan struktur database & master data untuk modul spesifik atau seluruh sistem';

    public function handle()
    {
        $modul = strtolower($this->argument('modul'));

        $this->info("=================================================");
        $this->info("🚀 Memulai Instalasi Modul: [" . strtoupper($modul) . "]");
        $this->info("=================================================");

        // 1. Run migrations without data loss (never fresh)
        $this->info("▸ Jalankan Migrasi Database...");
        $this->call('migrate', ['--force' => true]);

        // 2. Base IAM & Core Setup
        $this->info("▸ Seed Base IAM (Roles, Permissions, Admin, Modules, Menus)...");
        $this->call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => '\Database\Seeders\IAM\AdminUserSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => '\Database\Seeders\IAM\ModuleSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => '\Database\Seeders\IAM\MenuSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => '\Database\Seeders\OauthAppClientSeeder', '--force' => true]);

        // 3. Specific Module Master Data Seeding
        $seeders = [
            'simpeg' => [
                '\Database\Seeders\Simpeg\UnitKerjaSeeder',
                '\Database\Seeders\Simpeg\JabatanFungsionalSeeder',
                '\Database\Seeders\Simpeg\JabatanSeeder'
            ],
            'sippm' => [
                '\Database\Seeders\Sippm\SippmSkemaSeeder',
                '\Database\Seeders\Sippm\SippmPeriodeSeeder'
            ],
            'sikeu' => [
                '\Database\Seeders\Sikeu\SikeuAkuntansiSeeder',
                '\Database\Seeders\Sikeu\SikeuMasterSeeder'
            ]
        ];

        foreach ($seeders as $modKey => $seederList) {
            if ($modul === $modKey || $modul === 'all') {
                $this->info("▸ Seed Master Data " . strtoupper($modKey) . "...");
                foreach ($seederList as $seeder) {
                    $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
            }
        }

        $this->newLine();
        $this->info("✅ Instalasi Modul [" . strtoupper($modul) . "] Berhasil Diselesaikan!");
        return 0;
    }
}
