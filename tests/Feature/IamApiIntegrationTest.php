<?php

namespace Tests\Feature;

use App\Models\IamApiIntegration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
            ['key' => 'siakad_program_studi'],
            [
                'name' => 'Migrasi Program Studi SIAKAD',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Endpoint API migrasi program studi',
                'expected_schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_id' => ['type' => ['string', 'integer']],
                            'kode_prodi' => ['type' => 'string'],
                            'nama' => ['type' => 'string'],
                            'jenjang' => ['type' => 'string'],
                        ],
                    ],
                ],
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

    public function test_program_studi_migration_integration_is_seeded_idempotently_without_overwriting_connection_settings(): void
    {
        IamApiIntegration::updateOrCreate(
            ['key' => 'spmb_pendaftaran'],
            [
                'name' => 'Legacy SPMB Pendaftaran',
                'module' => 'spmb',
                'http_method' => 'GET',
                'description' => 'Legacy integration row to remove.',
                'expected_schema' => ['type' => 'array'],
                'url_api' => 'https://legacy.example.test/api/pendaftaran',
                'token' => 'legacy-token',
                'auth_type' => 'bearer',
                'is_active' => true,
            ]
        );

        $integration = IamApiIntegration::updateOrCreate(
            ['key' => 'siakad_program_studi'],
            [
                'name' => 'Custom Source URL',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Temporary configuration from admin.',
                'expected_schema' => ['type' => 'object'],
                'url_api' => 'https://legacy.example.test/api/program-studi',
                'token' => 'preserve-this-token',
                'auth_type' => 'header_key',
                'is_active' => false,
            ]
        );

        $seeder = app(IamApiIntegrationSeeder::class);
        $seeder->run();
        $seeder->run();

        $integration->refresh();

        $this->assertSame(1, IamApiIntegration::where('key', 'siakad_program_studi')->count());
        $this->assertSame(0, IamApiIntegration::withTrashed()->where('key', 'spmb_pendaftaran')->count());
        $this->assertSame('Migrasi Program Studi SIAKAD', $integration->name);
        $this->assertSame('siakad', $integration->module);
        $this->assertSame('https://legacy.example.test/api/program-studi', $integration->url_api);
        $this->assertSame('preserve-this-token', $integration->token);
        $this->assertSame('header_key', $integration->auth_type);
        $this->assertFalse($integration->is_active);
        $this->assertArrayHasKey('source_id', $integration->expected_schema['items']['properties']);
        $this->assertSame(['string', 'null'], $integration->expected_schema['items']['properties']['fakultas']['type']);
    }

    public function test_admin_can_show_api_integration_detail()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/admin/api-integrations/{$this->integration->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.key', 'siakad_program_studi');
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

    public function test_admin_can_sync_program_studi_and_repeat_without_creating_duplicates(): void
    {
        DB::table('spmb_master_referensi')->insert([
            'modul' => 'siakad',
            'tipe' => 'jenjang_prodi',
            'kode' => 'D3',
            'nama' => 'Diploma 3',
            'urutan' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->integration->update([
            'url_api' => 'https://legacy.example.test/api/program-studi',
            'token' => 'sync-token',
        ]);

        Http::fakeSequence('https://legacy.example.test/api/program-studi*')
            ->push([
                'data' => [
                    [
                        'source_id' => 101,
                        'kode_prodi' => 'TI-D3',
                        'kode_prodi_dikti' => null,
                        'nama' => 'Teknik Informatika',
                        'jenjang' => 'D3',
                        'akreditasi' => null,
                        'is_active' => true,
                        'fakultas' => null,
                    ],
                    [
                        'source_id' => 102,
                        'kode_prodi' => 'TI-S9',
                        'nama' => 'Teknik Informatika Sembilan',
                        'jenjang' => 'S9',
                        'is_active' => true,
                    ],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ], 200)
            ->push([
                'data' => [[
                    'source_id' => 101,
                    'kode_prodi' => 'TI-D3-REV',
                    'kode_prodi_dikti' => null,
                    'nama' => 'Teknik Informatika Revisi',
                    'jenjang' => 'D3',
                    'akreditasi' => null,
                    'is_active' => true,
                    'fakultas' => null,
                ]],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ], 200);

        $firstSync = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$this->integration->id}/sync");

        $firstSync->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.source_total', 2)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_items.0.reason', 'Kode jenjang tidak tersedia pada master referensi SIAKAD.');

        $programStudiId = DB::table('siakad_program_studi')->where('kode_prodi', 'TI-D3')->value('id');
        $this->assertNotNull($programStudiId);
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $programStudiId,
            'source_id' => '101',
            'kode_prodi' => 'TI-D3',
            'nama' => 'Teknik Informatika',
            'fakultas_id' => null,
        ]);

        $secondSync = $this->postJson("/api/admin/api-integrations/{$this->integration->id}/sync");
        $secondSync->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(1, DB::table('siakad_program_studi')->where('source_id', '101')->count());
        $this->assertDatabaseMissing('siakad_program_studi', ['kode_prodi' => 'TI-D3']);
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $programStudiId,
            'source_id' => '101',
            'kode_prodi' => 'TI-D3-REV',
            'nama' => 'Teknik Informatika Revisi',
        ]);
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

        $syncResponse = $this->actingAs($this->regularUser, 'api')
            ->postJson("/api/admin/api-integrations/{$this->integration->id}/sync");

        $syncResponse->assertStatus(403);
    }
}
