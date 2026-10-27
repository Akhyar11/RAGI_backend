<?php

namespace Database\Seeders\Sikeu;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat / memulihkan akun admin akuntansi SIKEU agar fitur Saldo Awal
 * dapat diuji dan dioperasikan.
 *
 * Seeder ini TIDAK menghapus pivot role pengguna lain (tidak seperti
 * AdminUserSeeder). Aman dijalankan berulang kali.
 *
 * Kredensial dibaca dari .env:
 *   SIKEU_ADMIN_EMAIL, SIKEU_ADMIN_PASSWORD, SIKEU_ADMIN_USERNAME, SIKEU_ADMIN_NAME
 *
 * Bila SIKEU_ADMIN_PASSWORD kosong, seeder dilewati agar tidak membuat akun
 * dengan password default yang tidak diketahui.
 */
class SikeuAdminSeeder extends Seeder
{
    private const ROLE_SLUG = 'admin_keuangan_akuntansi';

    public function run(): void
    {
        $email = (string) env('SIKEU_ADMIN_EMAIL', 'admin.keu.akuntansi@kampus.ac.id');
        $password = (string) env('SIKEU_ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->command?->warn(
                'SikeuAdminSeeder dilewati: isi SIKEU_ADMIN_PASSWORD di .env untuk membuat akun admin akuntansi.'
            );

            return;
        }

        $role = Role::where('slug', self::ROLE_SLUG)->first();

        if (! $role) {
            $this->command?->error(
                'SikeuAdminSeeder dilewati: role "'.self::ROLE_SLUG.'" belum ada. Jalankan RoleSeeder terlebih dahulu.'
            );

            return;
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name'        => (string) env('SIKEU_ADMIN_NAME', 'Admin Keuangan Akuntansi'),
                'username'    => (string) env('SIKEU_ADMIN_USERNAME', 'admin_keu_akuntansi'),
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
                'assigned_by' => null,
                'valid_from'  => now()->toDateString(),
                'valid_until' => null,
                'created_at'  => now(),
            ]
        );

        $this->command?->info(
            'Akun admin akuntansi siap: '.$user->email.' (role: '.$role->slug.').'
        );
    }
}
