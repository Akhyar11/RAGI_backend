<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeederTokenModeTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->user = User::firstOrCreate(
            ['email' => 'feeder.token@kampus.ac.id'],
            ['username' => 'feedertoken', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        // Arahkan ke port yang pasti tertutup agar WS tidak terjangkau (connection refused, cepat).
        SystemSetting::set('feeder_url', 'http://127.0.0.1:9/ws/live2.php');
        SystemSetting::set('feeder_username', 'tes_koneksi');
        SystemSetting::set('feeder_password', 'rahasia');
    }

    protected function token(): string
    {
        $result = $this->user->createToken('test-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    public function test_token_ditandai_staging_saat_ws_tidak_terjangkau(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/v1/siakad/feeder-sync/token');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_staging', true)
            ->assertJsonStructure(['status', 'message', 'data' => ['token', 'is_staging', 'staging_reason']]);

        $token = $response->json('data.token');
        $this->assertTrue(str_starts_with($token, 'STAGING-TOKEN-'));
        $this->assertStringContainsString('Tidak dapat terhubung', (string) $response->json('data.staging_reason'));
    }

    public function test_update_kredensial_feeder_membuang_token_cache(): void
    {
        \Illuminate\Support\Facades\Cache::put('neo_feeder_token', 'STAGING-TOKEN-LAMA', 3600);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/system-settings', [
                'settings' => [
                    ['key' => 'feeder_password', 'value' => 'baru123'],
                ],
            ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('neo_feeder_token'));
        $this->assertDatabaseHas('core_system_settings', [
            'key' => 'feeder_password',
            'value' => 'baru123',
        ]);
    }
}
