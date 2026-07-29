<?php

namespace Tests\Feature;

use App\Models\SsoToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_authenticated_user_can_generate_sso_token()
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/sso/token', ['client_app' => 'siakad']);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [
                     'access_token', 'refresh_token',
                     'client_app', 'access_expires_at', 'refresh_expires_at',
                 ]]);

        $this->assertDatabaseHas('sso_tokens', [
            'user_id'    => $this->user->id,
            'client_app' => 'siakad',
        ]);
    }

    public function test_verify_valid_sso_token()
    {
        $ssoToken = SsoToken::create([
            'user_id'            => $this->user->id,
            'access_token'       => 'valid-access-token-123',
            'refresh_token'      => 'valid-refresh-token-123',
            'client_app'         => 'spmb',
            'access_expires_at'  => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/sso/verify', [
            'access_token' => 'valid-access-token-123',
            'client_app'   => 'spmb',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.valid', true)
                 ->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_verify_expired_token_returns_401()
    {
        SsoToken::create([
            'user_id'            => $this->user->id,
            'access_token'       => 'expired-token',
            'refresh_token'      => 'refresh-token',
            'client_app'         => 'spmb',
            'access_expires_at'  => now()->subMinutes(1), // Sudah kedaluwarsa
            'refresh_expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/sso/verify', [
            'access_token' => 'expired-token',
            'client_app'   => 'spmb',
        ]);

        $response->assertStatus(401);
    }

    public function test_refresh_token_generates_new_tokens()
    {
        SsoToken::create([
            'user_id'            => $this->user->id,
            'access_token'       => 'old-access-token',
            'refresh_token'      => 'valid-refresh-token',
            'client_app'         => 'sikeu',
            'access_expires_at'  => now()->subMinutes(5), // access expired
            'refresh_expires_at' => now()->addDays(30),   // refresh masih valid
        ]);

        $response = $this->postJson('/api/sso/refresh', [
            'refresh_token' => 'valid-refresh-token',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

        // Token lama harus sudah dihapus
        $this->assertDatabaseMissing('sso_tokens', ['access_token' => 'old-access-token']);
    }

    public function test_authenticated_user_can_revoke_sso_token()
    {
        Passport::actingAs($this->user);

        SsoToken::create([
            'user_id'            => $this->user->id,
            'access_token'       => 'token-to-revoke',
            'refresh_token'      => 'refresh-to-revoke',
            'client_app'         => 'lms',
            'access_expires_at'  => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/sso/revoke', ['client_app' => 'lms']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sso_tokens', ['access_token' => 'token-to-revoke']);
    }
}
