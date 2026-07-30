<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('role_permissions')->truncate();
        DB::table('roles')->truncate();
        Schema::enableForeignKeyConstraints();

        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'superadmin',
                'description' => 'Akses penuh tanpa batas ke seluruh ekosistem SSO dan semua modul aplikasi universitas',
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrator tingkat tinggi dengan akses ke manajemen pengguna dan sistem',
            ],
            [
                'name' => 'Admin SIMPEG',
                'slug' => 'admin_simpeg',
                'description' => 'Administrator penuh Sistem Informasi Manajemen Kepegawaian (SIMPEG)',
            ],
            [
                'name' => 'Operator SDM',
                'slug' => 'operator_sdm',
                'description' => 'Staf operasional kepegawaian, pengelola dokumen e-file, presensi, & cuti',
            ],
            [
                'name' => 'Dosen Pengajar',
                'slug' => 'dosen',
                'description' => 'Tenaga pengajar dengan akses Portal Mandiri Dosen, BKD, & Usulan Jafung',
            ],
            [
                'name' => 'Tenaga Kependidikan',
                'slug' => 'tendik',
                'description' => 'Staf pendukung administrasi dengan akses Portal Mandiri Tendik, Presensi, & Cuti',
            ],
            [
                'name' => 'Mahasiswa Reguler',
                'slug' => 'mahasiswa',
                'description' => 'Pengguna SSO Portal Mahasiswa (Tidak memiliki akses ke sistem SIMPEG)',
            ],
        ];

        foreach ($roles as $role) {
            Role::create([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'is_active' => true,
            ]);
        }
    }
}
