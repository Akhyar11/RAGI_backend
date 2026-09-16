<?php

namespace Tests\Feature;

use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpegAttendanceTest extends TestCase
{
    protected User $user;
    protected Pegawai $pegawai;
    protected OfficeLocation $office;
    protected ShiftTemplate $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        
        // Seed or create personal access client if needed without prompt
        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            $clientRepository = app(\Laravel\Passport\ClientRepository::class);
            $clientRepository->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->office = OfficeLocation::firstOrCreate(
            ['name' => 'Politeknik Indonusa Surakarta'],
            [
                'address' => 'Jl. KH Samanhudi No.84, Surakarta',
                'latitude' => -7.5675000,
                'longitude' => 110.8036000,
                'radius_meters' => 150,
                'is_active' => true,
            ]
        );

        $this->shift = ShiftTemplate::firstOrCreate(
            ['name' => 'Shift Reguler 5 Hari'],
            [
                'description' => 'Standard shift',
                'is_active' => true,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'max_early_clock_in_minutes' => 60,
                'applies_national_holidays' => true,
            ]
        );

        $this->user = User::firstOrCreate(
            ['email' => 'dosen.test@kampus.ac.id'],
            [
                'username' => 'dosentest',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $this->pegawai = Pegawai::firstOrCreate(
            ['nip' => '199999992026091001'],
            [
                'user_id' => $this->user->id,
                'nama_lengkap' => 'Dosen Peneliti Test',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'office_location_id' => $this->office->id,
                'shift_template_id' => $this->shift->id,
                'is_active' => true,
            ]
        );
    }

    public function test_face_health_endpoint_points_to_port_8001(): void
    {
        $response = $this->getJson('/api/v1/face/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'target_url' => 'http://127.0.0.1:8001',
            ]);
    }

    public function test_mobile_login_with_nip(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '199999992026091001',
            'password' => 'password123',
            'device_name' => 'flutter-test-device',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'success',
                'message' => 'Login berhasil',
            ])
            ->assertJsonStructure([
                'token',
                'access_token',
                'data' => [
                    'token',
                    'access_token',
                    'user' => ['id', 'name', 'email'],
                    'employee' => ['id', 'employee_code', 'office'],
                ],
            ]);
    }

    public function test_mobile_login_with_username_and_email_and_identifier(): void
    {
        // 1. Login with username payload
        $resUsername = $this->postJson('/api/v1/auth/login', [
            'username' => 'dosentest',
            'password' => 'password123',
        ]);
        $resUsername->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'success']);

        // 2. Login with email payload
        $resEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'dosen.test@kampus.ac.id',
            'password' => 'password123',
        ]);
        $resEmail->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'success']);

        // 3. Login with identifier payload
        $resIdentifier = $this->postJson('/api/v1/auth/login', [
            'identifier' => '199999992026091001',
            'password' => 'password123',
        ]);
        $resIdentifier->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'success']);
    }

    public function test_profile_and_me_endpoints(): void
    {
        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        $resProfile = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/profile');
        $resProfile->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'success']);

        $resMe = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');
        $resMe->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'success']);
    }

    public function test_submit_keterangan_izin_from_mobile(): void
    {
        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/keterangan', [
                'tanggal' => '2026-09-25',
                'status_kehadiran' => 'izin',
                'catatan' => 'Izin keperluan keluarga',
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'success' => true,
                'data' => [
                    'status' => 'izin',
                    'notes' => 'Izin keperluan keluarga',
                ],
            ]);
    }

    public function test_today_attendance_and_clock_in_out_flow(): void
    {
        Carbon::setTestNow('2026-09-15 08:05:00'); // Selasa, jam 08:05 WIB

        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        // 1. Check today status
        $todayRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/attendance/today');

        $todayRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Clock In
        $clockInRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/clock-in', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 15.0,
                'face_score' => 0.85,
                'is_mock_location' => false,
            ]);

        $clockInRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'hadir',
                    'late_minutes' => 0,
                ],
            ]);

        // 3. Clock Out at 17:05
        Carbon::setTestNow('2026-09-15 17:05:00');

        $clockOutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/clock-out', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 12.0,
                'face_score' => 0.86,
                'is_mock_location' => false,
            ]);

        $clockOutRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_integration_attendances_with_api_key(): void
    {
        $response = $this->withHeader('X-API-KEY', 'indo_absen_sec_2026_x89a7f3d')
            ->getJson('/api/v1/integration/attendances');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'meta' => ['current_page', 'per_page', 'total_records'],
                'data',
            ]);
    }

    public function test_admin_can_reset_pegawai_face_biometric(): void
    {
        // Beri data wajah awal
        $this->pegawai->update([
            'face_embedding' => json_encode([0.12, 0.34, 0.56]),
            'face_enrolled_at' => now(),
        ]);
        $this->assertTrue($this->pegawai->fresh()->is_face_enrolled);

        // Admin token
        $adminRole = \App\Models\Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $this->user->roles()->sync([$adminRole->id]);

        $tokenResult = $this->user->createToken('admin-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/simpeg/pegawai/{$this->pegawai->id}/reset-face");

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Data biometrik wajah pegawai berhasil direset. Pegawai dapat mendaftarkan ulang melalui aplikasi mobile.',
            ]);

        $this->assertFalse($this->pegawai->fresh()->is_face_enrolled);
        $this->assertNull($this->pegawai->fresh()->face_enrolled_at);
    }
}
