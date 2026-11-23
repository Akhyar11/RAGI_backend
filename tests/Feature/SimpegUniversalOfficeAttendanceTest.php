<?php

namespace Tests\Feature;

use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\ShiftScheduleDay;
use App\Models\Simpeg\Pegawai;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SimpegUniversalOfficeAttendanceTest extends TestCase
{
    protected User $user;
    protected Pegawai $pegawai;
    protected OfficeLocation $kampus1;
    protected OfficeLocation $kampus2;
    protected ShiftTemplate $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            $clientRepository = app(\Laravel\Passport\ClientRepository::class);
            $clientRepository->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        SystemSetting::set('gps_accuracy_threshold_meters', 50.0);
        SystemSetting::set('face_score_threshold', 0.68);
        SystemSetting::set('min_work_duration_minutes', 0); // Allow immediate clock out for testing

        $this->kampus1 = OfficeLocation::updateOrCreate(
            ['name' => 'Kampus 1 Utama'],
            [
                'address' => 'Jl. Samanhudi No. 84 Surakarta',
                'latitude' => -7.5675000,
                'longitude' => 110.8036000,
                'radius_meters' => 100,
                'is_active' => true,
            ]
        );

        $this->kampus2 = OfficeLocation::updateOrCreate(
            ['name' => 'Kampus 2 Pajang'],
            [
                'address' => 'Jl. Pajang No. 12 Surakarta',
                'latitude' => -7.5750000,
                'longitude' => 110.7900000,
                'radius_meters' => 100,
                'is_active' => true,
            ]
        );

        $this->shift = ShiftTemplate::firstOrCreate(
            ['name' => 'Shift Reguler Fleksibel'],
            [
                'description' => 'Shift fleksibel pengujian',
                'is_active' => true,
                'late_tolerance_minutes' => 60,
                'early_leave_tolerance_minutes' => 60,
                'max_early_clock_in_minutes' => 120,
                'applies_national_holidays' => false,
            ]
        );

        for ($d = 0; $d <= 6; $d++) {
            ShiftScheduleDay::updateOrCreate(
                ['shift_template_id' => $this->shift->id, 'day_of_week' => $d],
                [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'is_day_off' => false,
                ]
            );
        }

        $this->user = User::firstOrCreate(
            ['email' => 'pegawai.bebas@kampus.ac.id'],
            [
                'username' => 'pegawaibebas',
                'password' => Hash::make('secret123'),
                'is_active' => true,
            ]
        );

        // Pegawai sengaja TIDAK di-plot ke lokasi kantor mana pun (office_location_id = null)
        $this->pegawai = Pegawai::updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'nip' => 'PEG-BEBAS-001',
                'nama_lengkap' => 'Pegawai Bebas Kampus',
                'office_location_id' => null,
                'shift_template_id' => $this->shift->id,
                'face_embedding' => json_encode(array_fill(0, 512, 0.05)),
            ]
        );
    }

    public function test_employee_without_office_location_can_clock_in_at_any_active_campus_within_radius()
    {
        Passport::actingAs($this->user);
        Storage::fake('public');

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        // Absen tepat di koordinat Kampus 1
        $response = $this->postJson('/api/simpeg/presensi/clock-in', [
            'latitude' => -7.5675000,
            'longitude' => 110.8036000,
            'accuracy' => 10.0,
            'face_score' => 0.85,
            'foto' => UploadedFile::fake()->image('face.jpg', 200, 200),
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'hadir',
        ]);
    }

    public function test_employee_is_rejected_when_outside_radius_of_all_campuses()
    {
        Passport::actingAs($this->user);
        Storage::fake('public');

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        // Absen jauh dari Kampus 1 dan Kampus 2 (misal di Boyolali)
        $response = $this->postJson('/api/simpeg/presensi/clock-in', [
            'latitude' => -7.5000000,
            'longitude' => 110.6000000,
            'accuracy' => 10.0,
            'face_score' => 0.85,
            'foto' => UploadedFile::fake()->image('face.jpg', 200, 200),
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertStringContainsString('Di luar area kantor', $response->json('message'));
    }

    public function test_employee_can_clock_in_at_campus_1_and_clock_out_at_campus_2()
    {
        Passport::actingAs($this->user);
        Storage::fake('public');

        // Clock In di Kampus 1 pada jam 08:00
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        $inResponse = $this->postJson('/api/simpeg/presensi/clock-in', [
            'latitude' => -7.5675000,
            'longitude' => 110.8036000,
            'accuracy' => 10.0,
            'face_score' => 0.85,
            'foto' => UploadedFile::fake()->image('face_in.jpg', 200, 200),
        ]);
        $inResponse->assertStatus(200);

        // Pindah dan Clock Out di Kampus 2 pada jam 17:00
        Carbon::setTestNow(Carbon::today()->setTime(17, 0));

        $outResponse = $this->postJson('/api/simpeg/presensi/clock-out', [
            'latitude' => -7.5750000,
            'longitude' => 110.7900000,
            'accuracy' => 10.0,
            'face_score' => 0.85,
            'foto' => UploadedFile::fake()->image('face_out.jpg', 200, 200),
        ]);

        $outResponse->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $attendance = \App\Models\Attendance::where('pegawai_id', $this->pegawai->id)->first();
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->clock_in);
        $this->assertNotNull($attendance->clock_out);
    }
}
