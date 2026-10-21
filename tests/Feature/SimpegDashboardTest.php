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

    public function test_can_fetch_presensi_analytics(): void
    {
        $unit = UnitKerja::create(['nama' => 'Prodi Informatika', 'kode' => 'IF', 'tipe' => 'prodi']);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Uji Presensi',
            'nip' => '198501012010011001',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
            'unit_kerja_id' => $unit->id,
        ]);

        \App\Models\Simpeg\PresensiPegawai::create([
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-01',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '17:00:00',
            'status_kehadiran' => 'hadir',
            'late_minutes' => 0,
        ]);

        \App\Models\Simpeg\SuratPeringatan::create([
            'pegawai_id' => $pegawai->id,
            'nomor_sp' => 'SP/TEST/2026/10/01',
            'jenis_sp' => 'SP 1',
            'judul' => 'SP Uji Coba',
            'periode_bulan_tahun' => '2026-10',
            'total_terlambat' => 6,
            'batas_maksimal' => 5,
            'tanggal_terbit' => '2026-10-10',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/analytics/presensi?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tahun', 2026)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'tahun',
                    'summary' => ['total_kehadiran', 'total_tepat_waktu', 'total_terlambat', 'total_sp', 'tingkat_disiplin_persen'],
                    'monthly_attendance',
                    'monthly_sp',
                    'top_unit_kerja' => ['disiplin', 'rawan'],
                ],
            ]);

        $this->assertCount(12, $response->json('data.monthly_attendance'));
        $this->assertCount(12, $response->json('data.monthly_sp'));
    }

    public function test_can_fetch_demografi_analytics(): void
    {
        $jad = \App\Models\Simpeg\JabatanFungsionalAkademik::create([
            'nama' => 'Asisten Ahli',
            'angka_kredit_min' => 100,
        ]);

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Dosen Demografi Test',
            'nip' => '198801012015011002',
            'jenis_pegawai' => 'dosen',
            'tanggal_lahir' => '1988-05-15',
            'jabatan_fungsional_id' => $jad->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $jenis = \App\Models\Simpeg\MasterJenisSertifikasi::create([
            'nama' => 'Pendidik',
            'kode' => 'SERDOS',
            'is_active' => true,
        ]);

        \App\Models\Simpeg\SertifikasiDosen::create([
            'pegawai_id' => $pegawai->id,
            'jenis_sertifikasi_id' => $jenis->id,
            'nama_sertifikat' => 'Sertifikat Pendidik Dosen',
            'bidang_studi' => 'Informatika',
            'penyelenggara' => 'Kemdikbudristek',
            'tahun_sertifikasi' => 2024,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/analytics/demografi');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'jad_pyramid',
                    'serdos_ratio' => ['total_dosen', 'tersertifikasi', 'belum_tersertifikasi', 'persentase'],
                    'age_distribution',
                    'summary' => ['total_dosen', 'total_pegawai_terdata', 'persentase_dosen_ber_jad', 'total_mpp_pensiun'],
                ],
            ]);

        $this->assertCount(5, $response->json('data.jad_pyramid'));
        $this->assertCount(5, $response->json('data.age_distribution'));
    }
}
