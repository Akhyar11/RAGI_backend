<?php

namespace Tests\Feature;

use App\Models\IamApiIntegration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IamApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected IamApiIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $this->admin = User::create([
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_verified' => true,
        ]);
        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Admin', 'is_active' => true]
        );
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->regularUser = User::create([
            'username' => 'regular_test',
            'email' => 'regular@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->integration = IamApiIntegration::firstOrCreate(
            ['key' => 'spmb_pendaftaran'],
            [
                'name' => 'SPMB Pendaftaran',
                'module' => 'spmb',
                'http_method' => 'GET',
                'description' => 'Endpoint API migrasi pendaftaran',
                'expected_schema' => ['type' => 'array'],
                'auth_type' => 'bearer',
                'is_active' => true,
            ]
        );
    }

    public function test_admin_can_list_api_integrations()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/admin/api-integrations');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'key', 'name', 'module', 'http_method', 'is_active']
                ],
                'meta',
            ]);
    }

    public function test_admin_can_show_api_integration_detail()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/admin/api-integrations/{$this->integration->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.key', 'spmb_pendaftaran');
    }

    public function test_admin_can_update_api_integration()
    {
        $payload = [
            'url_api' => 'https://example.com/api/pendaftar',
            'token' => 'sample-test-token-123',
            'auth_type' => 'bearer',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/api-integrations/{$this->integration->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.url_api', 'https://example.com/api/pendaftar');

        $this->assertDatabaseHas('iam_api_integrations', [
            'id' => $this->integration->id,
            'url_api' => 'https://example.com/api/pendaftar',
            'auth_type' => 'bearer',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_test_api_connection_with_http_mock()
    {
        Http::fake([
            'https://example.com/api/pendaftar' => Http::response([
                ['nomor_pendaftaran' => 'REG001', 'nama_lengkap' => 'Tes Siswa']
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$this->integration->id}/test", [
                'url_api' => 'https://example.com/api/pendaftar',
                'token' => 'mock-token',
                'auth_type' => 'bearer',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_success', true)
            ->assertJsonPath('data.http_status', 200);

        $this->assertDatabaseHas('iam_api_integrations', [
            'id' => $this->integration->id,
            'last_status' => 'success',
        ]);
    }

    public function test_regular_user_without_permission_is_forbidden()
    {
        $response = $this->actingAs($this->regularUser, 'api')
            ->getJson('/api/admin/api-integrations');

        $response->assertStatus(403);
    }
}
