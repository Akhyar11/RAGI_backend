<?php

namespace Tests\Feature;

use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_today_returns_server_time_and_helper_flags(): void
    {
        Carbon::setTestNow('2026-09-15 07:30:00'); // Sebelum clock-in

        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/attendance/today');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'success' => true,
                'data' => [
                    'can_clock_in' => true,
                    'can_clock_out' => false,
                    'is_clocked_in' => false,
                    'is_clocked_out' => false,
                    'status' => 'belum_absen',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'server_time',
                    'server_timestamp',
                    'server_date',
                    'server_time_formatted',
                    'can_clock_in',
                    'can_clock_out',
                    'is_clocked_in',
                    'is_clocked_out',
                    'schedule' => [
                        'start_time',
                        'end_time',
                        'late_tolerance_minutes',
                        'early_leave_tolerance_minutes',
                    ],
                    'employee' => [
                        'id',
                        'nip',
                        'avatar',
                        'foto_url',
                    ],
                ],
            ]);

        $this->assertNotEmpty($res->json('data.server_time'));
        $this->assertIsInt($res->json('data.server_timestamp'));
    }

    public function test_clock_in_syncs_jam_masuk_and_lat_long_and_clock_out_syncs_jam_keluar(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        // Clock In
        $inRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/clock-in', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 10.0,
                'face_score' => 0.88,
                'notes' => 'Hadir tepat waktu',
                'device_id' => 'device-abc-123',
            ]);

        $inRes->assertStatus(200);

        $attendance = \App\Models\Attendance::where('pegawai_id', $this->pegawai->id)
            ->whereDate('tanggal', '2026-09-15')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('08:00:00', $attendance->jam_masuk);
        $this->assertEquals('-7.5675,110.8036', $attendance->lat_long);
        $this->assertEquals('device-abc-123', $attendance->device_id);
        $this->assertEquals('mobile_gps', $attendance->source);

        // Clock Out at 17:00:00
        Carbon::setTestNow('2026-09-15 17:00:00');

        $outRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/clock-out', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 10.0,
                'face_score' => 0.85,
                'notes' => 'Pulang kerja',
            ]);

        $outRes->assertStatus(200);

        $attendance->refresh();
        $this->assertEquals('17:00:00', $attendance->jam_keluar);
        $this->assertStringContainsString('Pulang kerja', $attendance->notes);
    }

    public function test_keterangan_supports_attachment_and_alias_keys(): void
    {
        Storage::fake('public');

        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        $fakeFile = UploadedFile::fake()->create('surat_dokter.pdf', 150, 'application/pdf');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/attendance/keterangan', [
                'tanggal' => '2026-09-28',
                'status' => 'sakit',
                'notes' => 'Demam tinggi, istirahat dokter',
                'file' => $fakeFile,
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'success' => true,
                'data' => [
                    'status' => 'sakit',
                    'status_kehadiran' => 'sakit',
                ],
            ]);

        $attendance = \App\Models\Attendance::where('pegawai_id', $this->pegawai->id)
            ->whereDate('tanggal', '2026-09-28')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->foto_presensi);
        $this->assertNotNull($attendance->foto_url);
        Storage::disk('public')->assertExists($attendance->foto_presensi);
    }

    public function test_history_supports_pagination_and_status_filter(): void
    {
        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        // Buat beberapa log presensi
        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Attendance::create([
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => "2026-08-0{$i}",
                'clock_in' => Carbon::parse("2026-08-0{$i} 08:00:00"),
                'status' => $i === 5 ? 'izin' : 'hadir',
            ]);
        }

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/attendance/history?month=8&year=2026&per_page=2&page=1');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'attendances',
                    'total',
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        $this->assertCount(2, $res->json('data.attendances'));
        $this->assertEquals(5, $res->json('meta.total'));
        $this->assertEquals(3, $res->json('meta.last_page'));
    }

    public function test_simpeg_presensi_today_and_clock_in_out_relaxed_parameters(): void
    {
        Carbon::setTestNow('2026-09-16 08:10:00');

        $tokenResult = $this->user->createToken('test-token');
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        // 1. Simpeg today endpoint
        $resToday = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/simpeg/presensi/today');

        $resToday->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'can_clock_in' => true,
                    'can_clock_out' => false,
                ],
            ]);

        // 2. Simpeg clock in TANPA mengirim is_mock_location (harus default false, tidak boleh 422)
        $resClockIn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/simpeg/presensi/clock-in', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 15.0,
                'face_score' => 0.85,
            ]);

        $resClockIn->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // 3. Simpeg clock out TANPA mengirim face_score dan is_mock_location (tidak boleh 422)
        Carbon::setTestNow('2026-09-16 17:05:00');

        $resClockOut = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/simpeg/presensi/clock-out', [
                'latitude' => -7.5675,
                'longitude' => 110.8036,
                'accuracy' => 15.0,
            ]);

        $resClockOut->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}
