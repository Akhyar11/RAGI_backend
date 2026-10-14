<?php

namespace Tests\Feature;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\SuratPeringatan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Simpeg\SuratPeringatanService;
use Carbon\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SimpegSuratPeringatanTest extends TestCase
{
    protected User $user;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $this->user = User::factory()->create([
            'email' => 'dosen.sp.test@example.com',
        ]);

        $this->pegawai = Pegawai::create([
            'user_id' => $this->user->id,
            'nip' => 'TEST-SP-001',
            'nama_lengkap' => 'Dosen SP Test',
            'email' => 'dosen.sp.test@example.com',
            'jenis_pegawai' => 'dosen',
            'status_pegawai' => 'tetap',
            'is_active' => true,
        ]);

        SystemSetting::set('max_late_per_month', '5');
    }

    public function test_generates_sp_when_late_exceeds_threshold_on_payroll_paid(): void
    {
        $periode = '2026-10';

        // Buat bundle presensi
        $bundle = PresensiPeriode::create([
            'nama_periode' => 'Bundle Presensi Oktober 2026',
            'bulan_tahun' => $periode,
            'tanggal_awal' => '2026-10-01',
            'tanggal_akhir' => '2026-10-31',
            'is_locked' => true,
            'total_record' => 30,
        ]);

        // Buat 6 catatan keterlambatan untuk pegawai (melebihi threshold 5)
        for ($i = 1; $i <= 6; $i++) {
            PresensiPegawai::create([
                'pegawai_id' => $this->pegawai->id,
                'presensi_periode_id' => $bundle->id,
                'tanggal' => sprintf('2026-10-%02d', $i),
                'jam_masuk' => '08:30:00',
                'jam_keluar' => '17:00:00',
                'status_kehadiran' => 'terlambat',
            ]);
        }

        // Buat gaji pegawai berstatus paid
        $gaji = GajiPegawai::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => $periode,
            'gaji_pokok' => 5000000,
            'gaji_bersih' => 5000000,
            'status_transfer' => 'paid',
        ]);

        $service = app(SuratPeringatanService::class);
        $sp = $service->checkAndGenerateSpForGaji($gaji);

        $this->assertNotNull($sp);
        $this->assertEquals(6, $sp->total_terlambat);
        $this->assertEquals(5, $sp->batas_maksimal);
        $this->assertEquals($this->pegawai->id, $sp->pegawai_id);
        $this->assertEquals('aktif', $sp->status);
        $this->assertStringContainsString('SP/SIMPEG', $sp->nomor_sp);

        $this->assertDatabaseHas('simpeg_surat_peringatan', [
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => $periode,
            'total_terlambat' => 6,
        ]);
    }

    public function test_does_not_generate_sp_when_late_does_not_exceed_threshold(): void
    {
        $periode = '2026-11';

        $bundle = PresensiPeriode::create([
            'nama_periode' => 'Bundle Presensi November 2026',
            'bulan_tahun' => $periode,
            'tanggal_awal' => '2026-11-01',
            'tanggal_akhir' => '2026-11-30',
            'is_locked' => true,
            'total_record' => 20,
        ]);

        // Hanya 3 kali terlambat (<= 5)
        for ($i = 1; $i <= 3; $i++) {
            PresensiPegawai::create([
                'pegawai_id' => $this->pegawai->id,
                'presensi_periode_id' => $bundle->id,
                'tanggal' => sprintf('2026-11-%02d', $i),
                'jam_masuk' => '08:20:00',
                'jam_keluar' => '17:00:00',
                'status_kehadiran' => 'terlambat',
            ]);
        }

        $gaji = GajiPegawai::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => $periode,
            'gaji_pokok' => 5000000,
            'gaji_bersih' => 5000000,
            'status_transfer' => 'paid',
        ]);

        $service = app(SuratPeringatanService::class);
        $sp = $service->checkAndGenerateSpForGaji($gaji);

        $this->assertNull($sp);
        $this->assertDatabaseMissing('simpeg_surat_peringatan', [
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => $periode,
        ]);
    }

    public function test_pegawai_can_fetch_own_surat_peringatan_via_api(): void
    {
        SuratPeringatan::create([
            'pegawai_id' => $this->pegawai->id,
            'nomor_sp' => 'SP/SIMPEG/2026/10/0099',
            'jenis_sp' => 'SP 1',
            'judul' => 'Surat Peringatan Keterlambatan Presensi Oktober 2026',
            'periode_bulan_tahun' => '2026-10',
            'total_terlambat' => 7,
            'batas_maksimal' => 5,
            'keterangan' => 'Terlambat 7 kali',
            'tanggal_terbit' => '2026-10-14',
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/simpeg/surat-peringatan/my');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_sp', 'SP/SIMPEG/2026/10/0099')
            ->assertJsonPath('data.0.total_terlambat', 7);
    }

    public function test_admin_can_fetch_annual_sp_monitoring_matrix(): void
    {
        $perm = \App\Models\Permission::firstOrCreate(
            ['slug' => 'simpeg.surat_peringatan.read'],
            ['name' => 'Lihat Surat Peringatan', 'module' => 'simpeg', 'action' => 'read']
        );
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'Admin Test'],
            ['slug' => 'admin_test', 'module' => 'simpeg']
        );
        \App\Models\RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $perm->id]);
        \App\Models\UserRole::firstOrCreate(['user_id' => $this->user->id, 'role_id' => $role->id]);

        SuratPeringatan::create([
            'pegawai_id' => $this->pegawai->id,
            'nomor_sp' => 'SP/SIMPEG/2026/10/0100',
            'jenis_sp' => 'SP 1',
            'judul' => 'Surat Peringatan Keterlambatan Presensi Oktober 2026',
            'periode_bulan_tahun' => '2026-10',
            'total_terlambat' => 6,
            'batas_maksimal' => 5,
            'keterangan' => 'Terlambat 6 kali',
            'tanggal_terbit' => '2026-10-14',
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/simpeg/surat-peringatan/monitoring-tahunan?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.tahun', 2026)
            ->assertJsonPath('data.0.pegawai_id', $this->pegawai->id)
            ->assertJsonPath('data.0.months.10.has_sp', true)
            ->assertJsonPath('data.0.months.10.status_color', 'danger')
            ->assertJsonPath('data.0.summary.total_sp', 1);
    }
}
