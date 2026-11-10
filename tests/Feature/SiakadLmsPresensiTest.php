<?php

namespace Tests\Feature;

use App\Models\Lms\LmsSetting;
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
use App\Models\Permission;
use App\Models\Role;
use App\Models\System\MasterReferensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pengujian monitoring presensi lokal RAG/SIAKAD dan feature flag LMS.
 */
class SiakadLmsPresensiTest extends TestCase
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

        $this->userAdmin = User::factory()->create([
            'username' => 'admin.presensi',
            'email'    => 'admin.presensi@test.ac.id',
        ]);

        $this->userMhs = User::factory()->create([
            'username' => 'mhs.presensi',
            'email'    => 'mhs.presensi@test.ac.id',
        ]);

        $this->userDosen = User::factory()->create([
            'username' => 'dosen.presensi',
            'email'    => 'dosen.presensi@test.ac.id',
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-PRES',
            'nama'       => 'Informatika Presensi',
            'jenjang'    => 'S1',
        ]);

        $ta = TahunAkademik::create([
            'kode'          => '20263',
            'nama'          => '2026/2027 Ganjil',
            'semester'      => 'ganjil',
            'tahun_mulai'  => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode'             => 'KUR-PRES',
            'nama'             => 'Kurikulum Presensi 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF401',
            'nama'             => 'Sistem Informasi Presensi',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 7,
            'is_active'        => true,
        ]);

        $dosen = Dosen::create([
            'user_id'          => $this->userDosen->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0099887767',
            'nama_lengkap'     => 'Dosen Presensi',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026003001',
            'nama_lengkap'     => 'Mahasiswa Presensi',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id'  => $prodi->id,
            'mata_kuliah_id'    => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas'        => 'IF-PRES-A',
            'nama_kelas'        => 'Kelas Presensi A',
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
            'materi'           => 'Pengantar Presensi',
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
            'status'       => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => true,
        ]);

        // ── RBAC ──────────────────────────────────────────────────────
        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $roleMhs   = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);

        $permRead   = Permission::firstOrCreate(['slug' => 'lms.presensi.read'], ['name' => 'Lihat Rekap Presensi LMS', 'module' => 'lms', 'action' => 'read']);
        $permManage = Permission::firstOrCreate(['slug' => 'lms.presensi.manage'], ['name' => 'Export Laporan Presensi LMS', 'module' => 'lms', 'action' => 'update']);
        $permKelas  = Permission::firstOrCreate(['slug' => 'siakad.kelas.read'], ['name' => 'Lihat Jadwal Kuliah', 'module' => 'siakad', 'action' => 'read']);
        $permManageKelas = Permission::firstOrCreate(['slug' => 'siakad.kelas.manage'], ['name' => 'Kelola Kelas', 'module' => 'siakad', 'action' => 'update']);

        $roleAdmin->permissions()->syncWithoutDetaching([
            $permRead->id,
            $permManage->id,
            $permKelas->id,
        ]);
        $roleMhs->permissions()->syncWithoutDetaching([
            $permKelas->id,
        ]);
        $roleDosen->permissions()->syncWithoutDetaching([
            $permKelas->id,
            $permManageKelas->id,
        ]);

        $this->userAdmin->roles()->attach($roleAdmin->id);
        $this->userMhs->roles()->attach($roleMhs->id);
        $this->userDosen->roles()->attach($roleDosen->id);
    }

    /* -----------------------------------------------------------------
     | Endpoint panel administrasi
     | ----------------------------------------------------------------- */

    public function test_user_tanpa_izin_ditolak_akses_endpoint_admin_presensi(): void
    {
        Passport::actingAs($this->userMhs);

        $this->getJson('/api/v1/lms/admin/presensi/kelas')->assertStatus(403);
        $this->getJson('/api/v1/lms/admin/presensi/rekap')->assertStatus(403);
    }

    public function test_dosen_can_refresh_live_attendance_roster_but_student_cannot_read_class_roster(): void
    {
        Passport::actingAs($this->userDosen);

        $this->getJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/absensi")
            ->assertOk()
            ->assertJsonPath('data.total_peserta', 1)
            ->assertJsonPath('data.total_sudah_absen', 1)
            ->assertJsonPath('data.absensi_list.0.mahasiswa_id', $this->mahasiswa->id)
            ->assertJsonPath('data.absensi_list.0.status', AbsensiMahasiswa::STATUS_HADIR);

        Passport::actingAs($this->userMhs);

        $this->getJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/absensi")
            ->assertForbidden();
    }

    public function test_manual_attendance_accepts_active_custom_status_and_rejects_other_reference_types(): void
    {
        $customStatus = MasterReferensi::create([
            'tipe' => 'status_absensi',
            'modul' => 'siakad',
            'kode' => 'kepentingan_khusus',
            'nama' => 'Kepentingan Khusus',
            'is_active' => true,
        ]);
        $unrelatedReference = MasterReferensi::create([
            'tipe' => 'agama',
            'modul' => 'siakad',
            'kode' => 'uji_tidak_valid',
            'nama' => 'Bukan Status Absensi',
            'is_active' => true,
        ]);

        Passport::actingAs($this->userDosen);

        $url = "/api/v1/lms/pertemuan/{$this->pertemuan->id}/bulk-absensi";
        $payload = ['absensi' => [[
            'mahasiswa_id' => $this->mahasiswa->id,
            'status_id' => $customStatus->id,
        ]]];
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status' => 'kepentingan_khusus',
        ]);
        $attendanceId = AbsensiMahasiswa::query()
            ->where('pertemuan_id', $this->pertemuan->id)
            ->where('mahasiswa_id', $this->mahasiswa->id)
            ->value('id');
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'LMS',
            'action' => 'update',
            'table_name' => 'siakad_absensi_mahasiswa',
            'record_id' => $attendanceId,
        ]);

        $payload['absensi'][0]['status_id'] = $unrelatedReference->id;
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status' => 'kepentingan_khusus',
        ]);

        $nonparticipant = Mahasiswa::create([
            'user_id' => User::factory()->create()->id,
            'program_studi_id' => $this->mahasiswa->program_studi_id,
            'nim' => '2026003999',
            'nama_lengkap' => 'Mahasiswa Non Peserta',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);
        $payload['absensi'][0] = [
            'mahasiswa_id' => $nonparticipant->id,
            'status_id' => $customStatus->id,
        ];
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $nonparticipant->id,
        ]);
    }

    public function test_admin_bisa_mengambil_daftar_kelas_dengan_ringkasan_rekap(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->getJson('/api/v1/lms/admin/presensi/kelas?search=IF-PRES');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                [
                    'id',
                    'kode_kelas',
                    'nama_kelas',
                    'jumlah_sesi',
                    'jumlah_rekap',
                    'jumlah_hadir',
                ],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
            'filters' => ['search', 'sort_by', 'sort_order'],
        ]);

        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.kode_kelas', 'IF-PRES-A');
        $response->assertJsonPath('data.0.jumlah_sesi', 1);
        $response->assertJsonPath('data.0.jumlah_rekap', 1);
        $response->assertJsonPath('data.0.jumlah_hadir', 1);
    }

    public function test_admin_bisa_mengambil_rekap_kehadiran_dengan_filter_kelas(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->getJson("/api/v1/lms/admin/presensi/rekap?kelas_id={$this->kelas->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.nim', '2026003001');
        $response->assertJsonPath('data.0.nama_lengkap', 'Mahasiswa Presensi');
        $response->assertJsonPath('data.0.status', AbsensiMahasiswa::STATUS_HADIR);
        $response->assertJsonPath('data.0.kode_kelas', 'IF-PRES-A');
    }

    public function test_admin_bisa_export_rekap_presensi_dalam_format_csv(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->get('/api/v1/lms/admin/presensi/export?format=csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV wajib memakai BOM UTF-8.');
        $this->assertStringContainsString('2026003001', $content);
        $this->assertStringContainsString('Hadir', $content);
    }

    public function test_endpoint_sinkronisasi_legacy_sudah_tidak_tersedia(): void
    {
        Passport::actingAs($this->userAdmin);

        $this->getJson('/api/v1/lms/admin/presensi/status')->assertNotFound();
        $this->getJson('/api/v1/lms/admin/presensi/log')->assertNotFound();
        $this->postJson('/api/v1/lms/admin/presensi/sync', ['kelas_id' => $this->kelas->id])->assertNotFound();
        $this->postJson('/api/v1/lms/admin/presensi/tarik', ['kelas_id' => $this->kelas->id])->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Feature flag
     | ----------------------------------------------------------------- */

    public function test_feature_flag_mematikan_presensi_untuk_non_admin(): void
    {
        LmsSetting::updateOrCreate(
            ['key' => LmsSetting::KEY_FITUR_PRESENSI],
            ['value' => '0', 'description' => 'Fitur presensi dimatikan oleh admin.']
        );
        LmsSetting::flushCache();

        Passport::actingAs($this->userMhs);

        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/rekap-absensi")->assertStatus(403);
    }

    public function test_admin_tetap_bisa_mengakses_presensi_saat_feature_flag_mati(): void
    {
        LmsSetting::updateOrCreate(
            ['key' => LmsSetting::KEY_FITUR_PRESENSI],
            ['value' => '0', 'description' => 'Fitur presensi dimatikan oleh admin.']
        );
        LmsSetting::flushCache();

        Passport::actingAs($this->userAdmin);

        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/rekap-absensi")->assertStatus(200);
    }

    /* -----------------------------------------------------------------
     | Monitoring Keaktifan Dosen & Export
     | ----------------------------------------------------------------- */

    public function test_admin_dapat_melihat_monitoring_keaktifan_dosen(): void
    {
        Passport::actingAs($this->userAdmin);

        $res = $this->getJson('/api/v1/lms/admin/presensi/keaktifan-dosen');

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.kode_kelas', 'IF-PRES-A')
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
            ]);
    }

    public function test_admin_dapat_export_laporan_keaktifan_dosen(): void
    {
        Passport::actingAs($this->userAdmin);

        $res = $this->get('/api/v1/lms/admin/presensi/keaktifan-dosen/export?format=csv');

        $res->assertStatus(200);
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type') ?? '');
    }

    public function test_mahasiswa_dilarang_mengakses_monitoring_keaktifan_dosen(): void
    {
        Passport::actingAs($this->userMhs);

        $this->getJson('/api/v1/lms/admin/presensi/keaktifan-dosen')
            ->assertStatus(403);
    }
}
