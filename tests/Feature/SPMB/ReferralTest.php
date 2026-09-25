<?php

namespace Tests\Feature\SPMB;

use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\ReferralUsage;
use App\Models\User;
use App\Services\Spmb\SpmbPendaftaranService;
use App\Services\Spmb\SpmbReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    private User $referrer;

    private GelombangPenerimaan $gelombang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        // Siapkan Passport personal access client untuk endpoint /auth/register.
        $this->setUpPassport();

        $this->referrer = User::factory()->create([
            'username' => 'alumni_referrer',
            'name' => 'Budi Santoso',
            'referral_code' => 'REF-ABC123',
            'is_active' => true,
        ]);

        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $this->gelombang = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang 1',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-06-30',
            'status' => 'aktif',
        ]);

        MasterProgramStudi::create([
            'kode_prodi' => 'TI01',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
    }

    private function makePendaftaran(User $user, array $overrides = []): PendaftaranCalonMhs
    {
        return PendaftaranCalonMhs::create(array_merge([
            'gelombang_id' => $this->gelombang->id,
            'user_id' => $user->id,
            'program_studi_id' => 1,
            'no_pendaftaran' => 'REG-TEST-'.uniqid(),
            'nama_lengkap' => $user->username,
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'status' => PendaftaranCalonMhs::STATUS_DRAFT,
            'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
        ], $overrides));
    }

    public function test_validasi_kode_referral_publik_berhasil(): void
    {
        $response = $this->getJson('/api/spmb/referral/validate?code=ref-abc123');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.referral_code', 'REF-ABC123');

        // Nama harus tersamar, bukan utuh.
        $this->assertStringNotContainsString('Budi Santoso', $response->json('data.referrer_name'));
    }

    public function test_validasi_kode_referral_tidak_ditemukan_gagal(): void
    {
        $this->getJson('/api/spmb/referral/validate?code=REF-TIDAKADA')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_register_dengan_kode_referral_membuat_usage(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'username' => 'camaba_baru',
            'email' => 'camaba.baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '081234567890',
            'referral_code' => 'REF-ABC123',
        ]);

        $response->assertStatus(201);

        $referee = User::where('username', 'camaba_baru')->firstOrFail();
        $pendaftaran = PendaftaranCalonMhs::where('user_id', $referee->id)->firstOrFail();

        $this->assertSame('REF-ABC123', $pendaftaran->used_referral_code);
        $this->assertSame($this->referrer->id, $pendaftaran->referrer_user_id);
        $this->assertNotNull($pendaftaran->referral_validated_at);

        $this->assertDatabaseHas('spmb_referral_usages', [
            'referee_user_id' => $referee->id,
            'referrer_user_id' => $this->referrer->id,
            'referral_code' => 'REF-ABC123',
            'status' => ReferralUsage::STATUS_CLAIMED,
        ]);
    }

    public function test_register_dengan_kode_referral_invalid_ditolak(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'username' => 'camaba_gagal',
            'email' => 'camaba.gagal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'REF-NOTFOUND',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('core_users', ['username' => 'camaba_gagal']);
    }

    public function test_tidak_dapat_menggunakan_kode_referral_sendiri(): void
    {
        Passport::actingAs($this->referrer);

        $this->makePendaftaran($this->referrer, ['nik' => '1111111111111111']);

        $this->postJson('/api/spmb/pendaftaran/biodata', [
            'used_referral_code' => 'REF-ABC123',
        ])->assertStatus(422);
    }

    public function test_referral_qualified_saat_lulus_administrasi(): void
    {
        $referee = User::factory()->create(['is_active' => true]);
        $pendaftaran = $this->makePendaftaran($referee, ['nik' => '2222222222222222']);

        app(SpmbReferralService::class)
            ->attachToPendaftaran($pendaftaran, 'REF-ABC123');

        $usage = ReferralUsage::where('pendaftaran_id', $pendaftaran->id)->firstOrFail();
        $this->assertSame(ReferralUsage::STATUS_CLAIMED, $usage->status);

        $pendaftaran->update(['status' => PendaftaranCalonMhs::STATUS_SUBMITTED]);

        app(SpmbPendaftaranService::class)->verifikasiAdministrasi(
            $pendaftaran,
            true,
            'Berkas lengkap',
            $this->referrer->id
        );

        $usage->refresh();
        $this->assertSame(ReferralUsage::STATUS_QUALIFIED, $usage->status);
        $this->assertNotNull($usage->qualified_at);
    }

    public function test_laporan_referral_mendukung_filter_dan_sort_relasi(): void
    {
        $referee = User::factory()->create(['username' => 'referee_satu', 'name' => 'Referee Satu', 'is_active' => true]);
        $pendaftaran = $this->makePendaftaran($referee, ['nik' => '3333333333333333', 'nama_lengkap' => 'Referee Satu']);

        app(SpmbReferralService::class)->attachToPendaftaran($pendaftaran, 'REF-ABC123');

        $role = \App\Models\Role::create(['name' => 'Admin SPMB', 'slug' => 'admin_spmb_test', 'is_active' => true]);
        $permission = \App\Models\Permission::create(['name' => 'Kelola SPMB', 'slug' => 'spmb.manage', 'module' => 'spmb', 'action' => 'update']);
        \App\Models\RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($role->id);

        Passport::actingAs($admin);

        $this->getJson('/api/spmb/laporan/referral?sort_by=referrer&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('data.0.referral_code', 'REF-ABC123');

        $this->getJson('/api/spmb/laporan/referral?referrer=Budi&gelombang_id='.$this->gelombang->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/spmb/laporan/referral?pendaftar=TidakAdaNamaIni')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/spmb/laporan/referral-summary')
            ->assertOk()
            ->assertJsonPath('data.claimed', 1);
    }
}
