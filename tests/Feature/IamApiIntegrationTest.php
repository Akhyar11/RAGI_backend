<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IamApiIntegration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\SystemSetting;
use App\Models\User;
use App\Jobs\SyncPddiktiMahasiswaJob;
use App\Services\IAM\SiakadMahasiswaIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\Client\Request;
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

    public function test_api_integration_responses_expose_token_presence_without_exposing_token(): void
    {
        $secret = 'configured-secret-token';
        $this->integration->update(['token' => $secret]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/admin/api-integrations');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.token_configured', true)
            ->assertJsonMissing(['token' => $secret]);

        $response->assertJsonMissingPath('data.0.token');
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
        IamApiIntegration::updateOrCreate(
            ['key' => 'siakad_mata_kuliah'],
            [
                'name' => 'Custom Course Source URL',
                'module' => 'siakad',
                'http_method' => 'GET',
                'description' => 'Temporary course source configuration.',
                'expected_schema' => ['type' => 'array'],
                'source_type' => 'custom_api',
                'url_api' => 'https://legacy.example.test/api/mata-kuliah',
                'token' => 'preserve-course-token',
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
        $this->assertSame('custom_api', $integration->source_type);
        $this->assertSame(['pddikti', 'custom_api'], $integration->supported_sources);
        $this->assertFalse($integration->is_active);
        $this->assertArrayHasKey('source_id', $integration->expected_schema['items']['properties']);
        $this->assertSame(['string', 'null'], $integration->expected_schema['items']['properties']['fakultas']['type']);

        $courseIntegration = IamApiIntegration::where('key', 'siakad_mata_kuliah')->firstOrFail();
        $this->assertSame('Migrasi Mata Kuliah SIAKAD', $courseIntegration->name);
        $this->assertArrayHasKey('kurikulum_kode', $courseIntegration->expected_schema['properties']['data']['items']['properties']);
        $this->assertSame(['pddikti', 'custom_api'], $courseIntegration->supported_sources);
        $this->assertSame('GetMatkulKurikulum', $courseIntegration->source_schemas['pddikti']['action']);
        $this->assertTrue($courseIntegration->source_schemas['pddikti']['connection_managed']);
        $this->assertSame('https://legacy.example.test/api/mata-kuliah', $courseIntegration->url_api);
        $this->assertSame('preserve-course-token', $courseIntegration->token);
        $this->assertSame('header_key', $courseIntegration->auth_type);
        $this->assertFalse($courseIntegration->is_active);
        $this->assertSame('custom_api', $courseIntegration->source_type);

        $studentIntegration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $this->assertSame(['pddikti', 'custom_api'], $studentIntegration->supported_sources);
        $this->assertSame('GetCountMahasiswa', $studentIntegration->source_schemas['pddikti']['count_action']);
        $this->assertSame('GetListMahasiswa', $studentIntegration->source_schemas['pddikti']['action']);
        $this->assertSame('GetCountBiodataMahasiswa', $studentIntegration->source_schemas['pddikti']['biodata_count_action']);
        $this->assertSame('GetBiodataMahasiswa', $studentIntegration->source_schemas['pddikti']['detail_action']);
        $this->assertTrue($studentIntegration->source_schemas['pddikti']['connection_managed']);
        $this->assertSame('pddikti', $studentIntegration->source_type);
    }

    public function test_new_course_integration_defaults_to_pddikti_without_custom_credentials(): void
    {
        app(IamApiIntegrationSeeder::class)->run();

        $integration = IamApiIntegration::where('key', 'siakad_mata_kuliah')->firstOrFail();

        $this->assertSame('pddikti', $integration->source_type);
        $this->assertNull($integration->url_api);
        $this->assertNull($integration->token);
    }

    public function test_program_study_registry_imports_get_prodi_and_stores_feeder_uuid(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_program_studi')->firstOrFail();

        $this->assertSame(['pddikti', 'custom_api'], $integration->supported_sources);
        $this->assertSame('GetProdi', $integration->source_schemas['pddikti']['action']);
        $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/api-integrations/{$integration->id}", [
                'source_type' => 'pddikti',
                'url_api' => null,
                'auth_type' => 'bearer',
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.source_type', 'pddikti');

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
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_prodi' => 'program-uuid-77',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'nama_jenjang_pendidikan' => 'D3',
                'status' => 'A',
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('siakad_program_studi', [
            'kode_prodi' => '55201',
            'source_id' => 'program-uuid-77',
            'id_feeder' => 'program-uuid-77',
                'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
        ]);
        $response->assertJsonMissing(['token' => 'runtime-feeder-token']);
    }

    public function test_program_study_source_can_switch_to_pddikti_and_sync_get_prodi(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_program_studi')->firstOrFail();
        $integration->update([
            'source_type' => 'pddikti',
            'url_api' => null,
            'token' => null,
        ]);

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
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_prodi' => 'program-uuid-88',
                'kode_program_studi' => '55202',
                'nama_program_studi' => 'Teknik Elektro',
                'nama_jenjang_pendidikan' => 'D3',
                'status' => 'A',
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('siakad_program_studi', [
            'id_feeder' => 'program-uuid-88',
            'source_id' => 'program-uuid-88',
            'kode_prodi' => '55202',
            'nama' => 'Teknik Elektro',
            'jenjang' => 'D3',
        ]);
    }

    public function test_program_study_pddikti_sync_maps_existing_source_uuid_without_overwriting_manual_code(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_program_studi')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);

        DB::table('spmb_master_referensi')->insert([
            'modul' => 'siakad',
            'tipe' => 'jenjang_prodi',
            'kode' => 'D4',
            'nama' => 'Sarjana Terapan',
            'urutan' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manualProgramId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'A',
            'source_id' => 'feeder-uuid-manual-prodi',
            'source_kode_prodi' => '21304',
            'is_status_manual' => true,
            'nama' => 'Nama Lokal Manual',
            'jenjang' => 'D4',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_prodi' => 'feeder-uuid-manual-prodi',
                'kode_program_studi' => '21304',
                'nama_program_studi' => 'Teknologi Rekayasa Otomotif',
                'nama_jenjang_pendidikan' => 'D4',
                'status' => 'A',
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $manualProgramId,
            'id_feeder' => 'feeder-uuid-manual-prodi',
            'source_id' => 'feeder-uuid-manual-prodi',
            'kode_prodi' => 'A',
            'source_kode_prodi' => '21304',
            'nama' => 'Teknologi Rekayasa Otomotif',
            'is_active' => false,
            'is_status_manual' => true,
        ]);
        $this->assertDatabaseMissing('siakad_program_studi', [
            'id_feeder' => 'feeder-uuid-manual-prodi',
            'kode_prodi' => '21304',
        ]);
    }

    public function test_program_study_pddikti_sync_resolves_duplicate_codes_by_uuid_and_skips_uuid_code_mismatch(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_program_studi')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
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
        $existingId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '21403',
            'source_id' => 'feeder-uuid-engineering',
            'nama' => 'Nama Legacy Berbeda',
            'jenjang' => 'D3',
            'is_active' => true,
            'is_status_manual' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mismatchedId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '57403',
            'source_id' => 'feeder-uuid-wrong-program',
            'nama' => 'Sistem Informasi Lokal',
            'jenjang' => 'D3',
            'is_active' => true,
            'is_status_manual' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [
                [
                    'id_prodi' => 'feeder-uuid-technology',
                    'kode_program_studi' => '21403',
                    'nama_program_studi' => 'Teknologi Otomotif',
                    'nama_jenjang_pendidikan' => 'D3',
                    'status' => 'H',
                ],
                [
                    'id_prodi' => 'feeder-uuid-engineering',
                    'kode_program_studi' => '21403',
                    'nama_program_studi' => 'Mesin Otomotif',
                    'nama_jenjang_pendidikan' => 'D3',
                    'status' => 'B',
                ],
                [
                    'id_prodi' => 'feeder-uuid-unmatched',
                    'kode_program_studi' => '57401',
                    'nama_program_studi' => 'Manajemen Informatika',
                    'nama_jenjang_pendidikan' => 'D3',
                    'status' => 'B',
                ],
            ]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.source_total', 3)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_items.0.reason', 'Kode program studi sudah terhubung ke UUID sumber lain.');
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $existingId,
            'id_feeder' => 'feeder-uuid-engineering',
            'kode_prodi' => '21403',
            'nama' => 'Mesin Otomotif',
        ]);
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $mismatchedId,
            'id_feeder' => null,
            'kode_prodi' => '57403',
            'nama' => 'Sistem Informasi Lokal',
        ]);
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

    public function test_empty_token_is_not_sent_when_updating_other_integration_settings(): void
    {
        $secret = 'keep-this-secret-token';
        $this->integration->update(['token' => $secret]);

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/api-integrations/{$this->integration->id}", [
                'url_api' => 'https://example.com/api/program-studi',
                'token' => '',
                'auth_type' => 'bearer',
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        $this->assertSame($secret, $this->integration->fresh()->token);
    }

    public function test_managed_pddikti_connection_clears_custom_url_but_preserves_unsent_token(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update([
            'source_type' => 'custom_api',
            'url_api' => 'https://legacy.example.test/students',
            'token' => 'existing-legacy-token',
        ]);

        $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/api-integrations/{$integration->id}", [
                'source_type' => 'pddikti',
                'auth_type' => 'bearer',
                'is_active' => true,
            ])
            ->assertOk();

        $integration->refresh();
        $this->assertNull($integration->url_api);
        $this->assertSame('existing-legacy-token', $integration->token);
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
        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $programStudiId,
            'source_kode_prodi' => 'TI-D3-REV',
            'kode_prodi' => 'TI-D3-REV',
        ]);
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

    public function test_resync_preserves_admin_edited_code_and_status_while_refreshing_source_code(): void
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
            ->push(['data' => [[
                'source_id' => 'legacy-77',
                'kode_prodi' => 'SRC-77',
                'nama' => 'Program Sumber',
                'jenjang' => 'D3',
                'is_active' => true,
                'fakultas' => null,
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1]], 200)
            ->push(['data' => [[
                'source_id' => 'legacy-77',
                'kode_prodi' => 'SRC-77-NEW',
                'nama' => 'Program Sumber Diperbarui',
                'jenjang' => 'D3',
                'is_active' => true,
                'fakultas' => null,
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1]], 200);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$this->integration->id}/sync")
            ->assertStatus(200);

        $program = \App\Models\Siakad\ProgramStudi::where('source_id', 'legacy-77')->firstOrFail();
        $this->putJson("/api/v1/siakad/akademik/prodi/{$program->id}", [
            'kode_prodi' => 'LOCAL-77',
            'nama' => 'Nama Lokal',
            'jenjang' => 'D3',
            'fakultas_id' => null,
            'is_active' => false,
        ])->assertStatus(200);

        $this->postJson("/api/admin/api-integrations/{$this->integration->id}/sync")
            ->assertStatus(200)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('siakad_program_studi', [
            'id' => $program->id,
            'kode_prodi' => 'LOCAL-77',
            'source_kode_prodi' => 'SRC-77-NEW',
            'nama' => 'Program Sumber Diperbarui',
            'is_active' => 0,
            'is_status_manual' => 1,
            'fakultas_id' => null,
        ]);
    }

    public function test_admin_can_sync_courses_to_an_existing_curriculum_idempotently(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_mata_kuliah',
            'name' => 'Migrasi Mata Kuliah SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi mata kuliah legacy.',
            'expected_schema' => ['type' => 'array'],
            'supported_sources' => ['pddikti', 'custom_api'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetMatkulKurikulum']],
            'url_api' => 'https://legacy.example.test/api/mata-kuliah',
            'token' => 'sync-token',
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        $programStudiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('siakad_kurikulum')->insert([
            'program_studi_id' => $programStudiId,
            'kode' => 'KUR-TI-2024',
            'nama' => 'Kurikulum TI 2024',
            'tahun_berlaku' => 2024,
            'total_sks_lulus' => 144,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fakeSequence('https://legacy.example.test/api/mata-kuliah*')
            ->push(['data' => [[
                'source_id' => 7101,
                'kurikulum_kode' => 'KUR-TI-2024',
                'kode_mk' => 'TI101',
                'nama' => 'Algoritma dan Pemrograman',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 1,
                'tipe' => 'wajib',
                'is_active' => true,
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1]], 200)
            ->push(['data' => [[
                'source_id' => 7101,
                'kurikulum_kode' => 'KUR-TI-2024',
                'kode_mk' => 'TI101',
                'nama' => 'Algoritma dan Pemrograman Revisi',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 1,
                'tipe' => 'wajib',
                'is_active' => true,
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1]], 200);

        $firstSync = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $firstSync->assertStatus(200)
            ->assertJsonPath('data.source_total', 1)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://legacy.example.test/api/mata-kuliah?page=1&per_page=100'
            && $request->hasHeader('Authorization', 'Bearer sync-token'));

        $courseId = DB::table('siakad_mata_kuliah')->where('source_id', '7101')->value('id');
        $this->assertNotNull($courseId);

        $secondSync = $this->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $secondSync->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertSame(1, DB::table('siakad_mata_kuliah')->where('source_id', '7101')->count());
        $this->assertDatabaseHas('siakad_mata_kuliah', [
            'id' => $courseId,
            'kurikulum_id' => DB::table('siakad_kurikulum')->where('kode', 'KUR-TI-2024')->value('id'),
            'kode_mk' => 'TI101',
            'nama' => 'Algoritma dan Pemrograman Revisi',
            'total_sks' => 3,
        ]);
    }

    public function test_course_sync_skips_rows_without_a_matching_curriculum(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_mata_kuliah',
            'name' => 'Migrasi Mata Kuliah SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi mata kuliah legacy.',
            'expected_schema' => ['type' => 'array'],
            'supported_sources' => ['pddikti', 'custom_api'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetMatkulKurikulum']],
            'url_api' => 'https://legacy.example.test/api/mata-kuliah',
            'token' => 'sync-token',
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        Http::fake([
            'https://legacy.example.test/api/mata-kuliah*' => Http::response(['data' => [[
                'source_id' => 7102,
                'kurikulum_kode' => 'UNKNOWN-CURRICULUM',
                'kode_mk' => 'TI102',
                'nama' => 'Struktur Data',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'total_sks' => 3,
                'semester_anjuran' => 2,
                'tipe' => 'wajib',
                'is_active' => true,
            ]], 'meta' => ['current_page' => 1, 'last_page' => 1]], 200),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_items.0.item_ref', 'TI102')
            ->assertJsonPath('data.skipped_items.0.reason', 'Kode kurikulum tidak ditemukan di SIAKAD tujuan.');
        $this->assertDatabaseMissing('siakad_mata_kuliah', ['kode_mk' => 'TI102']);
    }

    public function test_course_sync_can_pull_pddikti_using_the_saved_neo_feeder_connection(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_mata_kuliah',
            'name' => 'Migrasi Mata Kuliah SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi mata kuliah PDDIKTI.',
            'expected_schema' => ['type' => 'object'],
            'source_type' => 'pddikti',
            'supported_sources' => ['pddikti', 'custom_api'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetMatkulKurikulum']],
            'url_api' => null,
            'token' => null,
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        $programStudiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'TI-PDDIKTI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
            'id_feeder' => 'prodi-uuid-1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('siakad_kurikulum')->insert([
            'program_studi_id' => $programStudiId,
            'kode' => 'KUR-TI-PDDIKTI',
            'id_feeder' => 'kurikulum-uuid-1',
            'nama' => 'Kurikulum Teknik Informatika 2024',
            'tahun_berlaku' => 2024,
            'total_sks_lulus' => 144,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('siakad_kurikulum')->where('kode', 'KUR-TI-PDDIKTI')
            ->update(['id_feeder' => 'kurikulum-uuid-1']);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_kurikulum' => 'kurikulum-uuid-1',
                'nama_kurikulum' => 'Kurikulum Teknik Informatika 2024',
                'id_matkul' => 'matkul-uuid-1',
                'kode_mata_kuliah' => 'TI-PDD-101',
                'nama_mata_kuliah' => 'Dasar Pemrograman',
                'id_prodi' => 'prodi-uuid-1',
                'nama_program_studi' => 'Teknik Informatika',
                'semester' => 1,
                'sks_mata_kuliah' => 3,
                'sks_tatap_muka' => 2,
                'sks_praktek' => 1,
                'apakah_wajib' => 1,
                'id_kelompok_mata_kuliah' => 'MKK',
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.source_total', 1)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('siakad_mata_kuliah', [
            'kurikulum_id' => DB::table('siakad_kurikulum')->where('kode', 'KUR-TI-PDDIKTI')->value('id'),
            'source_id' => 'matkul-uuid-1',
            'id_feeder' => 'matkul-uuid-1',
            'kode_mk' => 'TI-PDD-101',
            'nama' => 'Dasar Pemrograman',
            'tipe' => 'wajib',
            'total_sks' => 3,
        ]);
        $this->assertDatabaseHas('siakad_kurikulum', ['kode' => 'KUR-TI-PDDIKTI', 'id_feeder' => 'kurikulum-uuid-1']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'GetMatkulKurikulum')
            || str_contains($request->body(), 'GetToken'));
        $response->assertJsonMissing(['token' => 'runtime-feeder-token']);
    }

    public function test_pddikti_student_preview_does_not_write_and_sync_is_idempotent_without_creating_accounts(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti', 'url_api' => null, 'token' => null]);

        ProgramStudi::query()->updateOrCreate(
            ['id_feeder' => 'a3323a44-49b3-4e7b-b887-2d89a14b0c61'],
            [
                'source_id' => 'a3323a44-49b3-4e7b-b887-2d89a14b0c61',
                'kode_prodi' => '55201',
                'nama' => 'Teknik Informatika',
                'jenjang' => 'S1',
                'is_active' => true,
            ]
        );
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');

        $student = [
            'id_mahasiswa' => '5b24f773-988a-49c8-9d44-21f4f9f1de42',
            'id_registrasi_mahasiswa' => '088adb51-ed04-4609-9aa5-f96201192704',
            'id_prodi' => 'a3323a44-49b3-4e7b-b887-2d89a14b0c61',
            'nim' => 'D25011',
            'nama_mahasiswa' => 'Mahasiswa Uji PDDIKTI',
            'jenis_kelamin' => 'P',
            'id_periode' => '20251',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
        ];

        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) use ($student) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => 1], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 2], 200),
                    'GetListMahasiswa' => Http::response(['error_code' => 0, 'data' => [$student]], 200),
                    'GetBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => [[
                        'id_mahasiswa' => $student['id_mahasiswa'],
                        'nim' => 'NIM-BERBEDA-DARI-DAFTAR',
                        'nisn' => '1234567890',
                        'tanggal_lahir' => '2005-04-03',
                    ], [
                        'id_mahasiswa' => '3d2e2352-4379-48f8-93cc-8b62cff559a1',
                        'nim' => 'NIM-TIDAK-TERDAFTAR',
                        'nisn' => '9999999999',
                    ]]], 200),
                    default => Http::response(['error_code' => 1, 'error_desc' => 'Unexpected action'], 400),
                };
            },
        ]);

        $preview = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync", ['preview' => true]);

        $preview->assertStatus(200)
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('data.read_count', 1)
            ->assertJsonPath('data.created', 1);
        $this->assertDatabaseMissing('siakad_mahasiswa', ['nim' => 'D25011']);

        $progressId = '4b44e9e0-1851-4b90-ae1f-f79e21e5ae87';
        Cache::put('siakad-mahasiswa-pddikti-sync:' . $progressId, [
            'progress_id' => $progressId,
            'requested_by' => $this->admin->id,
            'status' => 'running',
            'phase' => 'starting',
            'progress' => 0,
        ]);
        $syncService = app(SiakadMahasiswaIntegrationSyncService::class);
        $sync = $syncService->syncPddikti($integration, progressId: $progressId, actorId: $this->admin->id);
        $syncService->completeProgress($progressId, $sync);
        $this->assertFalse($sync['preview']);
        $this->assertFalse($sync['read_only']);
        $this->assertSame(1, $sync['read_count']);
        $this->assertSame(1, $sync['created']);
        $this->assertSame(1, $sync['synced']);
        $this->assertSame(0, $sync['skipped']);
        $this->assertSame(1, $sync['biodata_updated']);
        $this->assertSame(1, $sync['biodata_updated']);
        $this->assertSame(1, $sync['biodata_skipped']);
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetListMahasiswa'
            && (int) (json_decode($request->body(), true)['limit'] ?? 0) === 500
            && (int) (json_decode($request->body(), true)['offset'] ?? -1) === 0);
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetBiodataMahasiswa'
            && (int) (json_decode($request->body(), true)['limit'] ?? 0) === 500
            && (int) (json_decode($request->body(), true)['offset'] ?? -1) === 0);
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetCountMahasiswa');
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetCountBiodataMahasiswa');
        $this->assertSame('completed', Cache::get('siakad-mahasiswa-pddikti-sync:' . $progressId)['status']);
        $this->assertSame(100, Cache::get('siakad-mahasiswa-pddikti-sync:' . $progressId)['progress']);
        $this->assertSame(
            Cache::get('siakad-mahasiswa-pddikti-sync:' . $progressId)['work_total'],
            Cache::get('siakad-mahasiswa-pddikti-sync:' . $progressId)['work_processed']
        );
        $this->assertDatabaseHas('siakad_mahasiswa', [
            'nim' => 'D25011',
            'id_feeder' => $student['id_mahasiswa'],
            'id_feeder_biodata' => $student['id_mahasiswa'],
            'id_feeder_riwayat' => $student['id_registrasi_mahasiswa'],
            'program_studi_id' => ProgramStudi::where('id_feeder', $student['id_prodi'])->value('id'),
            'nisn' => '1234567890',
            'user_id' => null,
        ]);
        $studentId = Mahasiswa::where('nim', 'D25011')->value('id');
        $this->assertDatabaseHas('core_audit_logs', [
            'user_id' => $this->admin->id,
            'module' => 'SIAKAD',
            'table_name' => 'siakad_mahasiswa',
            'record_id' => $studentId,
        ]);
        $this->assertSame(0, User::where('username', 'D25011')->count());
        $rerun = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);
        $this->assertSame(0, $rerun['created']);
        $this->assertSame(1, $rerun['updated']);
        $this->assertSame(1, Mahasiswa::where('nim', 'D25011')->count());
    }

    public function test_pddikti_student_sync_uses_nim_for_legacy_student_without_feeder_ids(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        $programId = 'a3323a44-49b3-4e7b-b887-2d89a14b0c61';
        $sourceId = '5b24f773-988a-49c8-9d44-21f4f9f1de42';
        ProgramStudi::create([
            'id_feeder' => $programId,
            'source_id' => $programId,
            'kode_prodi' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $legacyStudent = Mahasiswa::create([
            'program_studi_id' => ProgramStudi::where('id_feeder', $programId)->value('id'),
            'nim' => 'LEGACY-001',
            'nama_lengkap' => 'Nama Lokal Lama',
            'angkatan' => 2020,
            'status' => 'A',
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) use ($sourceId, $programId) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => 1], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 0], 200),
                    'GetListMahasiswa' => Http::response(['error_code' => 0, 'data' => [[
                        'id_mahasiswa' => $sourceId,
                        'id_prodi' => $programId,
                        'nim' => 'LEGACY-001',
                        'nama_mahasiswa' => 'Nama dari PDDIKTI',
                        'id_periode' => '20251',
                        'id_periode' => '20251',
                        'id_status_mahasiswa' => 'A',
                        'nama_status_mahasiswa' => 'Aktif',
                    ]]], 200),
                    default => Http::response(['error_code' => 1], 400),
                };
            },
        ]);

        $service = app(SiakadMahasiswaIntegrationSyncService::class);
        $preview = $service->syncPddikti($integration, preview: true);

        $this->assertSame(1, $preview['updated']);
        $this->assertSame(0, $preview['created']);
        $this->assertNull($legacyStudent->fresh()->id_feeder);

        $result = $service->syncPddikti($integration, actorId: $this->admin->id);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, Mahasiswa::where('nim', 'LEGACY-001')->count());
        $this->assertDatabaseHas('siakad_mahasiswa', [
            'id' => $legacyStudent->id,
            'nim' => 'LEGACY-001',
            'nama_lengkap' => 'Nama dari PDDIKTI',
            'id_feeder' => $sourceId,
            'id_feeder_biodata' => $sourceId,
        ]);
    }

    public function test_pddikti_student_sync_skips_status_without_approved_local_mapping(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        ProgramStudi::create([
            'id_feeder' => 'bcd9d6cc-a3b3-45ab-b181-0d253f9bd00e',
            'source_id' => 'bcd9d6cc-a3b3-45ab-b181-0d253f9bd00e',
            'kode_prodi' => '55202',
            'nama' => 'Program Uji',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => 1], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 0], 200),
                    'GetListMahasiswa' => Http::response(['error_code' => 0, 'data' => [[
                        'id_mahasiswa' => '16000127-8e52-44c7-9c6e-7eeeb96c1249',
                        'id_prodi' => 'bcd9d6cc-a3b3-45ab-b181-0d253f9bd00e',
                        'nim' => 'MUTASI-001',
                        'nama_mahasiswa' => 'Status Belum Dipetakan',
                        'id_periode' => '20251',
                        'id_periode' => '20251',
                        'id_status_mahasiswa' => 'INVALID',
                        'nama_status_mahasiswa' => 'Status Tidak Dikenal',
                    ]]], 200),
                    default => Http::response(['error_code' => 1], 400),
                };
            },
        ]);

        $response = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);

        $this->assertSame(0, $response['created']);
        $this->assertSame(1, $response['skipped']);
        $this->assertSame('Kode id_status_mahasiswa tidak ditemukan di kamus GetStatusMahasiswa PDDIKTI.', $response['skipped_items'][0]['reason']);
        $this->assertDatabaseMissing('siakad_mahasiswa', ['nim' => 'MUTASI-001']);
    }

    public function test_pddikti_student_sync_is_queued_and_progress_is_private_to_requester(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        config(['queue.default' => 'database']);
        Queue::fake();

        $queued = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $queued->assertStatus(202)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['progress_id']]);
        $progressId = $queued->json('data.progress_id');
        Queue::assertPushedOn('default', SyncPddiktiMahasiswaJob::class, fn (SyncPddiktiMahasiswaJob $job): bool => $job->progressId === $progressId
            && $job->requestedBy === $this->admin->id);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/admin/api-integrations/student-sync-progress/{$progressId}")
            ->assertOk()
            ->assertJsonPath('data.progress_id', $progressId)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.progress', 0);

        $managerRole = Role::create(['name' => 'API Manager', 'slug' => 'api-manager', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['slug' => 'iam.api_integration.manage'],
            ['name' => 'Kelola Pengaturan API', 'module' => 'iam', 'action' => 'update']
        );
        $managerRole->permissions()->syncWithoutDetaching([$permission->id]);
        $this->regularUser->roles()->syncWithoutDetaching([$managerRole->id]);
        $this->actingAs($this->regularUser, 'api')
            ->getJson("/api/admin/api-integrations/student-sync-progress/{$progressId}")
            ->assertForbidden();
    }

    public function test_pddikti_student_sync_uses_count_and_500_offsets_for_list_and_biodata(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => ['count' => 501]], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => ['total' => 501]], 200),
                    'GetListMahasiswa', 'GetBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => []], 200),
                    default => Http::response(['error_code' => 1], 400),
                };
            },
        ]);

        $result = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);

        $this->assertSame(0, $result['created']);
        $this->assertSame(501, $result['source_total']);
        $this->assertSame(501, $result['biodata_total']);
        Http::assertSent(fn (Request $request): bool => in_array((json_decode($request->body(), true)['act'] ?? null), ['GetCountMahasiswa', 'GetCountBiodataMahasiswa'], true));
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetListMahasiswa'
            && (int) (json_decode($request->body(), true)['limit'] ?? 0) === 500
            && (int) (json_decode($request->body(), true)['offset'] ?? -1) === 500);
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetBiodataMahasiswa'
            && (int) (json_decode($request->body(), true)['limit'] ?? 0) === 500
            && (int) (json_decode($request->body(), true)['offset'] ?? -1) === 500);
    }

    public function test_ineligible_duplicate_does_not_block_later_eligible_student_record(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        ProgramStudi::create([
            'id_feeder' => 'd35f4ec7-9689-4471-99b9-bcf60ea96ac2',
            'source_id' => 'd35f4ec7-9689-4471-99b9-bcf60ea96ac2',
            'kode_prodi' => '55203',
            'nama' => 'Program Regresi',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');

        $ineligibleRow = [
            'id_mahasiswa' => '3cab7912-3fbf-4d80-bdcb-fac565e72425',
            'id_prodi' => 'd35f4ec7-9689-4471-99b9-bcf60ea96ac2',
            'nim' => 'REG-DUP-001',
            'nama_mahasiswa' => 'Mahasiswa Status Lama',
            'id_periode' => '20251',
            'id_status_mahasiswa' => 'U',
            'nama_status_mahasiswa' => 'Menunggu Ujian',
        ];
        $eligibleRow = array_merge($ineligibleRow, [
            'nama_mahasiswa' => 'Mahasiswa Status Aktif',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
        ]);

        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) use ($ineligibleRow, $eligibleRow) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => 2], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 0], 200),
                    'GetListMahasiswa' => Http::response(['error_code' => 0, 'data' => [$ineligibleRow, $eligibleRow]], 200),
                    default => Http::response(['error_code' => 1], 400),
                };
            },
        ]);

        $response = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);

        $this->assertSame(2, $response['source_total']);
        $this->assertSame(1, $response['created']);
        $this->assertSame(1, $response['skipped']);
        $this->assertDatabaseHas('siakad_mahasiswa', [
            'nim' => 'REG-DUP-001',
            'nama_lengkap' => 'Mahasiswa Status Aktif',
            'status' => 'A',
        ]);
    }

    public function test_pddikti_semester_integration_is_tested_and_synced_from_api_settings(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_tahun_akademik')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);

        DB::table('siakad_tahun_akademik')->insert([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil Lokal',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
            'mode_penilaian' => 'semi_obe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('siakad_tahun_akademik')->insert([
            'kode' => '20262',
            'nama' => '2026/2027 Genap Lokal',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => false,
            'mode_penilaian' => 'semi_obe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetSemester' => Http::response(['error_code' => 0, 'data' => [
                        [
                            'id_semester' => '20261',
                            'id_tahun_ajaran' => 2027,
                            'nama_semester' => '2026/2027 Ganjil',
                            'semester' => 1,
                            'a_periode_aktif' => 1,
                            'tanggal_mulai' => '2026-09-01 00:00:00',
                            'tanggal_selesai' => '2027-01-31 00:00:00',
                        ],
                        [
                            'id_semester' => '20263',
                            'id_tahun_ajaran' => 2027,
                            'nama_semester' => '2027/2028 Ganjil',
                            'semester' => 1,
                            'a_periode_aktif' => 0,
                            'tanggal_mulai' => '2027-09-01 00:00:00',
                            'tanggal_selesai' => '2028-01-31 00:00:00',
                        ],
                    ]], 200),
                    default => Http::response(['error_code' => 1, 'error_desc' => 'Unexpected action'], 400),
                };
            },
        ]);

        $test = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/test");
        $test->assertStatus(200)
            ->assertJsonPath('data.is_success', true)
            ->assertJsonPath('data.response_sample.action', 'GetSemester');

        $sync = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $sync->assertStatus(200)
            ->assertJsonPath('data.source_total', 2)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.synced', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseHas('siakad_tahun_akademik', [
            'kode' => '20261',
            'id_feeder' => '20261',
            'nama' => '2026/2027 Ganjil Lokal',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('siakad_tahun_akademik', [
            'kode' => '20263',
            'id_feeder' => '20263',
            'nama' => '2027/2028 Ganjil',
            'is_active' => false,
        ]);
        Http::assertSent(fn (Request $request): bool => (json_decode($request->body(), true)['act'] ?? null) === 'GetSemester'
            && (int) (json_decode($request->body(), true)['limit'] ?? 0) === 500
            && (int) (json_decode($request->body(), true)['offset'] ?? -1) === 0);

        $repeat = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $repeat->assertStatus(200)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.synced', 2)
            ->assertJsonPath('data.skipped', 0);
    }

    public function test_pddikti_student_connection_test_checks_list_and_biodata_without_writing_students(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) {
                $payload = json_decode($request->body(), true);
                return match ($payload['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetListMahasiswa', 'GetBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => []], 200),
                    default => Http::response(['error_code' => 1], 400),
                };
            },
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_success', true)
            ->assertJsonPath('data.response_sample.action', 'GetListMahasiswa');
        $this->assertSame(0, Mahasiswa::count());
    }

    public function test_pddikti_curriculum_sync_maps_programs_and_is_idempotent(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_kurikulum',
            'name' => 'Migrasi Kurikulum SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi kurikulum Neo Feeder.',
            'expected_schema' => ['type' => 'object'],
            'source_type' => 'pddikti',
            'supported_sources' => ['pddikti'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetListKurikulum', 'target_entity' => 'kurikulum']],
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        $programStudiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'TI-KUR-TEST',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
            'id_feeder' => 'prodi-curriculum-uuid',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [
                [
                    'id_kurikulum' => 'curriculum-uuid-1',
                    'nama_kurikulum' => 'Kurikulum TI 2024',
                    'id_prodi' => 'prodi-curriculum-uuid',
                    'nama_program_studi' => 'Teknik Informatika',
                    'id_semester' => '20241',
                    'semester_mulai_berlaku' => '2024/2025 Ganjil',
                    'jumlah_sks_lulus' => 144,
                    'jumlah_sks_wajib' => 120,
                ],
                [
                    'id_kurikulum' => 'curriculum-uuid-unmapped',
                    'nama_kurikulum' => 'Kurikulum Prodi Belum Ada',
                    'id_prodi' => 'unknown-program-uuid',
                    'nama_program_studi' => 'Program Belum Dipetakan',
                    'id_semester' => '20241',
                    'semester_mulai_berlaku' => '2024/2025 Ganjil',
                    'jumlah_sks_lulus' => 120,
                    'jumlah_sks_wajib' => 100,
                ],
            ]], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_kurikulum' => 'curriculum-uuid-1',
                'nama_kurikulum' => 'Kurikulum TI 2024 Revisi',
                'id_prodi' => 'prodi-curriculum-uuid',
                'nama_program_studi' => 'Teknik Informatika',
                'id_semester' => '20241',
                'semester_mulai_berlaku' => '2024/2025 Ganjil',
                'jumlah_sks_lulus' => 144,
                'jumlah_sks_wajib' => 120,
            ]]], 200);

        $firstSync = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $firstSync->assertStatus(200)
            ->assertJsonPath('data.source_total', 2)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_items.0.reason', 'Program studi PDDIKTI belum dipetakan ke ID Feeder di SIAKAD.');

        $this->assertDatabaseHas('siakad_kurikulum', [
            'program_studi_id' => $programStudiId,
            'id_feeder' => 'curriculum-uuid-1',
            'kode' => 'PDDIKTI-curriculum-uuid-1',
            'nama' => 'Kurikulum TI 2024',
            'tahun_berlaku' => 2024,
            'total_sks_lulus' => 144,
        ]);

        $secondSync = $this->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $secondSync->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(1, DB::table('siakad_kurikulum')->where('id_feeder', 'curriculum-uuid-1')->count());
        $this->assertDatabaseHas('siakad_kurikulum', [
            'id_feeder' => 'curriculum-uuid-1',
            'nama' => 'Kurikulum TI 2024 Revisi',
        ]);
    }

    public function test_pddikti_curriculum_registry_imports_get_list_curriculum_before_courses(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_kurikulum')->firstOrFail();
        $sourceSchema = $integration->source_schemas['pddikti'];

        $this->assertSame('pddikti', $integration->source_type);
        $this->assertSame(['pddikti'], $integration->supported_sources);
        $this->assertSame('GetListKurikulum', $sourceSchema['action']);
        $this->assertSame('kurikulum', $sourceSchema['target_entity']);

        $programStudiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'TI-KUR-TEST',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
            'id_feeder' => 'prodi-curriculum-uuid',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_kurikulum' => 'curriculum-uuid-1',
                'nama_kurikulum' => 'Kurikulum TI 2024',
                'id_prodi' => 'prodi-curriculum-uuid',
                'nama_program_studi' => 'Teknik Informatika',
                'id_semester' => '20241',
                'semester_mulai_berlaku' => '2024/2025 Ganjil',
                'jumlah_sks_lulus' => 144,
                'jumlah_sks_wajib' => 120,
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('siakad_kurikulum', [
            'program_studi_id' => $programStudiId,
            'id_feeder' => 'curriculum-uuid-1',
            'kode' => 'PDDIKTI-curriculum-uuid-1',
            'tahun_berlaku' => 2024,
            'total_sks_lulus' => 144,
        ]);
    }

    public function test_pddikti_curriculum_connection_test_uses_get_list_curriculum_without_touching_sync_state(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_kurikulum')->firstOrFail();
        $integration->update([
            'supported_sources' => ['pddikti'],
            'source_schemas' => ['pddikti' => [
                'connection_managed' => true,
                'action' => 'GetListKurikulum',
                'target_entity' => 'kurikulum',
            ]],
            'last_synced_at' => '2026-11-02 08:00:00',
            'last_status' => 'failed',
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => []], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('data.response_sample.action', 'GetListKurikulum')
            ->assertJsonMissing(['token' => 'runtime-feeder-token']);
        $this->assertDatabaseMissing('siakad_kurikulum', ['id_feeder' => 'curriculum-uuid-1']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'GetListKurikulum'));
        $this->assertSame('2026-11-02 08:00:00', $integration->fresh()->last_synced_at->format('Y-m-d H:i:s'));
        $this->assertSame('failed', $integration->fresh()->last_status);
    }

    public function test_pddikti_course_sync_skips_when_curriculum_has_not_been_synced(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mata_kuliah')->firstOrFail();
        $programStudiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'TI-NO-KUR',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'D3',
            'id_feeder' => 'prodi-no-curriculum',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => [[
                'id_kurikulum' => 'not-yet-mapped-curriculum',
                'nama_kurikulum' => 'Kurikulum belum dimigrasikan',
                'id_matkul' => 'course-without-curriculum',
                'kode_mata_kuliah' => 'TI-NOKUR-01',
                'nama_mata_kuliah' => 'Mata Kuliah Belum Terpetakan',
                'id_prodi' => 'prodi-no-curriculum',
                'semester' => 1,
                'sks_mata_kuliah' => 3,
                'sks_tatap_muka' => 2,
                'sks_praktek' => 1,
                'apakah_wajib' => 1,
            ]]], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_items.0.reason', 'Kurikulum PDDIKTI belum dimigrasikan. Jalankan sinkronisasi kurikulum terlebih dahulu.');
        $this->assertDatabaseMissing('siakad_mata_kuliah', ['kode_mk' => 'TI-NOKUR-01']);
        $this->assertDatabaseMissing('siakad_kurikulum', ['program_studi_id' => $programStudiId]);
    }

    public function test_pddikti_connection_test_uses_neo_feeder_settings_without_exposing_token(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_mata_kuliah',
            'name' => 'Migrasi Mata Kuliah SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi mata kuliah PDDIKTI.',
            'expected_schema' => ['type' => 'object'],
            'source_type' => 'pddikti',
            'supported_sources' => ['pddikti', 'custom_api'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetMatkulKurikulum']],
            'auth_type' => 'bearer',
            'is_active' => true,
            'last_synced_at' => '2026-11-01 12:00:00',
            'last_status' => 'failed',
        ]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fakeSequence('https://feeder.example.test/ws/live2.php')
            ->push(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
            ->push(['error_code' => 0, 'data' => []], 200);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_success', true)
            ->assertJsonMissing(['token' => 'runtime-feeder-token']);
        $this->assertSame('2026-11-01 12:00:00', $integration->fresh()->last_synced_at->format('Y-m-d H:i:s'));
        $this->assertSame('failed', $integration->fresh()->last_status);
    }

    public function test_switching_course_source_to_pddikti_clears_custom_url_and_preserves_unsent_token(): void
    {
        $integration = IamApiIntegration::create([
            'key' => 'siakad_mata_kuliah',
            'name' => 'Migrasi Mata Kuliah SIAKAD',
            'module' => 'siakad',
            'http_method' => 'GET',
            'description' => 'Sinkronisasi mata kuliah.',
            'expected_schema' => ['type' => 'object'],
            'source_type' => 'custom_api',
            'supported_sources' => ['pddikti', 'custom_api'],
            'source_schemas' => ['pddikti' => ['connection_managed' => true, 'action' => 'GetMatkulKurikulum']],
            'url_api' => 'https://custom.example.test/api/courses',
            'token' => 'custom-source-token',
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/admin/api-integrations/{$integration->id}", [
                'source_type' => 'pddikti',
                'url_api' => null,
                'auth_type' => 'bearer',
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.source_type', 'pddikti')
            ->assertJsonPath('data.token_configured', true);
        $this->assertDatabaseHas('iam_api_integrations', [
            'id' => $integration->id,
            'source_type' => 'pddikti',
            'url_api' => null,
        ]);
        $this->assertSame('custom-source-token', $integration->fresh()->token);
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
