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

        // 1. Seed Super Admin Tunggal
        $superadmin = $createOrRestoreUser(
            env('SUPER_ADMIN_EMAIL', 'superadmin@kampus.ac.id'),
            [
                'username'    => 'superadmin',
                'password'    => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        $roleSuperAdmin = Role::where('slug', 'superadmin')->orWhere('slug', 'super-admin')->first();
        if ($roleSuperAdmin && $superadmin) {
            DB::table('core_user_roles')->updateOrInsert(
                ['user_id' => $superadmin->id, 'role_id' => $roleSuperAdmin->id],
                ['assigned_by' => $superadmin->id, 'valid_from' => now()->toDateString(), 'created_at' => now()]
            );
        }
    }
}
