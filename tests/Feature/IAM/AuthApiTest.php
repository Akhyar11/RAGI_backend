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
            ->assertJsonStructure(['data' => ['user', 'token']]);

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
            ->assertJsonStructure(['data' => ['access_token']]);
        
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

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_user_can_generate_sso_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/sso/generate-token', [
            'target_module' => 'siakad'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['sso_token']]);

        $this->assertDatabaseHas('core_sso_tokens', [
            'user_id' => $user->id,
            'target_module' => 'siakad'
        ]);
    }

    public function test_user_can_validate_sso_token()
    {
        $user = User::factory()->create();
        $ssoTokenStr = \Illuminate\Support\Str::random(40);
        
        $user->ssoTokens()->create([
            'token' => hash('sha256', $ssoTokenStr),
            'target_module' => 'siakad',
            'expires_at' => now()->addMinutes(5)
        ]);

        $response = $this->postJson('/api/sso/validate-token', [
            'sso_token' => $ssoTokenStr,
            'target_module' => 'siakad'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id);
    }
}
