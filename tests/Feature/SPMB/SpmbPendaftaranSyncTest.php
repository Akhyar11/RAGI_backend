<?php

namespace Tests\Feature\SPMB;

use App\Models\IamApiIntegration;
use App\Models\MasterTipeJalur;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpmbPendaftaranSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected IamApiIntegration $integration;
    protected ProgramStudi $prodi;
    protected GelombangPenerimaan $gelombang;
    protected MasterTipeJalur $tipeJalur;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        // 1. Role dan Akun Admin SPMB
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin_spmb'],
            ['name' => 'Admin SPMB', 'is_active' => true]
        );
        $permission = Permission::firstOrCreate(
            ['slug' => 'spmb.manage'],
            ['name' => 'Kelola Admin SPMB', 'module' => 'spmb', 'action' => 'update']
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->admin = User::create([
            'username'    => 'admin_spmb_test',
            'email'       => 'admin_spmb@test.com',
            'password'    => bcrypt('password'),
            'is_active'   => true,
            'is_verified' => true,
        ]);
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // 2. Role Calon Mahasiswa (Wajib ada di sistem)
        Role::firstOrCreate(
            ['slug' => 'calon_mhs'],
            ['name' => 'Calon Mahasiswa', 'is_active' => true]
        );

        // 3. User Biasa (Tanpa Izin)
        $this->regularUser = User::create([
            'username'    => 'regular_test',
            'email'       => 'regular@test.com',
            'password'    => bcrypt('password'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        // 4. Konfigurasi IAM API Integration
        $this->integration = IamApiIntegration::firstOrCreate(
            ['key' => 'spmb_pendaftaran'],
            [
                'name'            => 'SPMB Pendaftaran',
                'module'          => 'spmb',
                'http_method'     => 'GET',
                'description'     => 'Endpoint API migrasi pendaftaran',
                'url_api'         => 'https://external-spmb.test/api/pendaftaran',
                'token'           => 'mock-secret-token',
                'auth_type'       => 'bearer',
                'is_active'       => true,
                'expected_schema' => ['type' => 'array'],
            ]
        );
        $this->integration->update([
            'url_api'   => 'https://external-spmb.test/api/pendaftaran',
            'token'     => 'mock-secret-token',
            'is_active' => true,
        ]);

        // 5. Master Data Penunjang
        $this->prodi = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'D3-SI'],
            [
                'nama'        => 'D3 Sistem Informasi',
                'jenjang'     => 'D3',
                'is_active'   => true,
            ]
        );

        $jalur = JalurMasuk::firstOrCreate(
            ['kode' => 'REG'],
            ['nama' => 'Jalur Reguler', 'is_active' => true]
        );

        $this->gelombang = GelombangPenerimaan::firstOrCreate(
            ['nama' => 'Gelombang 1 2026'],
            [
                'jalur_masuk_id'    => $jalur->id,
                'tanggal_buka'      => now()->subDays(10)->toDateString(),
                'tanggal_tutup'     => now()->addDays(20)->toDateString(),
                'kuota_total'       => 100,
                'kuota_terisi'      => 0,
                'biaya_pendaftaran' => 200000,
                'status'            => 'aktif',
            ]
        );

        $this->tipeJalur = MasterTipeJalur::firstOrCreate(
            ['kode' => 'REG'],
            ['nama' => 'Reguler']
        );
    }

    public function test_unauthorized_user_cannot_sync_external()
    {
        $response = $this->actingAs($this->regularUser, 'api')
            ->postJson('/api/spmb/pendaftaran/sync-external');

        $response->assertStatus(403);
    }

    public function test_sync_external_successfully_inserts_and_is_idempotent()
    {
        Http::fake([
            'https://external-spmb.test/*' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'no_pendaftaran'    => '202610001',
                        'nama_lengkap'      => 'Ahmad Dahlan',
                        'nik'               => '3372010101990001',
                        'email'             => 'ahmad.dahlan@example.com',
                        'no_hp'             => '081234567890',
                        'tempat_lahir'      => 'Surakarta',
                        'tanggal_lahir'     => '2005-08-17',
                        'jenis_kelamin'     => 'L',
                        'agama'             => 'Islam',
                        'status_sipil'      => 'belum_menikah',
                        'kewarganegaraan'   => 'WNI',
                        'alamat'            => 'Jl. Slamet Riyadi No. 10',
                        'provinsi'          => 'Jawa Tengah',
                        'kota_kabupaten'    => 'Kota Surakarta',
                        'kecamatan'         => 'Banjarsari',
                        'kode_pos'          => '57139',
                        'asal_sekolah'      => 'SMAN 1 Surakarta',
                        'npsn_sekolah'      => '20327981',
                        'jurusan_sekolah'   => 'IPA',
                        'tahun_lulus'       => 2024,
                        'nilai_rata_rapor'  => 88.50,
                        'pilihan_prodi_1'   => 'D3-SI',
                        'gelombang'         => 'Gelombang 1 2026',
                        'jalur_pendaftaran' => 'Reguler',
                        'nama_ayah'         => 'Bambang',
                        'pekerjaan_ayah'    => 'Wiraswasta',
                        'nama_ibu'          => 'Aminah',
                        'pekerjaan_ibu'     => 'Guru',
                        'telp_ortu'         => '081234567891',
                        'status'            => 'submitted',
                        'status_pembayaran' => 'lunas',
                    ],
                ],
            ], 200),
        ]);

        // Eksekusi Pertama: Tarik data pendaftar baru
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/sync-external');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_fetched', 1)
            ->assertJsonPath('data.inserted', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0);

        // Verifikasi pendaftar tersimpan di database
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'no_pendaftaran' => '202610001',
            'nama_lengkap'   => 'Ahmad Dahlan',
            'nik'            => '3372010101990001',
            'status'         => 'submitted',
            'status_pembayaran' => 'lunas',
        ]);

        // Verifikasi akun user calon mahasiswa terbuat
        $this->assertDatabaseHas('core_users', [
            'email' => 'ahmad.dahlan@example.com',
        ]);

        $createdUser = User::where('email', 'ahmad.dahlan@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->roles()->where('slug', 'calon_mhs')->exists());

        // Verifikasi relasi terhubung ke ID master prodi dan gelombang
        $pendaftaran = PendaftaranCalonMhs::where('no_pendaftaran', '202610001')->first();
        $this->assertEquals($this->prodi->id, $pendaftaran->program_studi_id);
        $this->assertEquals($this->gelombang->id, $pendaftaran->gelombang_id);
        $this->assertEquals($createdUser->id, $pendaftaran->user_id);

        // Eksekusi Kedua (Uji Idempoten): Tarik ulang data yang sama
        $response2 = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/sync-external');

        $response2->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_fetched', 1)
            ->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0);

        // Pastikan tidak ada data ganda
        $this->assertEquals(1, PendaftaranCalonMhs::where('no_pendaftaran', '202610001')->count());
    }

    public function test_sync_external_handles_empty_response()
    {
        Http::fake([
            'https://external-spmb.test/*' => Http::response([
                'status' => true,
                'data' => [],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/sync-external');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_fetched', 0)
            ->assertJsonPath('data.inserted', 0)
            ->assertJsonPath('data.updated', 0);
    }

    public function test_sync_external_fails_when_external_api_errors()
    {
        Http::fake([
            'https://external-spmb.test/*' => Http::response([
                'status' => false,
                'message' => 'Internal server error di sistem eksternal',
            ], 500),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/sync-external');

        $response->assertStatus(500)
            ->assertJsonPath('status', 'error');
    }
}
