<?php

namespace Tests\Feature;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PenilaianKinerja;
use App\Models\Simpeg\SkPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\UnitKerja;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\MataKuliah;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\User;
use Database\Seeders\SimpegIzinDanSkMasterSeeder;
use Database\Seeders\SimpegSkpMasterSeeder;
use Database\Seeders\SimpegSuratTugasMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegTridharmaDossierTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userDosen;
    protected Pegawai $pegawai;
    protected Dosen $dosen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->seed(SimpegSuratTugasMasterSeeder::class);
        $this->seed(SimpegIzinDanSkMasterSeeder::class);
        $this->seed(SimpegSkpMasterSeeder::class);

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $unitKerja = UnitKerja::create([
            'nama' => 'Teknik Informatika',
            'kode' => 'TI',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        $this->userDosen = User::factory()->create([
            'username' => 'dosen.ahmad',
            'email' => 'ahmad@campus.ac.id',
        ]);

        $this->pegawai = Pegawai::create([
            'user_id' => $this->userDosen->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '198805122015041003',
            'nik' => '3271011205880001',
            'nidn' => '0412058801',
            'nama_lengkap' => 'Ahmad Dahlan, M.T.',
            'tanggal_lahir' => '1988-05-12',
            'tempat_lahir' => 'Surabaya',
            'jenis_kelamin' => 'L',
            'status_pegawai' => 'tetap',
            'status_aktif' => true,
            'tanggal_masuk' => '2015-04-01',
            'sinta_id' => '6021849',
            'scopus_id' => '57201948211',
        ]);

        $prodi = MasterProgramStudi::create([
            'nama' => 'Teknik Informatika',
            'kode_prodi' => 'TIF',
            'jenjang' => 'S1',
        ]);

        $this->dosen = Dosen::create([
            'pegawai_id' => $this->pegawai->id,
            'user_id' => $this->userDosen->id,
            'nidn' => '0412058801',
            'nip' => '198805122015041003',
            'nama_lengkap' => 'Ahmad Dahlan, M.T.',
            'program_studi_id' => $prodi->id,
            'jabatan_akademik' => 'Lektor',
            'is_active' => true,
        ]);
    }

    public function test_get_dossier_returns_aggregated_data_successfully(): void
    {
        // 1. Mock Pengajaran SIAKAD
        $ta = MasterTahunAkademik::create([
            'kode' => '20251',
            'nama' => '2025/2026 Ganjil',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'is_active' => true,
        ]);

        $kurikulum = \App\Models\Siakad\Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-2025',
            'nama' => 'Kurikulum OBE 2025',
            'tahun_berlaku' => 2025,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF301',
            'nama' => 'Rekayasa Perangkat Lunak',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 5,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas' => 'TI-3A',
            'nama_kelas' => 'Kelas Reguler A',
            'kapasitas' => 40,
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'pengampu_utama',
        ]);

        // 2. Mock Penelitian SIPPM
        $periode = PeriodeHibah::create([
            'tahun_anggaran' => '2026',
            'nama_gelombang' => 'Hibah Penelitian Internal 2026',
            'tgl_buka_proposal' => '2026-01-01',
            'tgl_tutup_proposal' => '2026-12-31',
            'is_active' => true,
        ]);

        $skema = SkemaKegiatan::create([
            'kode' => 'RIS-INT',
            'nama' => 'Penelitian Dosen Pemula',
            'tipe' => 'penelitian',
            'sumber_dana' => 'internal',
            'maksimal_anggaran' => 25000000,
        ]);

        ProposalKegiatan::create([
            'periode_id' => $periode->id,
            'skema_id' => $skema->id,
            'ketua_pegawai_id' => $this->pegawai->id,
            'kode_proposal' => 'PROP-2026-001',
            'judul' => 'Penerapan AI untuk Rekomendasi Akademik',
            'abstrak' => 'Abstrak penelitian...',
            'rumpun_ilmu' => 'Informatika',
            'target_tkt' => 3,
            'anggaran_diajukan' => 20000000,
            'anggaran_disetujui' => 18000000,
            'file_proposal' => 'proposals/prop-1.pdf',
            'status' => 'didanai',
        ]);

        PublikasiIlmiah::create([
            'pegawai_id' => $this->pegawai->id,
            'judul_artikel' => 'Deep Learning in Academic Advisory Systems',
            'jenis_publikasi' => 'jurnal_internasional_bereputasi',
            'nama_jurnal_prosiding' => 'IEEE Access (Scopus Q1)',
            'indexing' => 'scopus_q1',
            'volume_issue_tahun' => 'Vol. 10, No. 2, 2026',
            'doi' => '10.1109/ACCESS.2026.0001',
            'is_verified_lppm' => true,
        ]);

        // 3. Mock Kinerja SIMPEG
        PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2025,
            'semester' => 'tahunan',
            'status' => 'dinilai',
            'nilai_skp' => 92.50,
            'nilai_bkd' => 14.00,
            'predikat' => 'sangat_baik',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/simpeg/pegawai/{$this->pegawai->id}/tridharma-dossier");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pegawai.nidn', '0412058801')
            ->assertJsonPath('data.metrics.total_kelas_ajar', 1)
            ->assertJsonPath('data.metrics.total_sks_ajar', 3)
            ->assertJsonPath('data.metrics.total_penelitian', 1)
            ->assertJsonPath('data.metrics.total_publikasi', 1)
            ->assertJsonPath('data.metrics.total_publikasi_scopus', 1)
            ->assertJsonPath('data.metrics.rerata_nilai_skp', 92.5)
            ->assertJsonPath('data.pengajaran.kelas.0.kode_mk', 'IF301')
            ->assertJsonPath('data.penelitian.hibah_ketua.0.judul', 'Penerapan AI untuk Rekomendasi Akademik');
    }

    public function test_self_dosen_can_access_own_dossier(): void
    {
        $response = $this->actingAs($this->userDosen, 'api')
            ->getJson("/api/simpeg/pegawai/{$this->pegawai->id}/tridharma-dossier");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pegawai.id', $this->pegawai->id);
    }
}
