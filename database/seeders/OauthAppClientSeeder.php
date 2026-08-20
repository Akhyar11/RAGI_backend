<?php

namespace Database\Seeders;

use App\Models\OauthAppClient;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class OauthAppClientSeeder extends Seeder
{
    /**
     * Daftarkan semua aplikasi klien ekosistem kampus sebagai OAuth2 client.
     * Setiap aplikasi mendapat client_id dan client_secret dari Passport.
     */
    public function run(): void
    {
        $clientRepo = app(ClientRepository::class);

        $apps = [
            [
                'client_app'            => 'spmb',
                'client_name'           => 'Sistem Penerimaan Mahasiswa Baru',
                'allowed_redirect_uris' => [
                    'https://spmb.kampus.ac.id/auth/callback',
                    'http://localhost:3001/auth/callback',
                ],
            ],
            [
                'client_app'            => 'siakad',
                'client_name'           => 'Sistem Informasi Akademik',
                'allowed_redirect_uris' => [
                    'https://siakad.kampus.ac.id/auth/callback',
                    'http://localhost:3002/auth/callback',
                ],
            ],
            [
                'client_app'            => 'sikeu',
                'client_name'           => 'Sistem Informasi Keuangan',
                'allowed_redirect_uris' => [
                    'https://sikeu.kampus.ac.id/auth/callback',
                    'http://localhost:3003/auth/callback',
                ],
            ],
            [
                'client_app'            => 'simpeg',
                'client_name'           => 'Sistem Informasi Kepegawaian',
                'allowed_redirect_uris' => [
                    'https://simpeg.kampus.ac.id/auth/callback',
                    'http://localhost:3004/auth/callback',
                ],
            ],
            [
                'client_app'            => 'lms',
                'client_name'           => 'Learning Management System',
                'allowed_redirect_uris' => [
                    'https://lms.kampus.ac.id/auth/callback',
                    'http://localhost:3005/auth/callback',
                ],
            ],
            [
                'client_app'            => 'sinapra',
                'client_name'           => 'Sistem Informasi Anggaran & Prasarana',
                'allowed_redirect_uris' => [
                    'https://sinapra.kampus.ac.id/auth/callback',
                    'http://localhost:3006/auth/callback',
                ],
            ],
            [
                'client_app'            => 'upm',
                'client_name'           => 'Unit Penjaminan Mutu',
                'allowed_redirect_uris' => [
                    'https://upm.kampus.ac.id/auth/callback',
                    'http://localhost:3007/auth/callback',
                ],
            ],
        ];

        foreach ($apps as $app) {
            // Buat OAuth2 client di Passport
            $passportClient = $clientRepo->createAuthorizationCodeGrantClient(
                name: $app['client_name'],
                redirectUris: $app['allowed_redirect_uris'],
                confidential: true,
                user: null,
            );

            // Simpan mapping di tabel kustom kita
            OauthAppClient::updateOrCreate(
                ['client_app' => $app['client_app']],
                [
                    'client_name'           => $app['client_name'],
                    'passport_client_id'    => $passportClient->id,
                    'allowed_redirect_uris' => $app['allowed_redirect_uris'],
                    'is_active'             => true,
                ]
            );

            $this->command->info("✓ Client [{$app['client_app']}] registered — ID: {$passportClient->id}");
        }

        // Pastikan Personal Access Client tersedia untuk provider 'users'
        try {
            $personalClient = $clientRepo->personalAccessClient('users');
            if (!$personalClient) {
                $createdClient = $clientRepo->createPersonalAccessGrantClient(
                    name: 'Campus Personal Access Client',
                    provider: 'users'
                );
                $this->command->info("✓ Personal Access Client registered — ID: {$createdClient->id}");
            }
        } catch (\Throwable $e) {
            $createdClient = $clientRepo->createPersonalAccessGrantClient(
                name: 'Campus Personal Access Client',
                provider: 'users'
            );
            $this->command->info("✓ Personal Access Client created — ID: {$createdClient->id}");
        }
    }
}
