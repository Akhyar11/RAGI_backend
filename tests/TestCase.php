<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;
use Laravel\Passport\ClientRepository;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup Passport personal access client setelah RefreshDatabase
     * menghapus tabel oauth. Dipanggil di setiap test yang membutuhkannya.
     */
    protected function setUpPassport(): void
    {
        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createPersonalAccessGrantClient(
            name: 'Test Personal Access Client',
            provider: null,
        );

        // Simpan ke config agar Passport tau client mana yang dipakai
        config(['passport.personal_access_client.id'     => $client->id]);
        config(['passport.personal_access_client.secret' => $client->plainSecret ?? '']);
    }
}
