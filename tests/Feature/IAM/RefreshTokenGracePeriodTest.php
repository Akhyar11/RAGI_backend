<?php

namespace Tests\Feature\IAM;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SsoToken;
use App\Services\IAM\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RefreshTokenGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('passport:client', ['--personal' => true, '--name' => 'Personal Access Client', '--provider' => 'users']);
    }

    public function test_refresh_token_has_grace_period_for_concurrent_requests()
    {
        $user = User::factory()->create(['is_active' => true, 'is_verified' => true]);
        $ssoService = app(SsoService::class);

        $initialSession = $ssoService->issueSessionPair($user, 'web', 1440);
        $oldRefreshToken = $initialSession->refresh_token;

        // Request 1: Tukar refresh token
        $response1 = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $response1->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $newAccessToken = $response1->json('data.access_token');
        $newRefreshToken = $response1->json('data.refresh_token');

        // Request 2 (Simulasi Tab 2 menembak dengan token lama dalam rentang grace period < 30 detik)
        $response2 = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Verifikasi token baru yang dikembalikan sama dan valid (tidak 401)
        $this->assertEquals($newRefreshToken, $response2->json('data.refresh_token'));
    }
}
