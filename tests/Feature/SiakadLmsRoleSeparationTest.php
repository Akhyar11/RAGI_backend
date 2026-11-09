<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Lms\PengumpulanTugas;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadLmsRoleSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $userDosenA;
    protected User $userDosenB;
    protected User $userKaprodiA;
    protected User $userMhsEnrolled;
    protected User $userMhsUnenrolled;

    protected Dosen $dosenA;
    protected Dosen $dosenB;
    protected Dosen $dosenKaprodiA;
    protected Mahasiswa $mhsEnrolled;
    protected Mahasiswa $mhsUnenrolled;

    protected ProgramStudi $prodiA;
    protected ProgramStudi $prodiB;
    protected TahunAkademik $ta;
    protected Kurikulum $kurikulumA;
    protected Kurikulum $kurikulumB;
    protected MataKuliah $mkA;
    protected MataKuliah $mkB;

    protected Kelas $kelasA;
    protected Kelas $kelasKaprodiA;
    protected Kelas $kelasB;
    protected Pertemuan $pertemuanA;
    protected Pertemuan $pertemuanKaprodiA;
    protected Pertemuan $pertemuanB;
    protected Tugas $tugasA;
    protected PengumpulanTugas $pengumpulanA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('local');

        $roleSuperAdmin = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Admin', 'description' => 'Super Administrator']
        );
        $roleDosen = Role::firstOrCreate(
            ['slug' => 'dosen'],
            ['name' => 'Dosen', 'description' => 'Dosen Pengajar']
        );
        $roleMahasiswa = Role::firstOrCreate(
            ['slug' => 'mahasiswa'],
            ['name' => 'Mahasiswa', 'description' => 'Mahasiswa']
        );

        $permKelasRead = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.read'],
            ['name' => 'Lihat Kelas', 'module' => 'siakad', 'action' => 'read']
        );
        $permKelasManage = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.manage'],
            ['name' => 'Kelola Kelas', 'module' => 'siakad', 'action' => 'update']
        );
        $permPresensiRead = Permission::firstOrCreate(
            ['slug' => 'lms.presensi.read'],
            ['name' => 'Monitoring Presensi LMS', 'module' => 'lms', 'action' => 'read']
        );
        $roleSuperAdmin->permissions()->syncWithoutDetaching([$permKelasRead->id, $permKelasManage->id, $permPresensiRead->id]);
        $roleDosen->permissions()->syncWithoutDetaching([$permKelasRead->id, $permKelasManage->id]);
        $roleMahasiswa->permissions()->syncWithoutDetaching([$permKelasRead->id]);

        // Super Admin TANPA profil Dosen maupun Mahasiswa
        $this->superAdmin = User::factory()->create([
            'username' => 'superadmin.lms',
            'email'    => 'superadmin.lms@test.ac.id',
        ]);
        $this->superAdmin->roles()->sync([$roleSuperAdmin->id]);

        $this->userDosenA = User::factory()->create([
            'username' => 'dosen.a',
            'email'    => 'dosen.a@test.ac.id',
        ]);
        $this->userDosenA->roles()->sync([$roleDosen->id]);

        $this->userDosenB = User::factory()->create([
            'username' => 'dosen.b',
            'email'    => 'dosen.b@test.ac.id',
        ]);
        $this->userDosenB->roles()->sync([$roleDosen->id]);

        $this->userKaprodiA = User::factory()->create([
            'username' => 'pejabat.prodi.a',
            'email'    => 'pejabat.prodi.a@test.ac.id',
        ]);
        $this->userKaprodiA->roles()->sync([$roleDosen->id]);

        $this->userMhsEnrolled = User::factory()->create([
            'username' => 'mhs.enrolled',
            'email'    => 'mhs.enrolled@test.ac.id',
        ]);
        $this->userMhsEnrolled->roles()->sync([$roleMahasiswa->id]);

        $this->userMhsUnenrolled = User::factory()->create([
            'username' => 'mhs.unenrolled',
            'email'    => 'mhs.unenrolled@test.ac.id',
        ]);
        $this->userMhsUnenrolled->roles()->sync([$roleMahasiswa->id]);

        $this->prodiA = ProgramStudi::create([
            'kode_prodi' => 'IF-ROLESEP-A',
            'nama'       => 'Informatika A',
            'jenjang'    => 'S1',
        ]);
        $this->prodiB = ProgramStudi::create([
            'kode_prodi' => 'SI-ROLESEP-B',
            'nama'       => 'Sistem Informasi B',
            'jenjang'    => 'S1',
        ]);

        $this->ta = TahunAkademik::create([
            'kode'          => '20261',
            'nama'          => '2026/2027 Ganjil',
            'semester'      => 'ganjil',
            'tahun_mulai'   => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $this->kurikulumA = Kurikulum::create([
            'program_studi_id' => $this->prodiA->id,
            'kode'             => 'KUR-A',
            'nama'             => 'Kurikulum A',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);
        $this->kurikulumB = Kurikulum::create([
            'program_studi_id' => $this->prodiB->id,
            'kode'             => 'KUR-B',
            'nama'             => 'Kurikulum B',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $this->mkA = MataKuliah::create([
            'kurikulum_id'     => $this->kurikulumA->id,
            'kode_mk'          => 'IFA101',
            'nama'             => 'Algoritma A',
            'sks_teori'        => 3,
            'sks_praktik'      => 0,
            'total_sks'        => 3,
            'semester_anjuran' => 1,
            'is_active'        => true,
        ]);
        $this->mkB = MataKuliah::create([
            'kurikulum_id'     => $this->kurikulumB->id,
            'kode_mk'          => 'SIB101',
            'nama'             => 'Manajemen SI B',
            'sks_teori'        => 3,
            'sks_praktik'      => 0,
            'total_sks'        => 3,
            'semester_anjuran' => 1,
            'is_active'        => true,
        ]);

        $this->dosenA = Dosen::create([
            'user_id'          => $this->userDosenA->id,
            'program_studi_id' => $this->prodiA->id,
            'nidn'             => '1111111101',
            'nama_lengkap'     => 'Dosen A',
            'status_aktif'     => 'aktif',
        ]);
        $this->dosenB = Dosen::create([
            'user_id'          => $this->userDosenB->id,
            'program_studi_id' => $this->prodiB->id,
            'nidn'             => '1111111102',
            'nama_lengkap'     => 'Dosen B',
            'status_aktif'     => 'aktif',
        ]);
        $this->dosenKaprodiA = Dosen::create([
            'user_id'          => $this->userKaprodiA->id,
            'program_studi_id' => $this->prodiA->id,
            'nidn'             => '1111111103',
            'nama_lengkap'     => 'Kaprodi A',
            'status_aktif'     => 'aktif',
        ]);
        $this->prodiA->update(['kaprodi_id' => $this->dosenKaprodiA->id]);

        $this->mhsEnrolled = Mahasiswa::create([
            'user_id'          => $this->userMhsEnrolled->id,
            'program_studi_id' => $this->prodiA->id,
            'nim'              => '2026990001',
            'nama_lengkap'     => 'Mahasiswa Enrolled',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);
        $this->mhsUnenrolled = Mahasiswa::create([
            'user_id'          => $this->userMhsUnenrolled->id,
            'program_studi_id' => $this->prodiA->id,
            'nim'              => '2026990002',
            'nama_lengkap'     => 'Mahasiswa Unenrolled',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        // Kelas A: Prodi A, diampu oleh Dosen A
        $this->kelasA = Kelas::create([
            'program_studi_id'  => $this->prodiA->id,
            'mata_kuliah_id'    => $this->mkA->id,
            'tahun_akademik_id' => $this->ta->id,
            'kode_kelas'        => 'IFA-01',
            'nama_kelas'        => 'Kelas Algoritma A1',
            'kapasitas'         => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelasA->id,
            'dosen_id' => $this->dosenA->id,
            'peran'    => 'pengampu_utama',
        ]);

        // Kelas Kaprodi A: Prodi A, diampu langsung oleh Kaprodi A
        $this->kelasKaprodiA = Kelas::create([
            'program_studi_id'  => $this->prodiA->id,
            'mata_kuliah_id'    => $this->mkA->id,
            'tahun_akademik_id' => $this->ta->id,
            'kode_kelas'        => 'IFA-02',
            'nama_kelas'        => 'Kelas Algoritma A2 (Kaprodi)',
            'kapasitas'         => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelasKaprodiA->id,
            'dosen_id' => $this->dosenKaprodiA->id,
            'peran'    => 'pengampu_utama',
        ]);

        // Kelas B: Prodi B, diampu oleh Dosen B
        $this->kelasB = Kelas::create([
            'program_studi_id'  => $this->prodiB->id,
            'mata_kuliah_id'    => $this->mkB->id,
            'tahun_akademik_id' => $this->ta->id,
            'kode_kelas'        => 'SIB-01',
            'nama_kelas'        => 'Kelas SI B1',
            'kapasitas'         => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelasB->id,
            'dosen_id' => $this->dosenB->id,
            'peran'    => 'pengampu_utama',
        ]);

        $this->pertemuanA = Pertemuan::create([
            'kelas_id'         => $this->kelasA->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Sesi 1 Kelas A',
            'status_pertemuan' => 'berlangsung',
        ]);
        $this->pertemuanKaprodiA = Pertemuan::create([
            'kelas_id'         => $this->kelasKaprodiA->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Sesi 1 Kelas Kaprodi A',
            'status_pertemuan' => 'berlangsung',
        ]);
        $this->pertemuanB = Pertemuan::create([
            'kelas_id'         => $this->kelasB->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Sesi 1 Kelas B',
            'status_pertemuan' => 'berlangsung',
        ]);

        $krsEnrolled = Krs::create([
            'mahasiswa_id'      => $this->mhsEnrolled->id,
            'tahun_akademik_id' => $this->ta->id,
            'status'            => 'disetujui',
            'total_sks'         => 3,
        ]);
        KrsDetail::create([
            'krs_id'   => $krsEnrolled->id,
            'kelas_id' => $this->kelasA->id,
            'status'   => 'aktif',
        ]);

        $this->tugasA = Tugas::create([
            'pertemuan_id'    => $this->pertemuanA->id,
            'judul'           => 'Tugas 1 Kelas A',
            'deskripsi'       => 'Kerjakan soal algoritma',
            'deadline_at'     => now()->addDays(3),
            'maks_nilai'      => 100,
            'can_submit_late' => true,
            'is_published'    => true,
        ]);

        $this->pengumpulanA = PengumpulanTugas::create([
            'tugas_id'          => $this->tugasA->id,
            'mahasiswa_id'      => $this->mhsEnrolled->id,
            'catatan_mahasiswa' => 'Jawaban mahasiswa enrolled',
            'is_late'           => false,
        ]);

        $now = now();
        $refItems = [
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'teks', 'nama' => 'Artikel Teks', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_izin', 'kode' => 'sakit', 'nama' => 'Sakit', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'hadir', 'nama' => 'Hadir', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'metode_absensi_lms', 'kode' => 'keduanya', 'nama' => 'Token & Manual', 'is_active' => true],
        ];
        foreach ($refItems as $item) {
            DB::table('spmb_master_referensi')->updateOrInsert(
                ['modul' => $item['modul'], 'tipe' => $item['tipe'], 'kode' => $item['kode']],
                array_merge($item, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function test_superadmin_without_academic_profile_acts_solely_as_pengelola(): void
    {
        Passport::actingAs($this->superAdmin);

        // 1. Dashboard Overview harus mengembalikan role = 'pengelola'
        $dashRes = $this->getJson('/api/v1/lms/dashboard/overview');
        $dashRes->assertStatus(200)
            ->assertJsonPath('data.role', 'pengelola')
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'ringkasan',
                    'tambahan' => [
                        'kelas_belum_dikonfigurasi',
                        'pengumpulan_belum_dinilai',
                        'attempt_belum_dinilai',
                    ],
                ],
            ]);

        // 2. Super Admin dapat memonitor overview kelas & detail pertemuan dengan viewer_mode = 'pengelola'
        $overviewRes = $this->getJson("/api/v1/lms/kelas/{$this->kelasA->id}/overview");
        $overviewRes->assertStatus(200)
            ->assertJsonPath('data.viewer_context.viewer_mode', 'pengelola')
            ->assertJsonPath('data.viewer_context.can_teach', false)
            ->assertJsonPath('data.viewer_context.can_participate', false)
            ->assertJsonPath('data.viewer_context.can_configure_kelas', true)
            ->assertJsonPath('data.viewer_context.can_export', true);

        $detailRes = $this->getJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}");
        $detailRes->assertStatus(200)
            ->assertJsonPath('data.viewer_context.viewer_mode', 'pengelola')
            ->assertJsonPath('data.viewer_context.can_teach', false)
            ->assertJsonPath('data.viewer_context.can_participate', false);

        // 3. Super Admin boleh mengubah konfigurasi kebijakan kelas LMS
        $settingRes = $this->putJson("/api/v1/lms/kelas/{$this->kelasA->id}/setting", [
            'total_pertemuan'         => 16,
            'batas_min_hadir_persen'  => 75,
            'metode_absensi'          => 'keduanya',
            'can_submit_late'         => true,
            'show_nilai_to_mahasiswa' => true,
        ]);
        $settingRes->assertStatus(200);

        // 4. Super Admin DILARANG melakukan aksi akademik dosen pengampu (403)
        $tipeKontenId = DB::table('spmb_master_referensi')->where('tipe', 'tipe_konten_lms')->where('kode', 'teks')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/materi", [
            'judul'          => 'Materi Ilegal Superadmin',
            'tipe_konten_id' => $tipeKontenId,
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/tugas", [
            'judul'      => 'Tugas Ilegal Superadmin',
            'maks_nilai' => 100,
        ])->assertStatus(403);

        $this->putJson("/api/v1/lms/pengumpulan/{$this->pengumpulanA->id}/nilai", [
            'nilai' => 95,
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/token")
            ->assertStatus(403);

        $statusHadirId = DB::table('spmb_master_referensi')->where('tipe', 'status_absensi')->where('kode', 'hadir')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/bulk-absensi", [
            'absensi' => [
                ['mahasiswa_id' => $this->mhsEnrolled->id, 'status_id' => $statusHadirId],
            ],
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/quiz", [
            'judul'       => 'Quiz Ilegal Superadmin',
            'max_attempt' => 1,
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/kelas/{$this->kelasA->id}/tryout", [
            'judul' => 'Tryout Ilegal Superadmin',
        ])->assertStatus(403);

        // 5. Super Admin DILARANG melakukan aksi partisipasi mahasiswa (403)
        $this->postJson("/api/v1/lms/tugas/{$this->tugasA->id}/kumpul", [
            'catatan_mahasiswa' => 'Superadmin ikut kumpul tugas',
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/input-token", [
            'token' => 'ABCDEF',
        ])->assertStatus(403);

        $tipeIzinId = DB::table('spmb_master_referensi')->where('tipe', 'tipe_izin')->where('kode', 'sakit')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/izin", [
            'tipe_izin_id' => $tipeIzinId,
            'alasan'       => 'Superadmin ajukan izin',
        ])->assertStatus(403);
    }

    public function test_dosen_cannot_view_or_mutate_unassigned_class(): void
    {
        Passport::actingAs($this->userDosenB);

        // Dosen B bukan pengampu Kelas A -> tidak boleh lihat overview atau detail pertemuan Kelas A
        $this->getJson("/api/v1/lms/kelas/{$this->kelasA->id}/overview")
            ->assertStatus(403);

        $this->getJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}")
            ->assertStatus(403);

        // Dosen B tidak boleh tambah materi, nilai tugas, atau buka token di Kelas A
        $tipeKontenId = DB::table('spmb_master_referensi')->where('tipe', 'tipe_konten_lms')->where('kode', 'teks')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/materi", [
            'judul'          => 'Materi Dosen B di Kelas A',
            'tipe_konten_id' => $tipeKontenId,
        ])->assertStatus(403);

        $this->putJson("/api/v1/lms/pengumpulan/{$this->pengumpulanA->id}/nilai", [
            'nilai' => 80,
        ])->assertStatus(403);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/token")
            ->assertStatus(403);
    }

    public function test_mahasiswa_requires_valid_krs_enrollment_for_class_access_and_participation(): void
    {
        // 1. Mahasiswa yang sama sekali tidak mengambil Kelas A ditolak (403)
        Passport::actingAs($this->userMhsUnenrolled);

        $this->getJson("/api/v1/lms/kelas/{$this->kelasA->id}/overview")
            ->assertStatus(403);

        $this->getJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}")
            ->assertStatus(403);

        $this->postJson("/api/v1/lms/tugas/{$this->tugasA->id}/kumpul", [
            'catatan_mahasiswa' => 'Mhs luar kelas kumpul tugas',
        ])->assertStatus(403);

        $tipeIzinId = DB::table('spmb_master_referensi')->where('tipe', 'tipe_izin')->where('kode', 'sakit')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/izin", [
            'tipe_izin_id' => $tipeIzinId,
            'alasan'       => 'Sakit demam',
        ])->assertStatus(403);

        // 2. Jika Mahasiswa punya KRS di Kelas A tetapi status KRS masih 'draft' atau 'ditolak', tetap ditolak
        $krsDraft = Krs::create([
            'mahasiswa_id'      => $this->mhsUnenrolled->id,
            'tahun_akademik_id' => $this->ta->id,
            'status'            => 'draft',
            'total_sks'         => 3,
        ]);
        KrsDetail::create([
            'krs_id'   => $krsDraft->id,
            'kelas_id' => $this->kelasA->id,
            'status'   => 'aktif',
        ]);

        $this->getJson("/api/v1/lms/kelas/{$this->kelasA->id}/overview")
            ->assertStatus(403);

        $this->postJson("/api/v1/lms/tugas/{$this->tugasA->id}/kumpul", [
            'catatan_mahasiswa' => 'Mhs KRS draft kumpul tugas',
        ])->assertStatus(403);
    }

    public function test_kaprodi_monitors_prodi_classes_readonly_and_teaches_own_assigned_class(): void
    {
        Passport::actingAs($this->userKaprodiA);

        // 1. Kelas A (Prodi A, diampu Dosen A, bukan Kaprodi A) -> mode pengelola (read-only untuk pengajaran)
        $resKelasA = $this->getJson("/api/v1/lms/kelas/{$this->kelasA->id}/overview");
        $resKelasA->assertStatus(200)
            ->assertJsonPath('data.viewer_context.viewer_mode', 'pengelola')
            ->assertJsonPath('data.viewer_context.can_teach', false)
            ->assertJsonPath('data.viewer_context.can_configure_kelas', false)
            ->assertJsonPath('data.viewer_context.can_export', true);

        $tipeKontenId = DB::table('spmb_master_referensi')->where('tipe', 'tipe_konten_lms')->where('kode', 'teks')->value('id');
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanA->id}/materi", [
            'judul'          => 'Materi Kaprodi di Kelas Dosen A',
            'tipe_konten_id' => $tipeKontenId,
        ])->assertStatus(403);

        // 2. Kelas Kaprodi A (Prodi A, diampu langsung oleh Kaprodi A) -> mode dosen (can_teach = true)
        $resKelasKaprodi = $this->getJson("/api/v1/lms/kelas/{$this->kelasKaprodiA->id}/overview");
        $resKelasKaprodi->assertStatus(200)
            ->assertJsonPath('data.viewer_context.viewer_mode', 'dosen')
            ->assertJsonPath('data.viewer_context.can_teach', true);

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuanKaprodiA->id}/materi", [
            'judul'          => 'Materi Kaprodi di Kelas Sendiri',
            'tipe_konten_id' => $tipeKontenId,
        ])->assertStatus(201);

        // 3. Kelas B (Prodi B, di luar Prodi Kaprodi A) -> ditolak (403)
        $this->getJson("/api/v1/lms/kelas/{$this->kelasB->id}/overview")
            ->assertStatus(403);
    }
}
