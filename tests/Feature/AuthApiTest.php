<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'user_type'             => 'mahasiswa',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token']);
    }

    public function test_user_can_refresh_token()
    {
        $user = User::factory()->create();
        
        $ssoService = app(\App\Services\IAM\SsoService::class);
        $ssoToken = $ssoService->generateTokens($user, 'spmb');

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $ssoToken->refresh_token
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }
}
