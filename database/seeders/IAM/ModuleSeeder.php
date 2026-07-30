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
        Module::create([
            'name' => 'IAM & Auth Center',
            'code' => 'sso',
            'description' => 'Modul inti untuk Single Sign-On dan Manajemen Pengguna (IAM).',
            'is_active' => true,
        ]);
    }
}
