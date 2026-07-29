<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin',     'slug' => 'super-admin',     'description' => 'Akses penuh ke seluruh sistem'],
            ['name' => 'Admin IAM',       'slug' => 'admin-iam',       'description' => 'Mengelola user, role, dan permission'],
            ['name' => 'Dosen',           'slug' => 'dosen',           'description' => 'Tenaga pengajar'],
            ['name' => 'Dosen Wali',      'slug' => 'dosen-wali',      'description' => 'Dosen dengan tugas bimbingan akademik'],
            ['name' => 'Mahasiswa',       'slug' => 'mahasiswa',       'description' => 'Mahasiswa aktif'],
            ['name' => 'Admin SPMB',      'slug' => 'admin-spmb',      'description' => 'Mengelola penerimaan mahasiswa baru'],
            ['name' => 'Admin SIAKAD',    'slug' => 'admin-siakad',    'description' => 'Mengelola akademik core'],
            ['name' => 'Admin SIKEU',     'slug' => 'admin-sikeu',     'description' => 'Mengelola tagihan dan pembayaran'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => true
                ]
            );
        }
    }
}
