<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Module::updateOrCreate(
            ['code' => 'sso'],
            [
                'name' => 'IAM & Auth Center',
                'description' => 'Modul inti untuk Single Sign-On dan Manajemen Pengguna (IAM).',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'simpeg'],
            [
                'name' => 'SIMPEG (Kepegawaian)',
                'description' => 'Sistem Informasi Manajemen Kepegawaian Kampus.',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'sippm'],
            [
                'name' => 'SIPPM Kampus',
                'description' => 'Sistem Informasi Penelitian dan Pengabdian Masyarakat.',
                'is_active' => true,
            ]
        );
    }
}
