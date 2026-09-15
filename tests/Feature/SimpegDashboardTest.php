<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);
    }

    public function test_can_fetch_simpeg_dashboard_stats_realtime()
    {
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen Pengajar', 'is_active' => true]);
        $roleTendik = Role::firstOrCreate(['slug' => 'tendik'], ['name' => 'Tenaga Kependidikan', 'is_active' => true]);

        $unit1 = UnitKerja::create(['nama' => 'Fakultas Teknik', 'kode' => 'FT', 'tipe' => 'fakultas']);
        $unit2 = UnitKerja::create(['nama' => 'Biro Keuangan', 'kode' => 'KU', 'tipe' => 'biro']);

        $peg1 = Pegawai::create([
            'nama_lengkap' => 'Dosen Utama',
            'nip' => '198001012005011001',
            'jenis_pegawai' => 'dosen',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'unit_kerja_id' => $unit1->id,
        ]);
        $peg1->roles()->attach($roleDosen->id);

        $peg2 = Pegawai::create([
            'nama_lengkap' => 'Tendik Administrasi',
            'nip' => '199002022015022002',
            'jenis_pegawai' => 'tendik',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'unit_kerja_id' => $unit2->id,
        ]);
        $peg2->roles()->attach($roleTendik->id);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/dashboard-stats');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_pegawai', 2)
            ->assertJsonPath('data.total_dosen', 1)
            ->assertJsonPath('data.total_tendik', 1)
            ->assertJsonPath('data.total_unit_kerja', 2);

        $recent = $response->json('data.recent_pegawai');
        $this->assertCount(2, $recent);
    }
}
