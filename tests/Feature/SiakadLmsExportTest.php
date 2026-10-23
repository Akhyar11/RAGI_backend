<?php

namespace Tests\Feature;

use App\Models\Lms\Quiz;
use App\Models\Lms\QuizAttempt;
use App\Models\Lms\Tugas;
use App\Models\Lms\TryoutPeserta;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pengujian endpoint export laporan LMS (CSV & XLSX).
 */
class SiakadLmsExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected Dosen $dosen;
    protected Mahasiswa $mahasiswa;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;
    protected Tugas $tugas;
    protected Quiz $quiz;
    protected Quiz $tryout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create([
            'username' => 'dosen.export',
            'email'    => 'dosen.export@test.ac.id',
        ]);

        $this->userMhs = User::factory()->create([
            'username' => 'mhs.export',
            'email'    => 'mhs.export@test.ac.id',
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-EXP',
            'nama'       => 'Informatika Export',
            'jenjang'    => 'S1',
        ]);

        $ta = TahunAkademik::create([
            'kode'          => '20262',
            'nama'          => '2026/2027 Genap',
            'semester'      => 'genap',
            'tahun_mulai'  => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode'             => 'KUR-EXP',
            'nama'             => 'Kurikulum Export 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF301',
            'nama'             => 'Analisis Data LMS',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 5,
            'is_active'        => true,
        ]);

        $this->dosen = Dosen::create([
            'user_id'          => $this->userDosen->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0099887766',
            'nama_lengkap'     => 'Dosen Export',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026002001',
            'nama_lengkap'     => 'Mahasiswa Export',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id'  => $prodi->id,
            'mata_kuliah_id'    => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas'        => 'IF-EXP-A',
            'nama_kelas'        => 'Kelas Export A',
            'kapasitas'         => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran'    => 'pengampu_utama',
        ]);

        $this->pertemuan = Pertemuan::create([
            'kelas_id'         => $this->kelas->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Rekapitulasi Absensi',
            'status_pertemuan' => Pertemuan::STATUS_BELUM,
        ]);

        $krs = Krs::create([
            'mahasiswa_id'      => $this->mahasiswa->id,
            'tahun_akademik_id' => $ta->id,
            'status'            => 'disetujui',
            'total_sks'         => 3,
        ]);

        KrsDetail::create([
            'krs_id'   => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status'   => 'aktif',
        ]);

        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'hadir',
        ]);

        $this->tugas = Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas Ekspor',
            'deadline_at'  => now()->addDays(7),
            'maks_nilai'   => 100,
            'is_published' => true,
        ]);

        $this->quiz = Quiz::create([
            'kelas_id'      => $this->kelas->id,
            'tipe'          => Quiz::TIPE_KUIS,
            'judul'         => 'Kuis Ekspor',
            'durasi_menit'  => 60,
            'is_published'  => true,
            'dibuat_oleh'   => $this->userDosen->id,
        ]);

        $this->tryout = Quiz::create([
            'kelas_id'      => $this->kelas->id,
            'tipe'          => Quiz::TIPE_TRYOUT,
            'judul'         => 'Tryout Ekspor',
            'durasi_menit'  => 90,
            'is_published'  => true,
            'dibuat_oleh'   => $this->userDosen->id,
        ]);

        QuizAttempt::create([
            'quiz_id'      => $this->quiz->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'attempt_ke'   => 1,
            'status'       => 'selesai',
            'nilai_akhir'  => 87.50,
            'dimulai_at'   => now()->subMinutes(30),
            'disubmit_at'  => now()->subMinutes(10),
        ]);

        TryoutPeserta::create([
            'quiz_id'      => $this->tryout->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'ditambah_oleh' => $this->userDosen->id,
        ]);

        // ── RBAC ──────────────────────────────────────────────────────
        $roleDosen = \App\Models\Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs   = \App\Models\Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);

        $perms = [];
        foreach ([
            'siakad.kelas.read'   => 'read',
            'siakad.kelas.manage' => 'update',
            'siakad.nilai.manage' => 'update',
        ] as $slug => $action) {
            $perms[$slug] = \App\Models\Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'siakad', 'action' => $action]
            );
        }

        $roleDosen->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
            $perms['siakad.kelas.manage']->id,
            $perms['siakad.nilai.manage']->id,
        ]);
        $roleMhs->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
        ]);

        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
    }

    public function test_dosen_bisa_export_rekap_absensi_dalam_format_csv(): void
    {
        Passport::actingAs($this->userDosen);

        $response = $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/presensi");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertDownload('Rekap_Absensi_IF-EXP-A_' . now()->format('Ymd_His') . '.csv');

        $csv = $response->streamedContent();

        // BOM UTF-8 wajib ada agar Excel di Windows membaca karakter Indonesia.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $baris = array_map(
            'str_getcsv',
            array_filter(explode("\r\n", substr($csv, 3)))
        );
        $this->assertSame(
            ['Kode Kelas', 'Nama Kelas', 'Mata Kuliah', 'Pertemuan Ke', 'Tanggal', 'Topik Pertemuan', 'NIM', 'Nama Mahasiswa', 'Status Kehadiran', 'Catatan'],
            $baris[0]
        );
        $this->assertSame('IF-EXP-A', $baris[1][0]);
        $this->assertSame('2026002001', $baris[1][6]);
        $this->assertSame('Mahasiswa Export', $baris[1][7]);
        $this->assertSame('Hadir', $baris[1][8]);
    }

    public function test_dosen_bisa_export_rekap_nilai_kelas_dan_pengumpulan_tugas(): void
    {
        Passport::actingAs($this->userDosen);

        $nilai = $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/nilai");
        $nilai->assertStatus(200);

        $csvNilai = $nilai->streamedContent();
        $this->assertStringContainsString('Tugas: Tugas Ekspor', $csvNilai);
        $this->assertStringContainsString('Quiz: Kuis Ekspor', $csvNilai);
        $this->assertStringContainsString('2026002001', $csvNilai);
        $this->assertStringContainsString('87.5', $csvNilai);

        $pengumpulan = $this->get("/api/v1/lms/tugas/{$this->tugas->id}/export/pengumpulan");
        $pengumpulan->assertStatus(200);
        $this->assertStringContainsString('Belum Mengumpulkan', $pengumpulan->streamedContent());
    }

    public function test_dosen_bisa_export_hasil_quiz_dan_tryout(): void
    {
        Passport::actingAs($this->userDosen);

        $hasilQuiz = $this->get("/api/v1/lms/quiz/{$this->quiz->id}/export/hasil");
        $hasilQuiz->assertStatus(200);
        $this->assertStringContainsString('Kuis Ekspor', $hasilQuiz->streamedContent());

        $hasilTryout = $this->get("/api/v1/lms/tryout/{$this->tryout->id}/export/hasil");
        $hasilTryout->assertStatus(200);
        $this->assertStringContainsString('Tryout Ekspor', $hasilTryout->streamedContent());
    }

    public function test_endpoint_tryout_menolak_quiz_biasa(): void
    {
        Passport::actingAs($this->userDosen);

        $this->get("/api/v1/lms/tryout/{$this->quiz->id}/export/hasil")->assertStatus(422);
    }

    public function test_export_absensi_izin_menghasilkan_berkas(): void
    {
        Passport::actingAs($this->userDosen);

        $response = $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/absensi-izin");
        $response->assertStatus(200);

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Tipe Izin', $csv);
    }

    public function test_export_berformat_xlsx_menghasilkan_berkas_spreadsheet(): void
    {
        Passport::actingAs($this->userDosen);

        $response = $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/presensi?format=xlsx");

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // XLSX adalah file zip (signature "PK\x03\x04").
        $this->assertStringStartsWith("PK\x03\x04", $response->streamedContent());
    }

    public function test_format_export_tidak_valid_ditolak(): void
    {
        Passport::actingAs($this->userDosen);

        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/export/presensi?format=pdf")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message', 'errors' => ['format']]);
    }

    public function test_mahasiswa_tidak_boleh_mengekspor_laporan_kelas(): void
    {
        Passport::actingAs($this->userMhs);

        $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/presensi")->assertStatus(403);
        $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/nilai")->assertStatus(403);
    }

    public function test_dosen_luar_kelas_tidak_bisa_mengekspor_laporan(): void
    {
        $userLuar = User::factory()->create([
            'username' => 'dosen.luar.export',
            'email'    => 'dosen.luar.export@test.ac.id',
        ]);

        Dosen::create([
            'user_id'          => $userLuar->id,
            'program_studi_id' => $this->kelas->program_studi_id,
            'nidn'             => '0011229988',
            'nama_lengkap'     => 'Dosen Luar Kelas',
            'status_aktif'     => 'aktif',
        ]);

        $roleDosen = \App\Models\Role::where('slug', 'dosen_pengajar')->first();
        $userLuar->roles()->attach($roleDosen->id);

        Passport::actingAs($userLuar);

        $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/presensi")->assertStatus(422);
        $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/nilai")->assertStatus(422);
        $this->get("/api/v1/lms/tugas/{$this->tugas->id}/export/pengumpulan")->assertStatus(422);
    }

    public function test_ekspor_laporan_mencatat_audit_log(): void
    {
        Passport::actingAs($this->userDosen);

        $this->get("/api/v1/lms/kelas/{$this->kelas->id}/export/nilai")->assertStatus(200);

        $this->assertDatabaseHas('core_audit_logs', [
            'user_id'    => $this->userDosen->id,
            'module'     => 'LMS',
            'action'     => 'export',
            'table_name' => 'siakad_kelas',
            'record_id'  => $this->kelas->id,
        ]);
    }
}
