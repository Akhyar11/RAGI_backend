<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lms\LmsNotifikasi;
use App\Models\Permission;
use App\Models\Role;
use App\Models\System\MasterReferensi;
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
use App\Models\Simpeg\PresensiPengajaranCutoff;
use App\Models\User;
use App\Services\Siakad\LmsNotificationService;
use App\Services\Simpeg\PresensiPengajaranService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SimpegPresensiPengajaranTest extends TestCase
{
    use RefreshDatabase;

    private User $dosenUser;
    private Kelas $kelas;
    private Pertemuan $pertemuan;
    private Mahasiswa $mahasiswa1;
    private Mahasiswa $mahasiswa2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->dosenUser = User::factory()->create([
            'username' => 'dosen.monitoring',
            'email' => 'dosen.monitoring@test.ac.id',
        ]);
        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-MON',
            'nama' => 'Informatika Monitoring',
            'jenjang' => 'S1',
        ]);
        $tahunAkademik = TahunAkademik::create([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'semester' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode' => 'KUR-MON',
            'nama' => 'Kurikulum Monitoring',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);
        $mataKuliah = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF901',
            'nama' => 'Pengujian Monitoring Presensi',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'total_sks' => 2,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);
        $dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'program_studi_id' => $prodi->id,
            'nidn' => '0099887711',
            'nama_lengkap' => 'Dosen Monitoring',
            'status_aktif' => 'aktif',
        ]);
        $this->mahasiswa1 = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => '2026009001',
            'nama_lengkap' => 'Mahasiswa Satu',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);
        $this->mahasiswa2 = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => '2026009002',
            'nama_lengkap' => 'Mahasiswa Dua',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);
        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'tahun_akademik_id' => $tahunAkademik->id,
            'kode_kelas' => 'IF-MON-A',
            'nama_kelas' => 'Kelas Monitoring',
            'kapasitas' => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $dosen->id,
            'peran' => 'pengampu_utama',
        ]);

        foreach ([$this->mahasiswa1, $this->mahasiswa2] as $mahasiswa) {
            $krs = Krs::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_id' => $tahunAkademik->id,
                'status' => 'disetujui',
                'total_sks_diambil' => 2,
            ]);
            KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_id' => $this->kelas->id,
                'status' => 'aktif',
            ]);
        }

        $this->pertemuan = Pertemuan::create([
            'kelas_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-11-08',
            'materi' => 'Pengantar',
            'status_pertemuan' => Pertemuan::STATUS_SELESAI,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monitoring_only_counts_submitted_attendance_and_requires_complete_roster(): void
    {
        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa1->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => false,
        ]);

        $report = app(PresensiPengajaranService::class)->report([
            'periode' => '2026-11',
            'per_page' => 15,
        ]);
        $row = $report->items()[0];
        $this->assertSame(1, $row['presensi_belum_diisi']);
        $this->assertSame(0, $row['presensi_terisi']);

        AbsensiMahasiswa::where('mahasiswa_id', $this->mahasiswa1->id)
            ->update(['is_submitted' => true]);
        $report = app(PresensiPengajaranService::class)->report([
            'periode' => '2026-11',
            'per_page' => 15,
        ]);
        $this->assertSame(1, $report->items()[0]['presensi_belum_diisi']);

        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa2->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => true,
        ]);
        $report = app(PresensiPengajaranService::class)->report([
            'periode' => '2026-11',
            'per_page' => 15,
        ]);
        $this->assertSame(0, $report->items()[0]['presensi_belum_diisi']);
        $this->assertSame('lengkap', $report->items()[0]['status_pemenuhan']);
    }

    public function test_reminder_repeats_until_all_enrolled_students_have_attendance_and_stops_after_cutoff(): void
    {
        PresensiPengajaranCutoff::create([
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-30 23:59:59',
        ]);
        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa1->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => true,
        ]);

        $service = app(LmsNotificationService::class);
        $this->assertSame(1, $service->pushReminderPresensiPengajaran()['pertemuan']);
        $this->assertSame(1, LmsNotifikasi::count());

        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->assertSame(1, $service->pushReminderPresensiPengajaran()['pertemuan']);
        $this->assertSame(2, LmsNotifikasi::count());

        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa2->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => true,
        ]);
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->assertSame(0, $service->pushReminderPresensiPengajaran()['pertemuan']);
        $this->assertSame(2, LmsNotifikasi::count());

        AbsensiMahasiswa::where('mahasiswa_id', $this->mahasiswa2->id)
            ->update(['is_submitted' => false]);
        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->assertSame(0, $service->pushReminderPresensiPengajaran()['pertemuan']);
    }

    public function test_cutoff_is_closed_at_the_exact_configured_time(): void
    {
        PresensiPengajaranCutoff::create([
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-09 10:00:00',
        ]);

        $this->assertTrue(app(PresensiPengajaranService::class)->isPeriodClosed('2026-11'));
    }

    private function roleWithPermissions(string $roleSlug, array $permissionSlugs): Role
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucwords(str_replace('_', ' ', $roleSlug))]
        );

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permissionIds[] = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'module' => explode('.', $slug)[0],
                'action' => str_ends_with($slug, '.read') ? 'read' : 'update',
            ])->id;
        }
        $role->permissions()->syncWithoutDetaching($permissionIds);

        return $role;
    }

    public function test_admin_simpeg_can_set_cutoff_and_each_change_is_audited(): void
    {
        $role = $this->roleWithPermissions('admin_simpeg', [
            'simpeg.presensi.pengajaran.read',
            'simpeg.presensi.pengajaran.manage',
        ]);
        $admin = User::factory()->create([
            'username' => 'admin.simpeg.cutoff',
            'email' => 'admin.simpeg.cutoff@test.ac.id',
        ]);
        $admin->roles()->attach($role->id);
        Passport::actingAs($admin);

        $this->putJson('/api/simpeg/presensi/pengajaran/cutoff', [
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-30 23:59:59',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.periode', '2026-11');

        $this->assertDatabaseHas('simpeg_presensi_pengajaran_cutoffs', [
            'periode' => '2026-11',
            'set_by' => $admin->id,
        ]);
        $cutoff = PresensiPengajaranCutoff::query()->where('periode', '2026-11')->firstOrFail();
        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIMPEG',
            'action' => 'create',
            'table_name' => $cutoff->getTable(),
            'record_id' => $cutoff->id,
            'user_id' => $admin->id,
        ]);

        $this->putJson('/api/simpeg/presensi/pengajaran/cutoff', [
            'periode' => '2026-11',
            'cutoff_at' => '2026-12-05 23:59:59',
        ])->assertOk();

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIMPEG',
            'action' => 'update',
            'table_name' => $cutoff->getTable(),
            'record_id' => $cutoff->id,
            'user_id' => $admin->id,
        ]);
        $updateLog = AuditLog::query()
            ->where('module', 'SIMPEG')
            ->where('action', 'update')
            ->where('record_id', $cutoff->id)
            ->firstOrFail();
        $this->assertSame('2026-11-30 23:59:59', $updateLog->old_values['cutoff_at']);
        $this->assertSame('2026-12-05 23:59:59', $updateLog->new_values['cutoff_at']);
    }

    public function test_role_without_manage_permission_cannot_change_cutoff(): void
    {
        $viewer = User::factory()->create([
            'username' => 'viewer.simpeg.cutoff',
            'email' => 'viewer.simpeg.cutoff@test.ac.id',
        ]);
        $viewer->roles()->attach($this->roleWithPermissions('dosen_presensi_viewer', [
            'simpeg.presensi.pengajaran.read',
        ])->id);
        Passport::actingAs($viewer);

        $this->putJson('/api/simpeg/presensi/pengajaran/cutoff', [
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-30 23:59:59',
        ])->assertForbidden();

        $this->assertDatabaseMissing('simpeg_presensi_pengajaran_cutoffs', ['periode' => '2026-11']);
    }

    public function test_superadmin_bypasses_cutoff_manage_permission(): void
    {
        $superadmin = User::factory()->create([
            'username' => 'superadmin.cutoff',
            'email' => 'superadmin.cutoff@test.ac.id',
        ]);
        $superadmin->roles()->attach(Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Admin']
        )->id);
        Passport::actingAs($superadmin);

        $this->putJson('/api/simpeg/presensi/pengajaran/cutoff', [
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-30 23:59:59',
        ])->assertOk();

        $this->assertDatabaseHas('simpeg_presensi_pengajaran_cutoffs', [
            'periode' => '2026-11',
            'set_by' => $superadmin->id,
        ]);
    }

    public function test_cutoff_blocks_lms_and_mobile_attendance_writes_until_exception_is_granted(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin.simpeg.exception',
            'email' => 'admin.simpeg.exception@test.ac.id',
        ]);
        $admin->roles()->attach($this->roleWithPermissions('admin_simpeg', [
            'simpeg.presensi.pengajaran.read',
            'simpeg.presensi.pengajaran.manage',
        ])->id);
        $this->dosenUser->roles()->attach($this->roleWithPermissions('dosen_cutoff_writer', [
            'siakad.kelas.manage',
        ])->id);

        $hadir = MasterReferensi::create([
            'tipe' => 'status_absensi',
            'modul' => 'siakad',
            'kode' => 'hadir',
            'nama' => 'Hadir',
            'is_active' => true,
        ]);
        PresensiPengajaranCutoff::create([
            'periode' => '2026-11',
            'cutoff_at' => '2026-11-09 09:00:00',
            'set_by' => $admin->id,
        ]);

        $message = 'Batas waktu pengisian presensi periode ini telah berakhir. Hubungi Admin SIMPEG jika memerlukan izin khusus.';
        $lmsUrl = "/api/v1/lms/pertemuan/{$this->pertemuan->id}/bulk-absensi";
        $lmsPayload = ['absensi' => [[
            'mahasiswa_id' => $this->mahasiswa1->id,
            'status_id' => $hadir->id,
        ]]];
        $mobileUrl = "/api/v1/mobile/staff/perkuliahan/pertemuan/{$this->pertemuan->id}/presensi";
        $mobilePayload = ['items' => [[
            'mahasiswa_id' => $this->mahasiswa1->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
        ]]];

        Passport::actingAs($this->dosenUser);
        $this->postJson($lmsUrl, $lmsPayload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.presensi.0', $message);
        $this->postJson($mobileUrl, $mobilePayload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.presensi.0', $message);
        $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$this->kelas->id}/mulai", [
            'pertemuan_ke' => 2,
            'materi' => 'Pertemuan setelah cutoff',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.presensi.0', $message);

        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa1->id,
            'is_submitted' => true,
        ]);
        $this->assertDatabaseMissing('siakad_pertemuan', [
            'kelas_id' => $this->kelas->id,
            'pertemuan_ke' => 2,
        ]);

        $dosen = Dosen::query()->where('user_id', $this->dosenUser->id)->firstOrFail();
        app(PresensiPengajaranService::class)->grantException([
            'dosen_id' => $dosen->id,
            'periode' => '2026-11',
            'valid_until' => '2026-11-30 23:59:59',
            'reason' => 'Penyesuaian jadwal perkuliahan.',
        ], $admin->id);

        $this->postJson($lmsUrl, $lmsPayload)->assertOk();
        $this->postJson($mobileUrl, $mobilePayload)->assertOk();
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa1->id,
            'is_submitted' => true,
        ]);
    }
}
