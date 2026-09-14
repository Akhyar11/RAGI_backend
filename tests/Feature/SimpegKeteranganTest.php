<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PengajuanCuti;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpegKeteranganTest extends TestCase
{
    protected User $admin;
    protected User $staff;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        Carbon::setTestNow('2026-09-14 10:00:00'); // Senin

        $office = OfficeLocation::firstOrCreate(
            ['name' => 'Kantor Test'],
            ['latitude' => -7.5675, 'longitude' => 110.8036, 'radius_meters' => 150, 'is_active' => true]
        );

        $shift = ShiftTemplate::firstOrCreate(
            ['name' => 'Shift Reguler 5 Hari'],
            ['is_active' => true, 'applies_national_holidays' => true]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.keterangan@kampus.ac.id'],
            ['username' => 'adminketerangan', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $this->staff = User::firstOrCreate(
            ['email' => 'staff.keterangan@kampus.ac.id'],
            ['username' => 'staffketerangan', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $this->pegawai = Pegawai::firstOrCreate(
            ['nip' => '199999992026091002'],
            [
                'user_id' => $this->staff->id,
                'nama_lengkap' => 'Pegawai Keterangan Test',
                'jenis_pegawai' => 'tendik',
                'status_kepegawaian' => 'tetap_yayasan',
                'office_location_id' => $office->id,
                'shift_template_id' => $shift->id,
                'is_active' => true,
            ]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('test-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    public function test_admin_dapat_menetapkan_keterangan_izin(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/simpeg/presensi/keterangan', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-09-11',
                'status_kehadiran' => 'izin',
                'catatan' => 'Acara keluarga',
            ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['id', 'pegawai_id', 'status_kehadiran']]);

        $this->assertEquals(1, Attendance::where('pegawai_id', $this->pegawai->id)
            ->whereDate('tanggal', '2026-09-11')->count());
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status_kehadiran' => 'izin',
        ]);
    }

    public function test_set_keterangan_idempotent_per_tanggal(): void
    {
        $headers = ['Authorization' => 'Bearer ' . $this->adminToken()];

        foreach (['izin', 'sakit'] as $status) {
            $this->withHeaders($headers)->postJson('/api/simpeg/presensi/keterangan', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-09-10',
                'status_kehadiran' => $status,
            ])->assertStatus(201);
        }

        $this->assertEquals(1, Attendance::where('pegawai_id', $this->pegawai->id)
            ->whereDate('tanggal', '2026-09-10')->count());

        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status_kehadiran' => 'sakit',
        ]);
    }

    public function test_set_keterangan_ditolak_bila_sudah_ada_hasil_scan(): void
    {
        Attendance::create([
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-09-09',
            'clock_in' => Carbon::parse('2026-09-09 08:00:00'),
            'status' => 'hadir',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/simpeg/presensi/keterangan', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-09-09',
                'status_kehadiran' => 'alfa',
            ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertEquals(1, Attendance::where('pegawai_id', $this->pegawai->id)
            ->whereDate('tanggal', '2026-09-09')->count());
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'status_kehadiran' => 'hadir',
        ]);
    }

    public function test_non_admin_ditolak(): void
    {
        $result = $this->staff->createToken('staff-token');
        $token = $result->plainTextToken ?? $result->accessToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/simpeg/presensi/keterangan', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-09-11',
                'status_kehadiran' => 'izin',
            ])->assertStatus(403);
    }

    public function test_validasi_status_dan_tanggal(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/simpeg/presensi/keterangan', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '11-09-2026',
                'status_kehadiran' => 'hadir',
            ])->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_rekap_memuat_keterangan_cuti_dan_alpa(): void
    {
        $headers = ['Authorization' => 'Bearer ' . $this->adminToken()];

        // 11 Sep: keterangan izin oleh admin
        $this->withHeaders($headers)->postJson('/api/simpeg/presensi/keterangan', [
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-09-11',
            'status_kehadiran' => 'izin',
            'catatan' => 'Acara keluarga',
        ])->assertStatus(201);

        // 08-09 Sep: cuti tahunan yang disetujui
        PengajuanCuti::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_cuti' => 'tahunan',
            'tanggal_mulai' => '2026-09-08',
            'tanggal_selesai' => '2026-09-09',
            'jumlah_hari' => 2,
            'alasan' => 'Liburan',
            'status_approval' => 'approved',
        ]);

        $response = $this->withHeaders($headers)
            ->getJson("/api/simpeg/presensi/recap?pegawai_id={$this->pegawai->id}&month=9&year=2026");

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $recap = $response->json('data');
        $rows = collect($recap['rows'])->keyBy('tanggal');

        $this->assertEquals('izin', $rows['11/09/2026']['status_badge']);
        $this->assertEquals('Acara keluarga', $rows['11/09/2026']['keterangan']);
        $this->assertEquals('cuti', $rows['08/09/2026']['status_badge']);
        $this->assertStringContainsString('Cuti Tahunan', $rows['08/09/2026']['keterangan']);
        $this->assertEquals('alpa', $rows['07/09/2026']['status_badge']);

        $this->assertGreaterThanOrEqual(1, $recap['summary']['total_izin']);
        $this->assertGreaterThanOrEqual(2, $recap['summary']['total_cuti']);
        $this->assertGreaterThanOrEqual(1, $recap['summary']['total_alpa']);
    }
}
