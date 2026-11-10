<?php

namespace Tests\Feature;

use App\Models\Lms\LmsNotifikasi;
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
}
