<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Buat user admin default
        User::updateOrCreate(
            ['email' => 'admin@kampus.ac.id'],
            [
                'username'  => 'admin',
                'email'     => 'admin@kampus.ac.id',
                'password'  => Hash::make('password123'),
                'phone'     => '081234567890',
                'user_type' => 'admin',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        // Daftarkan semua OAuth2 client untuk ekosistem kampus
        $this->call(OauthAppClientSeeder::class);
    }
}
