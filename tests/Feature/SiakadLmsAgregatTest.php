<?php

namespace Tests\Feature;

use App\Models\Lms\Quiz;
use App\Models\Permission;
use App\Models\Role;
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
 * Endpoint agregat level modul LMS (dipakai halaman /lms/pertemuan,
 * /lms/tryout, dan /lms/pengaturan).
 *
 * Fokus test: pagination di level query, echo filter, dan yang paling penting —
 * filter `kelas_id` TIDAK boleh membuka akses ke kelas di luar hak user.
 */
class SiakadLmsAgregatTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected Kelas $kelasA;
    protected Kelas $kelasLuar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.agregat', 'email' => 'dosen.agregat@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.agregat', 'email' => 'mhs.agregat@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-AGR', 'nama' => 'Informatika Agregat', 'jenjang' => 'S1']);
        $ta = TahunAkademik::create([
            'kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil',
            'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-AGR',
            'nama' => 'Kurikulum Agregat', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF701', 'nama' => 'MK Agregat',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 4, 'is_active' => true,
        ]);

        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0077007700', 'nama_lengkap' => 'Dosen Agregat', 'status_aktif' => 'aktif',
        ]);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026007001', 'nama_lengkap' => 'Mahasiswa Agregat', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);

        // Kelas A diampu dosen ini; Kelas Luar sengaja tanpa DosenPengampu.
        $this->kelasA = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-AGR-A',
            'nama_kelas' => 'Kelas Agregat A', 'kapasitas' => 30,
        ]);
        DosenPengampu::create(['kelas_id' => $this->kelasA->id, 'dosen_id' => $dosen->id, 'peran' => 'pengampu_utama']);

        $this->kelasLuar = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-AGR-B',
            'nama_kelas' => 'Kelas Agregat B', 'kapasitas' => 30,
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $ta->id,
            'status' => 'disetujui', 'total_sks' => 3,
        ]);
        KrsDetail::create(['krs_id' => $krs->id, 'kelas_id' => $this->kelasA->id, 'status' => 'aktif']);

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $perms = [];
        foreach ([
            'siakad.kelas.read' => 'read',
            'siakad.kelas.manage' => 'update',
            'siakad.krs.read' => 'read',
        ] as $slug => $act) {
            $perms[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'siakad', 'action' => $act]
            );
        }
        $roleDosen->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
            $perms['siakad.kelas.manage']->id,
        ]);
        $roleMhs->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
            $perms['siakad.krs.read']->id,
        ]);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);

        // Data meeting pada kedua kelas.
        foreach ([$this->kelasA, $this->kelasLuar] as $i => $kelas) {
            Pertemuan::create([
                'kelas_id' => $kelas->id,
                'pertemuan_ke' => 1,
                'tanggal' => '2026-10-0' . ($i + 1),
                'materi' => 'Materi Aggregat ' . $kelas->kode_kelas,
                'status_pertemuan' => Pertemuan::STATUS_BELUM,
                'jam_mulai' => '08:00',
                'jam_selesai' => '10:00',
            ]);
            Quiz::create([
                'kelas_id' => $kelas->id,
                'tipe' => 'tryout',
                'judul' => 'Tryout Agregat ' . $kelas->kode_kelas,
                'durasi_menit' => 60,
                'is_published' => true,
                'dibuat_oleh' => $this->userDosen->id,
            ]);
        }
    }

    public function test_daftar_pertemuan_agregat_dibatasi_kelas_yang_diampu(): void
    {
        Passport::actingAs($this->userDosen);

        $semua = $this->getJson('/api/v1/lms/pertemuan');
        $semua->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.kelas_id', null);
        $this->assertSame([$this->kelasA->id], collect($semua->json('data'))->pluck('kelas_id')->all());

        // Filter kelas sendiri tetap mengembalikan datanya.
        $pilihSendiri = $this->getJson("/api/v1/lms/pertemuan?kelas_id={$this->kelasA->id}");
        $pilihSendiri->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.kelas_id', $this->kelasA->id);

        // Kelas yang tidak diakses → kosong, bukan 403 dan bukan data kelas itu.
        $pilihAsing = $this->getJson("/api/v1/lms/pertemuan?kelas_id={$this->kelasLuar->id}");
        $pilihAsing->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_daftar_tryout_agregat_dibatasi_kelas_yang_diampu(): void
    {
        Passport::actingAs($this->userDosen);

        $semua = $this->getJson('/api/v1/lms/tryout');
        $semua->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.kelas_id', null);
        $this->assertSame([$this->kelasA->id], collect($semua->json('data'))->pluck('kelas_id')->all());

        $pilihSendiri = $this->getJson("/api/v1/lms/tryout?kelas_id={$this->kelasA->id}");
        $pilihSendiri->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.kelas_id', $this->kelasA->id);

        $pilihAsing = $this->getJson("/api/v1/lms/tryout?kelas_id={$this->kelasLuar->id}");
        $pilihAsing->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_daftar_pengaturan_agregat_hanya_kelas_yang_diampu(): void
    {
        Passport::actingAs($this->userDosen);

        $res = $this->getJson('/api/v1/lms/pengaturan');
        $res->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->assertSame([$this->kelasA->id], collect($res->json('data'))->pluck('id')->all());

        // Kelas yang tidak dikonfigurasi tetap muncul dengan nilai bawaan.
        $this->assertNull($res->json('data.0.lms_setting'));
    }

    public function test_filter_metode_absensi_menyaring_nilai_efektif(): void
    {
        Passport::actingAs($this->userDosen);

        // Belum dikonfigurasi → nilai efektif = nilai bawaan (keduanya).
        $bawaan = $this->getJson('/api/v1/lms/pengaturan?metode_absensi=keduanya');
        $bawaan->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.metode_absensi', 'keduanya');
        $this->assertSame([$this->kelasA->id], collect($bawaan->json('data'))->pluck('id')->all());

        // Nilai lain tidak cocok untuk kelas yang belum dikonfigurasi.
        $lain = $this->getJson('/api/v1/lms/pengaturan?metode_absensi=manual_dosen');
        $lain->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        // Nilai di luar closed-set diabaikan diam-diam, bukan 422.
        $ngawur = $this->getJson('/api/v1/lms/pengaturan?metode_absensi=ngawur');
        $ngawur->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.metode_absensi', null);
    }

    public function test_filter_status_konfigurasi_memisahkan_kelas_belum_dikonfigurasi(): void
    {
        Passport::actingAs($this->userDosen);

        $belum = $this->getJson('/api/v1/lms/pengaturan?status_konfigurasi=belum_terkonfigurasi');
        $belum->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.status_konfigurasi', 'belum_terkonfigurasi');

        $sudah = $this->getJson('/api/v1/lms/pengaturan?status_konfigurasi=terkonfigurasi');
        $sudah->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $ngawur = $this->getJson('/api/v1/lms/pengaturan?status_konfigurasi=ngawur');
        $ngawur->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.status_konfigurasi', null);
    }
}