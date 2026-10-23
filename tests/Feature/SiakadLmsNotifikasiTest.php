<?php

namespace Tests\Feature;

use App\Models\Lms\LmsNotifikasi;
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
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pengujian notifikasi in-app modul LMS (LMS-04 / Fase 3A).
 *
 * Mencakup endpoint baca notifikasi, penandaan sudah dibaca, serta command
 * harian pengingat deadline tugas.
 */
class SiakadLmsNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMhs;
    protected User $userLain;
    protected Mahasiswa $mahasiswa;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userMhs = User::factory()->create([
            'username' => 'mhs.notifikasi',
            'email'    => 'mhs.notifikasi@test.ac.id',
        ]);

        $this->userLain = User::factory()->create([
            'username' => 'mhs.lain',
            'email'    => 'mhs.lain@test.ac.id',
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-NOTIF',
            'nama'       => 'Informatika Notifikasi',
            'jenjang'    => 'S1',
        ]);

        $ta = TahunAkademik::create([
            'kode'          => '20264',
            'nama'          => '2026/2027 Genap',
            'semester'      => 'genap',
            'tahun_mulai'  => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode'             => 'KUR-NOTIF',
            'nama'             => 'Kurikulum Notifikasi 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF402',
            'nama'             => 'Sistem Informasi Notifikasi',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 7,
            'is_active'        => true,
        ]);

        $dosen = Dosen::create([
            'program_studi_id' => $prodi->id,
            'nidn'             => '0099887768',
            'nama_lengkap'     => 'Dosen Notifikasi',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026004001',
            'nama_lengkap'     => 'Mahasiswa Notifikasi',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id'  => $prodi->id,
            'mata_kuliah_id'    => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas'        => 'IF-NOTIF-A',
            'nama_kelas'        => 'Kelas Notifikasi A',
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
            'materi'           => 'Pengantar Notifikasi',
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
    }

    /* -----------------------------------------------------------------
     | Endpoint notifikasi
     | ----------------------------------------------------------------- */

    public function test_pengguna_dapat_melihat_daftar_notifikasi_miliknya(): void
    {
        LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_DEADLINE_TUGAS,
            'judul'   => 'Tugas segera jatuh tempo',
            'pesan'   => 'Batas pengumpulan besok.',
            'is_read' => false,
        ]);

        Passport::actingAs($this->userMhs);

        $response = $this->getJson('/api/v1/lms/notifikasi');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                ['id', 'jenis', 'judul', 'pesan', 'is_read', 'read_at', 'created_at'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.judul', 'Tugas segera jatuh tempo');
    }

    public function test_notifikasi_milik_pengguna_lain_tidak_ditampilkan(): void
    {
        LmsNotifikasi::create([
            'user_id' => $this->userLain->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Notifikasi pribadi orang lain',
        ]);

        Passport::actingAs($this->userMhs);

        $response = $this->getJson('/api/v1/lms/notifikasi');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 0);
    }

    public function test_endpoint_notifikasi_menuntut_autentikasi(): void
    {
        $this->getJson('/api/v1/lms/notifikasi')->assertStatus(401);
    }

    public function test_notifikasi_dapat_ditandai_sudah_dibaca(): void
    {
        $notifikasi = LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_FORUM,
            'judul'   => 'Diskusi baru',
            'is_read' => false,
        ]);

        Passport::actingAs($this->userMhs);

        $response = $this->postJson('/api/v1/lms/notifikasi/'.$notifikasi->id.'/read');

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_read', true);
        $this->assertNotNull($notifikasi->fresh()->read_at);
    }

    public function test_notifikasi_milik_pengguna_lain_tidak_bisa_ditandai_dibaca(): void
    {
        $notifikasi = LmsNotifikasi::create([
            'user_id' => $this->userLain->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Milik orang lain',
        ]);

        Passport::actingAs($this->userMhs);

        $this->postJson('/api/v1/lms/notifikasi/'.$notifikasi->id.'/read')->assertStatus(404);
        $this->assertFalse((bool) $notifikasi->fresh()->is_read);
    }

    public function test_seluruh_notifikasi_dapat_ditandai_sudah_dibaca(): void
    {
        LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Satu',
        ]);
        LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Dua',
        ]);

        Passport::actingAs($this->userMhs);

        $response = $this->postJson('/api/v1/lms/notifikasi/read-all');

        $response->assertStatus(200);
        $response->assertJsonPath('data.updated', 2);
        $this->assertSame(0, LmsNotifikasi::where('user_id', $this->userMhs->id)->where('is_read', false)->count());
    }

    public function test_jumlah_notifikasi_belum_dibaca_valid(): void
    {
        LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Satu',
        ]);
        LmsNotifikasi::create([
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_INFO,
            'judul'   => 'Dua',
            'is_read' => true,
        ]);

        Passport::actingAs($this->userMhs);

        $response = $this->getJson('/api/v1/lms/notifikasi/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('data.unread_count', 1);
    }

    /* -----------------------------------------------------------------
     | Command pengingat deadline
     | ----------------------------------------------------------------- */

    public function test_command_deadline_membuat_notifikasi_untuk_mahasiswa(): void
    {
        Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas laporan',
            'deadline_at'  => now()->addHours(12),
            'is_published' => true,
        ]);

        $this->artisan('lms:notifikasi-deadline')->assertSuccessful();

        $this->assertDatabaseHas('lms_notifikasi', [
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_DEADLINE_TUGAS,
        ]);
    }

    public function test_command_deadline_bersifat_idempoten(): void
    {
        Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas idempoten',
            'deadline_at'  => now()->addHours(12),
            'is_published' => true,
        ]);

        $this->artisan('lms:notifikasi-deadline')->assertSuccessful();
        $this->artisan('lms:notifikasi-deadline')->assertSuccessful();

        $this->assertSame(1, LmsNotifikasi::where('user_id', $this->userMhs->id)->count());
    }

    public function test_mahasiswa_yang_sudah_mengumpulkan_tidak_dinotifikasi(): void
    {
        $tugas = Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas sudah dikumpulkan',
            'deadline_at'  => now()->addHours(12),
            'is_published' => true,
        ]);

        PengumpulanTugas::create([
            'tugas_id'     => $tugas->id,
            'mahasiswa_id' => $this->mahasiswa->id,
        ]);

        $this->artisan('lms:notifikasi-deadline')->assertSuccessful();

        $this->assertDatabaseMissing('lms_notifikasi', [
            'user_id' => $this->userMhs->id,
            'jenis'   => LmsNotifikasi::JENIS_DEADLINE_TUGAS,
        ]);
    }

    public function test_tugas_yang_belum_publish_tidak_dinotifikasi(): void
    {
        Tugas::create([
            'pertemuan_id' => $this->pertemuan->id,
            'judul'        => 'Tugas draf',
            'deadline_at'  => now()->addHours(12),
            'is_published' => false,
        ]);

        $this->artisan('lms:notifikasi-deadline')->assertSuccessful();

        $this->assertDatabaseMissing('lms_notifikasi', [
            'user_id' => $this->userMhs->id,
        ]);
    }
}
