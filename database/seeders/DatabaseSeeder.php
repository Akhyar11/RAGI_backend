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
            OauthAppClientSeeder::class,

            // SIMPEG Seeders
            \Database\Seeders\Simpeg\UnitKerjaSeeder::class,
            \Database\Seeders\Simpeg\JabatanFungsionalSeeder::class,
            \Database\Seeders\Simpeg\JabatanSeeder::class,
            \Database\Seeders\Simpeg\PegawaiSeeder::class,
            \Database\Seeders\Simpeg\EnterpriseSimpegSeeder::class,
        ]);
    }
}
