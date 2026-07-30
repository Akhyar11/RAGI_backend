<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('user_roles')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Seed Super Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@kampus.ac.id'],
            [
                'username'    => 'admin',
                'password'    => Hash::make('password'),
                'user_type'   => 'admin',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 2. User Wasis (Multi-role: Dosen + Admin SIMPEG)
        $wasis = User::updateOrCreate(
            ['email' => 'wasis@kampus.ac.id'],
            [
                'username'    => 'wasis',
                'password'    => Hash::make('password'),
                'user_type'   => 'dosen',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 3. User Admin SIMPEG
        $adminSimpegUser = User::updateOrCreate(
            ['email' => 'admin.simpeg@kampus.ac.id'],
            [
                'username'    => 'admin_simpeg',
                'password'    => Hash::make('password'),
                'user_type'   => 'admin',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 4. User Dosen Murni
        $dosenUser = User::updateOrCreate(
            ['email' => 'dosen@kampus.ac.id'],
            [
                'username'    => 'dosen',
                'password'    => Hash::make('password'),
                'user_type'   => 'dosen',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 5. User Tendik Murni
        $tendikUser = User::updateOrCreate(
            ['email' => 'tendik@kampus.ac.id'],
            [
                'username'    => 'tendik',
                'password'    => Hash::make('password'),
                'user_type'   => 'tendik',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 6. User Mahasiswa
        $mhsUser = User::updateOrCreate(
            ['email' => 'mahasiswa@kampus.ac.id'],
            [
                'username'    => 'mahasiswa',
                'password'    => Hash::make('password'),
                'user_type'   => 'mahasiswa',
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // ── MAP USER ROLES ──────────────────────────────────────────
        $roleAdmin = Role::where('slug', 'admin')->first();
        $roleAdminSimpeg = Role::where('slug', 'admin_simpeg')->first();
        $roleDosen = Role::where('slug', 'dosen')->first();
        $roleTendik = Role::where('slug', 'tendik')->first();
        $roleMhs = Role::where('slug', 'mahasiswa')->first();

        // Admin -> Role Super Admin
        if ($roleAdmin) {
            DB::table('user_roles')->insert([
                'user_id' => $admin->id,
                'role_id' => $roleAdmin->id,
                'assigned_by' => $admin->id,
                'valid_from' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // Wasis -> Role Dosen DAN Admin SIMPEG (Multi-role!)
        if ($wasis) {
            if ($roleDosen) {
                DB::table('user_roles')->insert([
                    'user_id' => $wasis->id,
                    'role_id' => $roleDosen->id,
                    'assigned_by' => $admin->id,
                    'valid_from' => now()->toDateString(),
                    'created_at' => now(),
                ]);
            }
            if ($roleAdminSimpeg) {
                DB::table('user_roles')->insert([
                    'user_id' => $wasis->id,
                    'role_id' => $roleAdminSimpeg->id,
                    'assigned_by' => $admin->id,
                    'valid_from' => now()->toDateString(),
                    'created_at' => now(),
                ]);
            }
        }

        // Admin SIMPEG User -> Role Admin SIMPEG
        if ($adminSimpegUser && $roleAdminSimpeg) {
            DB::table('user_roles')->insert([
                'user_id' => $adminSimpegUser->id,
                'role_id' => $roleAdminSimpeg->id,
                'assigned_by' => $admin->id,
                'valid_from' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // Dosen -> Role Dosen
        if ($dosenUser && $roleDosen) {
            DB::table('user_roles')->insert([
                'user_id' => $dosenUser->id,
                'role_id' => $roleDosen->id,
                'assigned_by' => $admin->id,
                'valid_from' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // Tendik -> Role Tendik
        if ($tendikUser && $roleTendik) {
            DB::table('user_roles')->insert([
                'user_id' => $tendikUser->id,
                'role_id' => $roleTendik->id,
                'assigned_by' => $admin->id,
                'valid_from' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // Mahasiswa -> Role Mahasiswa
        if ($mhsUser && $roleMhs) {
            DB::table('user_roles')->insert([
                'user_id' => $mhsUser->id,
                'role_id' => $roleMhs->id,
                'assigned_by' => $admin->id,
                'valid_from' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // 2. Seed Admin SPMB
        $adminSpmb = User::updateOrCreate(
            ['email' => env('SPMB_ADMIN_EMAIL', 'adminspmb@kampus.ac.id')],
            [
                'username'  => 'adminspmb',
                'password'  => Hash::make(env('SPMB_ADMIN_PASSWORD', 'password')),
                'user_type' => 'admin',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $spmbAdminRole = Role::where('slug', 'admin-spmb')->first();
        
        if ($spmbAdminRole) {
            DB::table('user_roles')->updateOrInsert(
                [
                    'user_id' => $adminSpmb->id,
                    'role_id' => $spmbAdminRole->id,
                ],
                [
                    'assigned_by' => $admin->id, // Assigned by superadmin
                    'valid_from' => now()->toDateString(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
