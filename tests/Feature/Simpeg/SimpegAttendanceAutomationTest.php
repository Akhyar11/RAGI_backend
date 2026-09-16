<?php

namespace Tests\Feature\Simpeg;

use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShiftScheduleDay;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\FingerprintDevice;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Simpeg\SimpegShiftUniversitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegAttendanceAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawaiTendik;
    protected Pegawai $pegawaiDosen;
    protected UnitKerja $unitKerja;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Role & Permissions Presensi
        $role = Role::create(['name' => 'HR Admin', 'slug' => 'hr-admin']);
        $perms = [
            'simpeg.presensi.read',
            'simpeg.presensi.create',
            'simpeg.presensi.manage',
            'simpeg.presensi.delete',
        ];
        foreach ($perms as $slug) {
            $p = Permission::create([
                'slug' => $slug,
                'name' => $slug,
                'module' => 'simpeg',
                'action' => 'read',
            ]);
            $role->permissions()->attach($p->id);
        }

        $this->admin = User::create([
            'username' => 'admin.presensi',
            'email' => 'admin.presensi@kampus.ac.id',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);
        $this->admin->roles()->attach($role->id);

        // 2. Unit Kerja & Pegawai
        $this->unitKerja = UnitKerja::create([
            'kode' => 'BAA',
            'nama' => 'Biro Administrasi Akademik',
            'tipe' => 'biro',
        ]);

        $this->pegawaiTendik = Pegawai::create([
            'nip' => 'TENDIK-001',
            'nama_lengkap' => 'Budi Santoso, S.Kom.',
            'jenis_pegawai' => 'tendik',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        $this->pegawaiDosen = Pegawai::create([
            'nip' => 'DOSEN-001',
            'nama_lengkap' => 'Dr. Hendra Gunawan, M.T.',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        // 3. Jalankan Seeder Multi-Shift Kampus
        $this->seed(SimpegShiftUniversitySeeder::class);
    }

    /**
     * Verifikasi ketersediaan dan toleransi 6 shift kampus.
     */
    public function test_university_multi_shift_templates_and_tolerances(): void
    {
        $shifts = ShiftTemplate::with('days')->get();

        $this->assertGreaterThanOrEqual(6, $shifts->count());

        $tendik = $shifts->firstWhere('name', 'Shift Tendik Reguler (5 Hari)');
        $this->assertNotNull($tendik);
        $this->assertEquals(15, $tendik->late_tolerance_minutes);
        $this->assertTrue($tendik->applies_national_holidays);
        $this->assertEquals(7, $tendik->days->count());

        $satpamMalam = $shifts->firstWhere('name', 'Shift Satpam / Security Malam (Shift 3)');
        $this->assertNotNull($satpamMalam);
        $this->assertFalse($satpamMalam->applies_national_holidays);
        $this->assertEquals(10, $satpamMalam->late_tolerance_minutes);
    }

    /**
     * Test Bulk Assignment Shift ke Pegawai di Unit Kerja.
     */
    public function test_bulk_assign_shift_to_employees(): void
    {
        $shiftDosen = ShiftTemplate::where('name', 'Shift Dosen Fleksibel (Tridharma)')->firstOrFail();

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/presensi/shift-assign-bulk', [
            'shift_template_id' => $shiftDosen->id,
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'shift_template_id' => $shiftDosen->id,
                    'total_assigned' => 2,
                ],
            ]);

        $this->pegawaiTendik->refresh();
        $this->pegawaiDosen->refresh();

        $this->assertEquals($shiftDosen->id, $this->pegawaiTendik->shift_template_id);
        $this->assertEquals($shiftDosen->id, $this->pegawaiDosen->shift_template_id);
    }

    /**
     * Test Sinkronisasi Log Mesin Fingerprint (Clock-In, Clock-Out, Deteksi Terlambat).
     */
    public function test_sync_fingerprint_punch_logs(): void
    {
        $shiftTendik = ShiftTemplate::where('name', 'Shift Tendik Reguler (5 Hari)')->firstOrFail();
        $this->pegawaiTendik->update(['shift_template_id' => $shiftTendik->id]);

        // Simulasikan hari Senin tanggal 2026-09-14 (Shift 08:00 - 17:00, toleransi telat 15m)
        // Punch masuk jam 08:25 (terlambat 25 menit dari 08:00)
        // Punch pulang jam 17:05 (tepat waktu)
        $payload = [
            'device_code' => 'FP-UTAMA-REKTORAT',
            'device_ip' => '192.168.1.201',
            'logs' => [
                [
                    'pin' => 'TENDIK-001',
                    'timestamp' => '2026-09-14 08:25:00',
                    'verify_mode' => 1,
                    'in_out_mode' => 0, // Clock In
                ],
                [
                    'pin' => 'TENDIK-001',
                    'timestamp' => '2026-09-14 17:05:00',
                    'verify_mode' => 1,
                    'in_out_mode' => 1, // Clock Out
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/presensi/fingerprint/sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_logs' => 2,
                    'synced_count' => 2,
                ],
            ]);

        $att = Attendance::where('pegawai_id', $this->pegawaiTendik->id)
            ->whereDate('tanggal', '2026-09-14')
            ->first();

        $this->assertNotNull($att);
        $this->assertEquals('terlambat', $att->status_kehadiran);
        $this->assertEquals(25, $att->late_minutes);
        $this->assertEquals('fingerprint', $att->source);
        $this->assertEquals('FP-UTAMA-REKTORAT', $att->device_id);
        $this->assertEquals('08:25:00', $att->jam_masuk);
        $this->assertEquals('17:05:00', $att->jam_keluar);
        $this->assertEquals(0, $att->early_leave_minutes);
    }

    /**
     * Test Otomasi Cut-off Presensi Harian (Auto-Alfa).
     */
    public function test_daily_cutoff_marks_unexcused_absences_as_alfa(): void
    {
        $shiftTendik = ShiftTemplate::where('name', 'Shift Tendik Reguler (5 Hari)')->firstOrFail();
        $this->pegawaiTendik->update(['shift_template_id' => $shiftTendik->id]);

        // Pada tanggal 2026-09-15 (Selasa, hari kerja), pegawai tidak melakukan presensi sama sekali
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/presensi/daily-cutoff', [
            'date' => '2026-09-15',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'date' => '2026-09-15',
                ],
            ]);

        $att = Attendance::where('pegawai_id', $this->pegawaiTendik->id)
            ->whereDate('tanggal', '2026-09-15')
            ->first();

        $this->assertNotNull($att);
        $this->assertEquals('alfa', $att->status_kehadiran);
        $this->assertEquals('system_cutoff', $att->source);
        $this->assertStringContainsString('Otomatis Cut-off Harian', $att->notes);
    }

    /**
     * Test CRUD Perangkat Mesin Fingerprint & Uji Koneksi.
     */
    public function test_fingerprint_device_crud_and_connection(): void
    {
        // 1. List Devices
        $resList = $this->actingAs($this->admin, 'api')->getJson('/api/simpeg/presensi/fingerprint-devices');
        $resList->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);

        // 2. Create Device
        $resCreate = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/presensi/fingerprint-devices', [
            'device_name' => 'Terminal Gedung Pascasarjana',
            'device_code' => 'FP-PASCASARJANA-01',
            'ip_address' => '192.168.1.205',
            'port' => 4370,
            'location' => 'Lobi Gedung Pascasarjana',
            'device_model' => 'ZKTeco K40 Biometric',
            'is_active' => true,
        ]);
        $resCreate->assertStatus(201);
        $deviceId = $resCreate->json('data.id');

        // 3. Test Connection
        $resTest = $this->actingAs($this->admin, 'api')->postJson("/api/simpeg/presensi/fingerprint-devices/{$deviceId}/test-connection");
        $resTest->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'device_code' => 'FP-PASCASARJANA-01',
                    'status' => 'online',
                ],
            ]);

        // 4. Update Device
        $resUpdate = $this->actingAs($this->admin, 'api')->putJson("/api/simpeg/presensi/fingerprint-devices/{$deviceId}", [
            'device_name' => 'Terminal Pascasarjana Updated',
            'device_code' => 'FP-PASCASARJANA-01',
            'ip_address' => '192.168.1.206',
            'port' => 4370,
            'is_active' => true,
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.ip_address', '192.168.1.206');

        // 5. Delete Device
        $resDelete = $this->actingAs($this->admin, 'api')->deleteJson("/api/simpeg/presensi/fingerprint-devices/{$deviceId}");
        $resDelete->assertStatus(200);
        $this->assertDatabaseMissing('simpeg_fingerprint_devices', ['id' => $deviceId]);
    }
}
