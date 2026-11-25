<?php

namespace Tests\Feature;

use App\Models\Siakad\KrsDetail;
use App\Services\Siakad\KrsService;
use App\Services\Siakad\SiakadAkademikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * KRS historis dari Feeder disimpan per mata kuliah: siakad_krs_detail.kelas_id NULL,
 * mata_kuliah_id (+ master_kelas_id) terisi. Pembaca nilai/IPK/prasyarat harus tetap benar.
 */
class SiakadKrsHistorisMataKuliahTest extends TestCase
{
    use RefreshDatabase;

    private int $programId;
    private int $semesterId;
    private int $studentId;
    private int $courseId;
    private int $krsId;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $this->programId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => 'HIS-TI', 'nama' => 'Prodi Historis', 'jenjang' => 'S1',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->semesterId = DB::table('siakad_tahun_akademik')->insertGetId([
            'kode' => '20251', 'nama' => '2025/2026 Ganjil', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026,
            'is_active' => false, 'mode_penilaian' => 'persentase', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $curriculumId = DB::table('siakad_kurikulum')->insertGetId([
            'program_studi_id' => $this->programId, 'kode' => 'HIS-2025', 'nama' => 'Kurikulum Historis',
            'tahun_berlaku' => 2025, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->courseId = DB::table('siakad_mata_kuliah')->insertGetId([
            'kurikulum_id' => $curriculumId, 'kode_mk' => 'HIS101', 'nama' => 'Algoritma',
            'sks_teori' => 3, 'sks_praktik' => 0, 'total_sks' => 3, 'semester_anjuran' => 1,
            'tipe' => 'wajib', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->studentId = DB::table('siakad_mahasiswa')->insertGetId([
            'program_studi_id' => $this->programId, 'nim' => 'HIS2501', 'nama_lengkap' => 'Mahasiswa Historis',
            'angkatan' => 2025, 'status' => 'A', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->krsId = DB::table('siakad_krs')->insertGetId([
            'mahasiswa_id' => $this->studentId, 'tahun_akademik_id' => $this->semesterId,
            'total_sks_diambil' => 3, 'status' => 'dikunci', 'locked_by_keuangan' => false,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertGrade(int $detailId, string $huruf, float $mutu, float $akhir): void
    {
        DB::table('siakad_nilai_mahasiswa')->insert([
            'krs_detail_id' => $detailId, 'nilai_harian' => $akhir, 'nilai_uts' => $akhir, 'nilai_uas' => $akhir,
            'nilai_praktik' => $akhir, 'nilai_akhir' => $akhir, 'nilai_huruf' => $huruf, 'bobot_mutu' => $mutu,
            'is_final' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertHistoricalDetail(): int
    {
        return DB::table('siakad_krs_detail')->insertGetId([
            'krs_id' => $this->krsId, 'kelas_id' => null, 'mata_kuliah_id' => $this->courseId,
            'master_kelas_id' => null, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_krs_detail_accepts_null_class_and_new_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('siakad_krs_detail', 'mata_kuliah_id'));
        $this->assertTrue(Schema::hasColumn('siakad_krs_detail', 'master_kelas_id'));

        $detailId = $this->insertHistoricalDetail();
        $detail = KrsDetail::with('mataKuliah')->findOrFail($detailId);

        $this->assertNull($detail->kelas_id);
        $this->assertSame($this->courseId, $detail->mata_kuliah_efektif_id);
        $this->assertSame('HIS101', $detail->mata_kuliah_efektif?->kode_mk);
    }

    public function test_same_course_may_repeat_in_one_krs_without_class(): void
    {
        $this->insertHistoricalDetail();
        $this->insertHistoricalDetail();

        $this->assertSame(2, DB::table('siakad_krs_detail')->where('krs_id', $this->krsId)->count());
    }

    public function test_khs_and_ipk_use_course_without_class_instead_of_erroring(): void
    {
        $detailId = $this->insertHistoricalDetail();
        $this->insertGrade($detailId, 'A', 4.00, 90);

        $khs = app(SiakadAkademikService::class)->hitungKhsDanIpk($this->studentId, $this->semesterId);

        $this->assertNotNull($khs);
        $this->assertSame(3, (int) $khs->total_sks_semester);
        $this->assertEqualsWithDelta(4.00, (float) $khs->ips, 0.001);
        $this->assertSame(3, (int) $khs->sks_kumulatif);
        $this->assertEqualsWithDelta(4.00, (float) $khs->ipk, 0.001);
    }

    public function test_prerequisite_pass_check_sees_historical_grade_without_class(): void
    {
        $service = app(KrsService::class);
        $this->assertFalse($service->hasLulusMk($this->studentId, $this->courseId));
        $this->assertFalse($service->hasPernahAmbil($this->studentId, $this->courseId));

        $detailId = $this->insertHistoricalDetail();
        $this->insertGrade($detailId, 'B', 3.00, 78);

        $this->assertTrue($service->hasLulusMk($this->studentId, $this->courseId));
        $this->assertTrue($service->hasPernahAmbil($this->studentId, $this->courseId));
        $this->assertFalse($service->hasLulusMk($this->studentId, $this->courseId + 999));
    }

    public function test_failing_grade_without_class_does_not_count_as_passed(): void
    {
        $detailId = $this->insertHistoricalDetail();
        $this->insertGrade($detailId, 'E', 0.00, 20);

        $this->assertFalse(app(KrsService::class)->hasLulusMk($this->studentId, $this->courseId));
        $this->assertTrue(app(KrsService::class)->hasPernahAmbil($this->studentId, $this->courseId));
    }

    public function test_effective_course_prefers_class_when_present(): void
    {
        $classId = DB::table('siakad_jadwal_kelas')->insertGetId([
            'mata_kuliah_id' => $this->courseId, 'tahun_akademik_id' => $this->semesterId,
            'program_studi_id' => $this->programId, 'kode_kelas' => 'HIS-A', 'nama_kelas' => 'A',
            'kapasitas' => 40, 'kuota_krs' => 40, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $detailId = DB::table('siakad_krs_detail')->insertGetId([
            'krs_id' => $this->krsId, 'kelas_id' => $classId, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->insertGrade($detailId, 'A', 4.00, 90);

        $detail = KrsDetail::with(['kelas.mataKuliah', 'mataKuliah'])->findOrFail($detailId);
        $this->assertSame($this->courseId, $detail->mata_kuliah_efektif_id);
        $this->assertSame(3, (int) $detail->mata_kuliah_efektif?->total_sks);

        $khs = app(SiakadAkademikService::class)->hitungKhsDanIpk($this->studentId, $this->semesterId);
        $this->assertSame(3, (int) $khs->total_sks_semester);
        $this->assertTrue(app(KrsService::class)->hasLulusMk($this->studentId, $this->courseId));
    }
}
