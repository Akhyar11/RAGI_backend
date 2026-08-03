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
        if (in_array($modul, ['simpeg', 'all'])) {
            $this->info("▸ Seed Master Data SIMPEG...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Simpeg\UnitKerjaSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => '\Database\Seeders\Simpeg\JabatanFungsionalSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => '\Database\Seeders\Simpeg\JabatanSeeder', '--force' => true]);
        }

        if (in_array($modul, ['sippm', 'all'])) {
            $this->info("▸ Seed Master Data SIPPM...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sippm\SippmSkemaSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sippm\SippmPeriodeSeeder', '--force' => true]);
        }

        if (in_array($modul, ['sikeu', 'all'])) {
            $this->info("▸ Seed Master Data SIKEU (Akuntansi COA, UKT, Kas)...");
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sikeu\SikeuAkuntansiSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => '\Database\Seeders\Sikeu\SikeuMasterSeeder', '--force' => true]);
        }

        $this->newLine();
        $this->info("✅ Instalasi Modul [" . strtoupper($modul) . "] Berhasil Diselesaikan!");
        return 0;
    }
}
