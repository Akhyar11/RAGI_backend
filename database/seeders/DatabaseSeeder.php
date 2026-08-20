<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\IAM\RoleSeeder::class,
            \Database\Seeders\IAM\PermissionSeeder::class,
            \Database\Seeders\IAM\AdminUserSeeder::class,
            \Database\Seeders\IAM\ModuleSeeder::class,
            \Database\Seeders\IAM\MenuSeeder::class,
            OauthAppClientSeeder::class,

            // SIMPEG Seeders
            \Database\Seeders\Simpeg\UnitKerjaSeeder::class,
            \Database\Seeders\Simpeg\JabatanFungsionalSeeder::class,
            \Database\Seeders\Simpeg\JabatanSeeder::class,
            \Database\Seeders\Simpeg\PegawaiSeeder::class,
            \Database\Seeders\Simpeg\EnterpriseSimpegSeeder::class,

            // SIPPM Seeders
            \Database\Seeders\Sippm\SippmSkemaSeeder::class,
            \Database\Seeders\Sippm\SippmPeriodeSeeder::class,
            \Database\Seeders\Sippm\StandarIku5ProdiSeeder::class,
            \Database\Seeders\Sippm\SippmSampleDataSeeder::class,

            // SIKEU Seeders
            \Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class,
            \Database\Seeders\Sikeu\SikeuMasterSeeder::class,
            \Database\Seeders\Sikeu\MahasiswaBillingSeeder::class,

            // SINAPRA Seeders
            \Database\Seeders\Sinapra\SinapraSeeder::class,

            // SPMB Seeders
            \Database\Seeders\SpmbMenuSeeder::class,
            \Database\Seeders\SPMB\SpmbPendaftarSeeder::class,
        ]);
    }
}
