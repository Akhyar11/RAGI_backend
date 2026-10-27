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
            \Database\Seeders\IAM\ModuleSeeder::class,
            \Database\Seeders\IAM\MenuSeeder::class,
            \Database\Seeders\IAM\MenuValidasiManualSeeder::class,
            \Database\Seeders\IAM\MenuApprovalTahapSeeder::class,
            \Database\Seeders\SpmbMenuCalonMahasiswaSeeder::class,
            \Database\Seeders\SpmbMenuAdminSeeder::class,
            \Database\Seeders\IAM\AdminUserSeeder::class,
            \Database\Seeders\IAM\SuperAdminSeeder::class,
            \Database\Seeders\SpmbBerkasRequirementSeeder::class,
            \Database\Seeders\SpmbTemplateSuratSeeder::class,
            \Database\Seeders\ArsipUnitSeeder::class,
            \Database\Seeders\Simpeg\SimpegPayrollFlexibleSeeder::class,
            \Database\Seeders\IAM\IamApiIntegrationSeeder::class,
            \Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class,
            \Database\Seeders\SiakadAturanSksSeeder::class,
            \Database\Seeders\Sikeu\SikeuAdminSeeder::class,
            \Database\Seeders\SiakadSamplePerkuliahanSeeder::class,
        ]);
    }
}
