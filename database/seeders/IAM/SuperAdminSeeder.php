<?php

namespace Database\Seeders\IAM;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Memastikan akun super admin tersedia tanpa menghapus pivot role pengguna lain.
 *
 * Berbeda dari AdminUserSeeder yang melakukan truncate pada core_user_roles,
 * seeder ini hanya menambah/memperbarui akun super admin dan role-nya.
 * Aman dijalankan berulang kali.
 *
 * Kredensial dibaca dari .env:
 *   SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_USERNAME, SUPER_ADMIN_PASSWORD
 *
 * Bila SUPER_ADMIN_PASSWORD kosong, seeder dilewati.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'superadmin@kampus.ac.id');
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->command?->warn(
                'SuperAdminSeeder dilewati: isi SUPER_ADMIN_PASSWORD di .env untuk mengatur akun super admin.'
            );

            return;
        }

        $role = Role::where('slug', 'superadmin')->orWhere('slug', 'super-admin')->first();

        if (! $role) {
            $this->command?->error(
                'SuperAdminSeeder dilewati: role "superadmin" belum ada. Jalankan RoleSeeder terlebih dahulu.'
            );

            return;
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name'        => (string) env('SUPER_ADMIN_NAME', 'Super Administrator'),
                'username'    => (string) env('SUPER_ADMIN_USERNAME', 'superadmin'),
                'password'    => Hash::make($password),
                'is_active'   => true,
                'is_verified' => true,
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }

        DB::table('core_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $role->id],
            [
                'assigned_by' => $user->id,
                'valid_from'  => now()->toDateString(),
                'valid_until' => null,
                'created_at'  => now(),
            ]
        );

        $this->command?->info(
            'Akun super admin siap: '.$user->email.' (role: '.$role->slug.').'
        );
    }
}
