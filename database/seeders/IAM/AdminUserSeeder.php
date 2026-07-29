<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@kampus.ac.id')],
            [
                'username'  => 'superadmin',
                'password'  => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'user_type' => 'admin',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        
        if ($superAdminRole) {
            DB::table('user_roles')->updateOrInsert(
                [
                    'user_id' => $admin->id,
                    'role_id' => $superAdminRole->id,
                ],
                [
                    'assigned_by' => $admin->id,
                    'valid_from' => now()->toDateString(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
