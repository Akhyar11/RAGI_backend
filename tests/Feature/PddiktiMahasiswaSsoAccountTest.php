<?php

namespace Tests\Feature;

use App\Models\IamApiIntegration;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MahasiswaStatus;
use App\Models\Siakad\ProgramStudi;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IAM\MahasiswaSsoAccountService;
use App\Services\IAM\SiakadMahasiswaIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PddiktiMahasiswaSsoAccountTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_PASSWORD = 'kata-sandi-uji-bawaan';

    private Role $role;
    private ProgramStudi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        config([
            'iam.sso.mahasiswa_default_password' => self::DEFAULT_PASSWORD,
            'iam.sso.placeholder_email_domain' => 'campus.test',
        ]);
        $this->role = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa', 'is_active' => true]);
        $this->prodi = ProgramStudi::query()->create([
            'source_id' => 'a3323a44-49b3-4e7b-b887-2d89a14b0c61',
            'id_feeder' => 'a3323a44-49b3-4e7b-b887-2d89a14b0c61',
            'kode_prodi' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
    }

    private function student(string $nim, array $attributes = []): Mahasiswa
    {
        return Mahasiswa::query()->create($attributes + [
            'program_studi_id' => $this->prodi->id,
            'id_feeder' => 'feeder-' . $nim,
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa ' . $nim,
            'angkatan' => 2025,
            'status' => MahasiswaStatus::AKTIF,
        ]);
    }

    public function test_creates_account_with_nim_username_default_password_role_and_required_fields(): void
    {
        $student = $this->student('D25001', ['email' => 'Budi@Example.com', 'telepon' => '081234567890']);

        $result = app(MahasiswaSsoAccountService::class)->provisionPending();

        $this->assertSame(1, $result['sso_created']);
        $user = User::where('username', 'D25001')->firstOrFail();
        $this->assertSame('Mahasiswa D25001', $user->name);
        $this->assertSame('budi@example.com', $user->email);
        $this->assertSame('081234567890', $user->phone);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_verified);
        $this->assertNotEmpty($user->referral_code);
        $this->assertTrue(Hash::check(self::DEFAULT_PASSWORD, $user->password));
        $this->assertNotSame(self::DEFAULT_PASSWORD, $user->password);
        $this->assertTrue($user->roles->contains('id', $this->role->id));
        $this->assertSame($user->id, $student->fresh()->user_id);
    }

    public function test_falls_back_to_nim_email_when_feeder_email_is_missing_invalid_or_taken(): void
    {
        User::create(['username' => 'lain', 'email' => 'dipakai@example.com', 'password' => 'x', 'is_active' => true, 'is_verified' => true]);
        $this->student('D25002');
        $this->student('D25003', ['email' => 'bukan-email']);
        $this->student('D25004', ['email' => 'dipakai@example.com']);

        $result = app(MahasiswaSsoAccountService::class)->provisionPending();

        $this->assertSame(3, $result['sso_created']);
        $this->assertSame('d25002@campus.test', User::where('username', 'D25002')->value('email'));
        $this->assertSame('d25003@campus.test', User::where('username', 'D25003')->value('email'));
        $this->assertSame('d25004@campus.test', User::where('username', 'D25004')->value('email'));
    }

    public function test_non_active_student_gets_inactive_account_so_alumni_cannot_log_in(): void
    {
        $this->student('D25005', ['status' => MahasiswaStatus::NON_AKTIF]);
        $this->student('D25006', ['status' => MahasiswaStatus::CUTI]);

        $result = app(MahasiswaSsoAccountService::class)->provisionPending();

        $this->assertSame(2, $result['sso_created']);
        $this->assertSame(1, $result['sso_inactive']);
        $this->assertFalse((bool) User::where('username', 'D25005')->value('is_active'));
        $this->assertTrue((bool) User::where('username', 'D25006')->value('is_active'));
    }

    public function test_existing_free_account_is_linked_without_changing_its_password(): void
    {
        $existing = User::create([
            'username' => 'D25007', 'email' => 'existing@example.com', 'password' => 'sandi-lama-rahasia',
            'is_active' => true, 'is_verified' => true,
        ]);
        $originalHash = $existing->fresh()->password;
        $student = $this->student('D25007');

        $result = app(MahasiswaSsoAccountService::class)->provisionPending();

        $this->assertSame(1, $result['sso_linked']);
        $this->assertSame(0, $result['sso_created']);
        $this->assertSame($originalHash, $existing->fresh()->password);
        $this->assertSame($existing->id, $student->fresh()->user_id);
        $this->assertTrue($existing->fresh()->roles->contains('id', $this->role->id));
    }

    public function test_conflicting_accounts_are_skipped_with_reason_not_guessed(): void
    {
        $deleted = User::create(['username' => 'D25008', 'email' => 'del@example.com', 'password' => 'x', 'is_active' => true, 'is_verified' => true]);
        $deleted->delete();
        $this->student('D25008');

        $lecturerUser = User::create(['username' => 'D25009', 'email' => 'dosen@example.com', 'password' => 'x', 'is_active' => true, 'is_verified' => true]);
        Dosen::query()->create(['user_id' => $lecturerUser->id, 'nama_lengkap' => 'Dosen Uji']);
        $this->student('D25009');

        $result = app(MahasiswaSsoAccountService::class)->provisionPending();

        $this->assertSame(0, $result['sso_created']);
        $this->assertSame(2, $result['sso_skipped']);
        $this->assertCount(2, $result['sso_skipped_items']);
        $this->assertNull(Mahasiswa::where('nim', 'D25008')->value('user_id'));
        $this->assertNull(Mahasiswa::where('nim', 'D25009')->value('user_id'));
    }

    public function test_rerun_is_idempotent_and_does_not_touch_manual_students_without_feeder_id(): void
    {
        $this->student('D25010');
        Mahasiswa::query()->create([
            'program_studi_id' => $this->prodi->id, 'nim' => 'MANUAL1', 'nama_lengkap' => 'Manual',
            'angkatan' => 2025, 'status' => MahasiswaStatus::AKTIF,
        ]);
        $service = app(MahasiswaSsoAccountService::class);

        $first = $service->provisionPending();
        $second = $service->provisionPending();

        $this->assertSame(1, $first['sso_created']);
        $this->assertSame(0, $second['sso_created']);
        $this->assertSame(1, User::where('username', 'D25010')->count());
        $this->assertSame(0, User::where('username', 'MANUAL1')->count());
    }

    public function test_nothing_is_created_when_default_password_is_not_configured(): void
    {
        config(['iam.sso.mahasiswa_default_password' => null]);
        $this->student('D25011');

        $service = app(MahasiswaSsoAccountService::class);
        $result = $service->provisionPending();

        $this->assertFalse($service->isConfigured());
        $this->assertSame(0, $result['sso_created']);
        $this->assertSame(0, User::where('username', 'D25011')->count());
    }

    public function test_full_pddikti_sync_creates_accounts_after_biodata_using_feeder_email_and_phone(): void
    {
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti', 'url_api' => null, 'token' => null]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');

        $student = [
            'id_mahasiswa' => '5b24f773-988a-49c8-9d44-21f4f9f1de42',
            'id_registrasi_mahasiswa' => '088adb51-ed04-4609-9aa5-f96201192704',
            'id_prodi' => $this->prodi->id_feeder,
            'nim' => 'D25012',
            'nama_mahasiswa' => 'Mahasiswa Sync SSO',
            'jenis_kelamin' => 'L',
            'id_periode' => '20251',
            'id_status_mahasiswa' => 'A',
            'nama_status_mahasiswa' => 'Aktif',
        ];
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) use ($student) {
                return match (json_decode($request->body(), true)['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200),
                    'GetCountMahasiswa' => Http::response(['error_code' => 0, 'data' => 1], 200),
                    'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 1], 200),
                    'GetListMahasiswa' => Http::response(['error_code' => 0, 'data' => [$student]], 200),
                    'GetBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => [[
                        'id_mahasiswa' => $student['id_mahasiswa'],
                        'email' => 'mahasiswa.sso@example.com',
                        'handphone' => '081200000001',
                    ]]], 200),
                    default => Http::response(['error_code' => 1, 'error_desc' => 'Unexpected action'], 400),
                };
            },
        ]);

        $sync = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);
        // Sync ulang idempoten: tidak membuat akun ganda.
        $again = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);

        $this->assertSame(1, $sync['sso_created']);
        $this->assertSame(0, $again['sso_created']);
        $this->assertNull($sync['sso_note']);
        $user = User::where('username', 'D25012')->firstOrFail();
        $this->assertSame('mahasiswa.sso@example.com', $user->email);
        $this->assertSame('081200000001', $user->phone);
        $this->assertSame($user->id, Mahasiswa::where('nim', 'D25012')->value('user_id'));
        $this->assertTrue(Hash::check(self::DEFAULT_PASSWORD, $user->password));
    }

    public function test_sync_reports_note_and_still_succeeds_when_password_is_not_configured(): void
    {
        config(['iam.sso.mahasiswa_default_password' => null]);
        app(IamApiIntegrationSeeder::class)->run();
        $integration = IamApiIntegration::where('key', 'siakad_mahasiswa')->firstOrFail();
        $integration->update(['source_type' => 'pddikti', 'url_api' => null, 'token' => null]);
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) {
                return match (json_decode($request->body(), true)['act'] ?? null) {
                    'GetToken' => Http::response(['error_code' => 0, 'data' => ['token' => 't']], 200),
                    'GetCountMahasiswa', 'GetCountBiodataMahasiswa' => Http::response(['error_code' => 0, 'data' => 0], 200),
                    default => Http::response(['error_code' => 0, 'data' => []], 200),
                };
            },
        ]);

        $sync = app(SiakadMahasiswaIntegrationSyncService::class)->syncPddikti($integration);

        $this->assertSame(0, $sync['sso_created']);
        $this->assertStringContainsString('SSO_DEFAULT_MAHASISWA_PASSWORD', (string) $sync['sso_note']);
    }
}
