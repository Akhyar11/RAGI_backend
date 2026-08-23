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
        DB::table('core_user_roles')->truncate();
        Schema::enableForeignKeyConstraints();

        $createOrRestoreUser = function ($email, $attributes) {
            $user = User::withTrashed()->updateOrCreate(['email' => $email], $attributes);
            if ($user->trashed()) {
                $user->restore();
            }
            return $user;
        };

        // 1. Seed Super Admin
        $superadmin = $createOrRestoreUser(
            'superadmin@kampus.ac.id',
            [
                'username'    => 'superadmin',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 1b. Seed Admin
        $admin = $createOrRestoreUser(
            'admin@kampus.ac.id',
            [
                'username'    => 'admin',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 2. User Wasis (Multi-role: Dosen + Admin SIMPEG)
        $wasis = $createOrRestoreUser(
            'wasis@kampus.ac.id',
            [
                'username'    => 'wasis',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 3. User Admin SIMPEG
        $adminSimpegUser = $createOrRestoreUser(
            'admin.simpeg@kampus.ac.id',
            [
                'username'    => 'admin_simpeg',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 4. User Dosen Murni
        $dosenUser = $createOrRestoreUser(
            'dosen@kampus.ac.id',
            [
                'username'    => 'dosen',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 5. User Tendik Murni
        $tendikUser = $createOrRestoreUser(
            'tendik@kampus.ac.id',
            [
                'username'    => 'tendik',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // 6. User Mahasiswa
        $mhsUser = $createOrRestoreUser(
            'mahasiswa@kampus.ac.id',
            [
                'username'    => 'mahasiswa',
                'password'    => Hash::make('password'),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        // ── MAP USER ROLES ──────────────────────────────────────────
        $roleSuperAdmin = Role::where('slug', 'superadmin')->first();
        $roleAdmin = Role::where('slug', 'admin')->first();
        $roleAdminSimpeg = Role::where('slug', 'admin_simpeg')->first();
        $roleDosen = Role::where('slug', 'dosen')->first();
        $roleTendik = Role::where('slug', 'tendik')->first();
        $roleMhs = Role::where('slug', 'mahasiswa')->first();

        $assignRole = function ($userId, $roleId, $assignerId) {
            if ($userId && $roleId) {
                DB::table('core_user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $roleId],
                    ['assigned_by' => $assignerId, 'valid_from' => now()->toDateString(), 'created_at' => now()]
                );
            }
        };

        if ($roleSuperAdmin) $assignRole($superadmin->id, $roleSuperAdmin->id, $superadmin->id);
        if ($roleAdmin) $assignRole($admin->id, $roleAdmin->id, $superadmin->id);
        if ($wasis) {
            if ($roleDosen) $assignRole($wasis->id, $roleDosen->id, $admin->id);
            if ($roleAdminSimpeg) $assignRole($wasis->id, $roleAdminSimpeg->id, $admin->id);
        }
        if ($adminSimpegUser && $roleAdminSimpeg) $assignRole($adminSimpegUser->id, $roleAdminSimpeg->id, $admin->id);
        if ($dosenUser && $roleDosen) $assignRole($dosenUser->id, $roleDosen->id, $admin->id);
        if ($tendikUser && $roleTendik) $assignRole($tendikUser->id, $roleTendik->id, $admin->id);
        if ($mhsUser && $roleMhs) $assignRole($mhsUser->id, $roleMhs->id, $admin->id);

        // 2. Seed Admin SPMB
        $adminSpmb = User::updateOrCreate(
            ['email' => env('SPMB_ADMIN_EMAIL', 'adminspmb@kampus.ac.id')],
            [
                'username'  => 'adminspmb',
                'password'  => Hash::make(env('SPMB_ADMIN_PASSWORD', 'password')),
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $spmbAdminRole = Role::where('slug', 'admin-spmb')->orWhere('slug', 'admin_spmb')->first();
        
        if ($spmbAdminRole) {
            $assignRole($adminSpmb->id, $spmbAdminRole->id, $admin->id);
        }
    }
}
