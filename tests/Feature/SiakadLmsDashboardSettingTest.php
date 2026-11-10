<?php

namespace Tests\Feature;

use App\Models\Lms\PengumpulanTugas;
use App\Models\Lms\Quiz;
use App\Models\Lms\Tugas;
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
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pengujian dashboard LMS & pengaturan level modul (LMS-07 / Fase 5).
 *
 * Mencakup ringkasan split role (mahasiswa vs pengelola), endpoint pengaturan
 * modul (`lms_*` pada `core_system_settings`), validasi, audit log, serta
 * endpoint pengaturan publik tanpa autentikasi.
 */
class SiakadLmsDashboardSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $userAdmin;
    protected User $userMhs;
    protected User $userDosen;
    protected Mahasiswa $mahasiswa;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Cache::flush();

        $this->userAdmin = User::factory()->create([
            'username' => 'admin.lmsdash',
            'email'    => 'admin.lmsdash@test.ac.id',
        ]);

        $this->userMhs = User::factory()->create([
            'username' => 'mhs.lmsdash',
            'email'    => 'mhs.lmsdash@test.ac.id',
        ]);

        $this->userDosen = User::factory()->create([
            'username' => 'dosen.lmsdash',
            'email'    => 'dosen.lmsdash@test.ac.id',
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-DASH',
            'nama'       => 'Informatika Dashboard',
            'jenjang'    => 'S1',
        ]);

        $ta = TahunAkademik::create([
            'kode'          => '20263',
            'nama'          => '2026/2027 Ganjil',
            'semester'      => 'ganjil',
            'tahun_mulai'   => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode'             => 'KUR-DASH',
            'nama'             => 'Kurikulum Dashboard 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF405',
            'nama'             => 'Sistem Informasi Dashboard',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 7,
            'is_active'        => true,
        ]);

        $dosen = Dosen::create([
            'user_id'          => $this->userDosen->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0099887700',
            'nama_lengkap'     => 'Dosen Dashboard',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026005001',
            'nama_lengkap'     => 'Mahasiswa Dashboard',
            'angkatan'         => 2026,
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id'  => $prodi->id,
            'mata_kuliah_id'    => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas'        => 'IF-DASH-A',
            'nama_kelas'        => 'Kelas Dashboard A',
            'kapasitas'         => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $dosen->id,
            'peran'    => 'pengampu_utama',
        ]);

        $this->pertemuan = Pertemuan::create([
            'kelas_id'         => $this->kelas->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Pengantar Dashboard',
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

        // ── RBAC ──────────────────────────────────────────────────────
        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $roleMhs   = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);

        $permKelas = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.read'],
            ['name' => 'Lihat Jadwal Kuliah', 'module' => 'siakad', 'action' => 'read']
        );
        $permRead = Permission::firstOrCreate(
            ['slug' => 'lms.pengaturan.read'],
            ['name' => 'Lihat Pengaturan Modul LMS', 'module' => 'lms', 'action' => 'read']
        );
        $permManage = Permission::firstOrCreate(
            ['slug' => 'lms.pengaturan.manage'],
            ['name' => 'Kelola Pengaturan Modul LMS', 'module' => 'lms', 'action' => 'update']
        );

        $roleAdmin->permissions()->syncWithoutDetaching([$permKelas->id, $permRead->id, $permManage->id]);
        $roleMhs->permissions()->syncWithoutDetaching([$permKelas->id]);
        $roleDosen->permissions()->syncWithoutDetaching([$permKelas->id]);

        $this->userAdmin->roles()->attach($roleAdmin->id);
        $this->userMhs->roles()->attach($roleMhs->id);
        $this->userDosen->roles()->attach($roleDosen->id);
    }

    private function buatTugas(bool $published = true): Tugas
    {
        return Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas Dashboard',
            'deadline_at'  => now()->addDays(3),
            'maks_nilai'   => 100,
            'is_published' => $published,
        ]);
    }

    public function test_dashboard_mahasiswa_menampilkan_ringkasan_dan_tugas_belum_dikumpul(): void
    {
        $this->buatTugas();

        Passport::actingAs($this->userMhs);

        $response = $this->getJson('/api/v1/lms/dashboard/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'mahasiswa');
        $response->assertJsonPath('data.ringkasan.kelas', 1);
        $response->assertJsonPath('data.ringkasan.tugas_aktif', 1);
        $response->assertJsonPath('data.tambahan.tugas_belum_dikumpul', 1);
        $response->assertJsonCount(7, 'data.tren');
        $response->assertJsonStructure([
            'data' => [
                'role',
                'ringkasan' => ['kelas', 'tugas_aktif', 'tryout_aktif', 'forum_topik', 'notifikasi_belum_dibaca'],
                'tambahan',
                'tren' => [['tanggal', 'pengumpulan', 'attempt']],
            ],
        ]);
    }

    public function test_dashboard_pengelola_menampilkan_pengumpulan_belum_dinilai(): void
    {
        $tugas = $this->buatTugas();

        PengumpulanTugas::create([
            'tugas_id'     => $tugas->id,
            'mahasiswa_id' => $this->mahasiswa->id,
        ]);

        Passport::actingAs($this->userAdmin);

        $response = $this->getJson('/api/v1/lms/dashboard/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'pengelola');
        $response->assertJsonPath('data.ringkasan.kelas', 1);
        $response->assertJsonPath('data.tambahan.pengumpulan_belum_dinilai', 1);
        $response->assertJsonPath('data.tambahan.attempt_belum_dinilai', 0);
    }

    public function test_dashboard_dosen_hanya_menghitung_kelas_yang_diampu(): void
    {
        $this->buatTugas();

        Passport::actingAs($this->userDosen);

        $response = $this->getJson('/api/v1/lms/dashboard/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'dosen');
        $response->assertJsonPath('data.ringkasan.kelas', 1);
        $response->assertJsonPath('data.tambahan.pengumpulan_belum_dinilai', 0);
    }

    public function test_dashboard_menampilkan_jadwal_siakad_dan_deadline_quiz_dalam_scope_kelas_user(): void
    {
        $hariPekan = ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
        $hariIni = $hariPekan[now()->dayOfWeek];
        $this->kelas->update([
            'hari' => $hariIni,
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '10:30:00',
        ]);
        $this->buatTugas();

        $quiz = Quiz::create([
            'pertemuan_id' => $this->pertemuan->id,
            'tipe' => Quiz::TIPE_KUIS,
            'judul' => 'Kuis Dashboard',
            'dibuka_at' => now()->subHour(),
            'ditutup_at' => now()->addDays(2),
            'is_published' => true,
        ]);

        Kelas::create([
            'program_studi_id' => $this->kelas->program_studi_id,
            'mata_kuliah_id' => $this->kelas->mata_kuliah_id,
            'tahun_akademik_id' => $this->kelas->tahun_akademik_id,
            'kode_kelas' => 'IF-DASH-OUTSIDE',
            'nama_kelas' => 'Kelas Di Luar Akses',
            'kapasitas' => 30,
            'hari' => $hariIni,
            'jam_mulai' => '11:00:00',
            'jam_selesai' => '12:00:00',
        ]);

        foreach ([$this->userDosen, $this->userMhs] as $user) {
            Passport::actingAs($user);

            $response = $this->getJson('/api/v1/lms/dashboard/overview')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'jadwal' => [
                            'hari_ini',
                            'tanggal',
                            'kelas' => [
                                'hari_ini' => [['id', 'kode_kelas', 'hari', 'jam_mulai', 'jam_selesai', 'mata_kuliah']],
                                'semua' => [['id', 'kode_kelas', 'hari', 'jam_mulai', 'jam_selesai', 'mata_kuliah']],
                            ],
                        ],
                        'preview' => [
                            'kuis' => [['id', 'judul', 'tipe', 'dibuka_at', 'deadline_at', 'kelas_id', 'pertemuan_id']],
                        ],
                    ],
                ]);

            $response->assertJsonPath('data.jadwal.hari_ini', $hariIni);
            $response->assertJsonPath('data.jadwal.kelas.hari_ini.0.id', $this->kelas->id);
            $response->assertJsonPath('data.jadwal.kelas.semua.0.jam_mulai', '09:00:00');
            $response->assertJsonCount(1, 'data.jadwal.kelas.semua');
            $response->assertJsonPath('data.preview.tugas.0.judul', 'Tugas Dashboard');
            $response->assertJsonCount(1, 'data.preview.kuis');
            $response->assertJsonPath('data.preview.kuis.0.id', $quiz->id);
        }
    }

    public function test_pengaturan_modul_hanya_dapat_diakses_admin(): void
    {
        Passport::actingAs($this->userMhs);
        $this->getJson('/api/v1/lms/pengaturan/modul')->assertStatus(403);
        $this->putJson('/api/v1/lms/pengaturan/modul', ['lms_upload_max_size_mb' => 5])->assertStatus(403);

        Passport::actingAs($this->userDosen);
        $this->getJson('/api/v1/lms/pengaturan/modul')->assertStatus(403);

        Passport::actingAs($this->userAdmin);
        $response = $this->getJson('/api/v1/lms/pengaturan/modul');

        $response->assertStatus(200);
        $response->assertJsonPath('data.values.lms_max_soal_import', 500);
        $response->assertJsonPath('data.values.lms_upload_max_size_mb', 100);
        $response->assertJsonPath('data.values.lms_video_max_size_mb', 500);
        $response->assertJsonPath('data.values.lms_fitur_presensi', true);
        $response->assertJsonCount(4, 'data.definitions');
        $response->assertJsonStructure([
            'data' => [
                'values',
                'definitions' => [['key', 'label', 'group', 'type', 'default', 'description']],
            ],
        ]);
    }

    public function test_admin_dapat_menyimpan_pengaturan_modul_dan_tercatat_di_audit(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->putJson('/api/v1/lms/pengaturan/modul', [
            'lms_max_soal_import'    => 50,
            'lms_upload_max_size_mb' => 10,
            'lms_video_max_size_mb'  => 500,
            'lms_fitur_presensi'     => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.lms_max_soal_import', 50);
        $response->assertJsonPath('data.lms_upload_max_size_mb', 10);
        $response->assertJsonPath('data.lms_video_max_size_mb', 500);
        $response->assertJsonPath('data.lms_fitur_presensi', false);

        $this->assertDatabaseHas('core_system_settings', ['key' => 'lms_max_soal_import', 'value' => '50']);
        $this->assertDatabaseHas('core_system_settings', ['key' => 'lms_fitur_presensi', 'value' => '0']);
        $this->assertDatabaseHas('core_audit_logs', [
            'module'     => 'LMS',
            'action'     => 'update',
            'table_name' => 'core_system_settings',
        ]);

        // Pengaturan publik mengikuti perubahan (cache di-flush).
        $this->getJson('/api/v1/lms/public/settings')
            ->assertStatus(200)
            ->assertJsonPath('data.lms_upload_max_size_mb', 10)
            ->assertJsonPath('data.lms_fitur_presensi', false);
    }

    public function test_pengaturan_modul_menolak_nilai_di_luar_batas(): void
    {
        Passport::actingAs($this->userAdmin);

        $this->putJson('/api/v1/lms/pengaturan/modul', ['lms_upload_max_size_mb' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lms_upload_max_size_mb']);

        $this->putJson('/api/v1/lms/pengaturan/modul', ['lms_video_max_size_mb' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lms_video_max_size_mb']);
    }

    public function test_pengaturan_publik_tanpa_autentikasi_hanya_memuat_key_publik(): void
    {
        $response = $this->getJson('/api/v1/lms/public/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('data.lms_upload_max_size_mb', 100);
        $response->assertJsonPath('data.lms_video_max_size_mb', 500);
        $response->assertJsonPath('data.lms_fitur_presensi', true);
        $response->assertJsonMissingPath('data.lms_max_soal_import');
    }
}
