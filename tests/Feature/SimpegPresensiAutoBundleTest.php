<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\User;
use App\Services\Simpeg\PresensiBundleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegPresensiAutoBundleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai1;
    protected Pegawai $pegawai2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Administrator']);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->pegawai1 = Pegawai::create([
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Budi Santoso',
            'jenis_pegawai' => 'tendik',
            'status' => 'aktif',
        ]);

        $this->pegawai2 = Pegawai::create([
            'nip' => '199002022018022002',
            'nama_lengkap' => 'Dewi Lestari',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);
    }

    public function test_period_calculation_adheres_to_campus_26_to_25_cycle()
    {
        $service = app(PresensiBundleService::class);

        // 1. Tanggal 26 Januari 2026 -> Periode Februari 2026 (26 Jan - 25 Feb)
        $p1 = $service->getPeriodForDate('2026-01-26');
        $this->assertEquals('2026-01-26', $p1['tanggal_awal']);
        $this->assertEquals('2026-02-25', $p1['tanggal_akhir']);
        $this->assertEquals('2026-02', $p1['bulan_tahun']);
        $this->assertEquals('Presensi Februari 2026', $p1['nama_periode']);

        // 2. Tanggal 15 Februari 2026 -> Periode Februari 2026 (26 Jan - 25 Feb)
        $p2 = $service->getPeriodForDate('2026-02-15');
        $this->assertEquals('2026-01-26', $p2['tanggal_awal']);
        $this->assertEquals('2026-02-25', $p2['tanggal_akhir']);
        $this->assertEquals('2026-02', $p2['bulan_tahun']);

        // 3. Tanggal 25 Februari 2026 -> Periode Februari 2026 (26 Jan - 25 Feb)
        $p3 = $service->getPeriodForDate('2026-02-25');
        $this->assertEquals('2026-01-26', $p3['tanggal_awal']);
        $this->assertEquals('2026-02-25', $p3['tanggal_akhir']);
        $this->assertEquals('2026-02', $p3['bulan_tahun']);

        // 4. Tanggal 26 Februari 2026 -> Periode Maret 2026 (26 Feb - 25 Mar)
        $p4 = $service->getPeriodForDate('2026-02-26');
        $this->assertEquals('2026-02-26', $p4['tanggal_awal']);
        $this->assertEquals('2026-03-25', $p4['tanggal_akhir']);
        $this->assertEquals('2026-03', $p4['bulan_tahun']);
        $this->assertEquals('Presensi Maret 2026', $p4['nama_periode']);

        // 5. Pergantian Tahun: Tanggal 26 Desember 2025 -> Periode Januari 2026 (26 Des 2025 - 25 Jan 2026)
        $p5 = $service->getPeriodForDate('2025-12-26');
        $this->assertEquals('2025-12-26', $p5['tanggal_awal']);
        $this->assertEquals('2026-01-25', $p5['tanggal_akhir']);
        $this->assertEquals('2026-01', $p5['bulan_tahun']);
        $this->assertEquals('Presensi Januari 2026', $p5['nama_periode']);
    }

    public function test_realtime_logs_are_automatically_grouped_into_corresponding_bundles()
    {
        // Log untuk Siklus Februari 2026 (26 Jan - 25 Feb)
        $log1 = PresensiPegawai::create([
            'pegawai_id' => $this->pegawai1->id,
            'tanggal' => '2026-01-27',
            'jam_masuk' => '08:00:00',
            'status_kehadiran' => 'hadir',
        ]);
        $log2 = PresensiPegawai::create([
            'pegawai_id' => $this->pegawai2->id,
            'tanggal' => '2026-02-10',
            'jam_masuk' => '08:05:00',
            'status_kehadiran' => 'hadir',
        ]);

        // Log untuk Siklus Maret 2026 (26 Feb - 25 Mar)
        $log3 = PresensiPegawai::create([
            'pegawai_id' => $this->pegawai1->id,
            'tanggal' => '2026-02-28',
            'jam_masuk' => '07:55:00',
            'status_kehadiran' => 'hadir',
        ]);

        $service = app(PresensiBundleService::class);
        $result = $service->autoSyncBundlesFromRealtime();

        $this->assertGreaterThanOrEqual(2, $result['total_bundles']);

        // Cek Bundle Februari 2026
        $bundleFeb = PresensiPeriode::where('bulan_tahun', '2026-02')->first();
        $this->assertNotNull($bundleFeb);
        $this->assertEquals('2026-01-26', Carbon::parse($bundleFeb->tanggal_awal)->toDateString());
        $this->assertEquals('2026-02-25', Carbon::parse($bundleFeb->tanggal_akhir)->toDateString());
        $this->assertEquals(2, $bundleFeb->total_record);

        // Verifikasi log1 dan log2 terhubung ke bundle Februari
        $this->assertEquals($bundleFeb->id, $log1->fresh()->presensi_periode_id);
        $this->assertEquals($bundleFeb->id, $log2->fresh()->presensi_periode_id);

        // Cek Bundle Maret 2026
        $bundleMar = PresensiPeriode::where('bulan_tahun', '2026-03')->first();
        $this->assertNotNull($bundleMar);
        $this->assertEquals('2026-02-26', Carbon::parse($bundleMar->tanggal_awal)->toDateString());
        $this->assertEquals('2026-03-25', Carbon::parse($bundleMar->tanggal_akhir)->toDateString());
        $this->assertEquals(1, $bundleMar->total_record);

        // Verifikasi log3 terhubung ke bundle Maret
        $this->assertEquals($bundleMar->id, $log3->fresh()->presensi_periode_id);
    }

    public function test_api_sync_bundle_and_index_bundle_endpoint()
    {
        // Buat log presensi
        PresensiPegawai::create([
            'pegawai_id' => $this->pegawai1->id,
            'tanggal' => '2026-01-28',
            'jam_masuk' => '08:00:00',
            'status_kehadiran' => 'hadir',
        ]);

        // Test Endpoint POST /api/simpeg/presensi/bundle/sync
        $responseSync = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/presensi/bundle/sync');

        $responseSync->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Test Endpoint GET /api/simpeg/presensi?type=bundle
        $responseIndex = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/presensi?type=bundle');

        $responseIndex->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'nama_periode',
                        'tanggal_awal',
                        'tanggal_akhir',
                        'bulan_tahun',
                        'total_record',
                        'total_pegawai',
                    ]
                ],
                'meta',
            ]);
    }
}
