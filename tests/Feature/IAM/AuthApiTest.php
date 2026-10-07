<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Artisan::call('migrate');
        
        // Buat setting default register role
        $role = Role::create([
            'name' => 'Calon Mahasiswa',
            'slug' => 'calon_mhs',
            'description' => 'Role default pendaftar',
            'is_active' => true
        ]);

        SystemSetting::create([
            'key' => 'default_register_role',
            'value' => 'calon_mhs'
        ]);
    }

    public function test_user_can_register()
    {
        $payload = [
            'username' => 'budi123',
            'email' => 'budi@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'name' => 'Budi Santoso',
            'phone' => '08123456789'
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['id', 'username', 'email'], 'access_token', 'refresh_token']);

        $this->assertDatabaseHas('core_users', [
            'email' => 'budi@example.com',
            'username' => 'budi123'
        ]);

        // Cek apakah mendapat role default
        $user = User::where('email', 'budi@example.com')->first();
        $this->assertTrue($user->hasRole('calon_mhs'));
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => Hash::make('Secret123!')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin@example.com',
            'password' => 'Secret123!'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['id', 'username', 'email'], 'access_token', 'refresh_token']);
        
        $this->assertDatabaseHas('core_user_sessions_iam', [
            'user_id' => $user->id
        ]);
    }

    public function test_login_rejects_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123!')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin@example.com',
            'password' => 'WrongPassword!'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_user_can_generate_sso_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/sso/token', [
            'client_app' => 'siakad'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'client_app']]);

        $this->assertDatabaseHas('core_sso_tokens', [
            'user_id' => $user->id,
            'client_app' => 'siakad'
        ]);
    }

    public function test_login_creates_user_session_with_valid_token()
    {
        $user = User::factory()->create([
            'email' => 'sessiontest@example.com',
            'username' => 'sessiontest',
            'password' => Hash::make('Secret123!')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'sessiontest@example.com',
            'password' => 'Secret123!'
        ]);

        $response->assertStatus(200);

        $session = \App\Models\UserSessionIam::where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertNotEmpty($session->token);

        $refreshToken = $response->json('refresh_token');
        $this->assertNotEmpty($refreshToken);

        // Refresh token rotation
        $refreshRes = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in']]);
    }
}
