<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bukti end-to-end: opsi form master SIAKAD berasal dari database
 * (master referensi), terkirim via API, dan benar-benar tersimpan
 * sebagai baris database — sekaligus menolak kode yang tidak terdaftar.
 */
class SiakadMasterReferensiFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Ambil kode opsi dari database, bukan literal. */
    private function masterKode(string $tipe, int $index = 0): string
    {
        $row = DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')
            ->where('tipe', $tipe)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->skip($index)
            ->first();

        $this->assertNotNull($row, "Master referensi {$tipe} kosong — migrasi seed belum jalan.");
        return $row->kode;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->admin = User::factory()->create();
        Passport::actingAs($this->admin);
    }

    public function test_referensi_options_endpoint_menyajikan_opsi_dari_database()
    {
        $response = $this->getJson('/api/v1/siakad/akademik/referensi-options?tipe=jenjang_prodi');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data' => [['kode', 'nama', 'urutan']]]);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_alur_form_prodi_tersimpan_ke_database()
    {
        $fakultas = $this->postJson('/api/v1/siakad/akademik/fakultas', [
            'kode' => 'FTI',
            'nama' => 'Fakultas Teknologi Informasi',
        ]);
        $fakultas->assertStatus(201);

        $jenjang = $this->masterKode('jenjang_prodi');
        $akreditasi = $this->masterKode('akreditasi_prodi');

        $response = $this->postJson('/api/v1/siakad/akademik/prodi', [
            'fakultas_id' => $fakultas->json('data.id'),
            'kode_prodi' => 'IF',
            'kode_prodi_dikti' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => $jenjang,
            'akreditasi' => $akreditasi,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'message', 'data' => ['id', 'jenjang', 'akreditasi']]);

        // Baris database-nya sungguhan:
        $this->assertDatabaseHas('spmb_master_program_studi', [
            'kode_prodi' => 'IF',
            'jenjang' => $jenjang,
            'akreditasi' => $akreditasi,
        ]);

        // Kode ngawur ditolak:
        $rejected = $this->postJson('/api/v1/siakad/akademik/prodi', [
            'fakultas_id' => $fakultas->json('data.id'),
            'kode_prodi' => 'SI',
            'nama' => 'Sistem Informasi',
            'jenjang' => 'STRATA-NGAWUR',
        ]);
        $rejected->assertStatus(422);
    }

    public function test_alur_form_mata_kuliah_tersimpan_ke_database()
    {
        $prodiId = DB::table('spmb_master_program_studi')->insertGetId([
            'kode_prodi' => 'IF',
            'nama' => 'Teknik Informatika',
            'jenjang' => $this->masterKode('jenjang_prodi'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kurikulum = $this->postJson('/api/v1/siakad/akademik/kurikulum', [
            'program_studi_id' => $prodiId,
            'kode' => 'KUR-IF-2026',
            'nama' => 'Kurikulum Informatika 2026',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
        ]);
        $kurikulum->assertStatus(201);

        $tipe = $this->masterKode('tipe_mk', 2); // baris ketiga master (pilihan)

        $response = $this->postJson('/api/v1/siakad/akademik/matakuliah', [
            'kurikulum_id' => $kurikulum->json('data.id'),
            'kode_mk' => 'IF2101',
            'nama' => 'Pemrograman Web Lanjut',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'semester_anjuran' => 3,
            'tipe' => $tipe,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('siakad_mata_kuliah', [
            'kode_mk' => 'IF2101',
            'tipe' => $tipe,
            'total_sks' => 3,
        ]);

        $rejected = $this->postJson('/api/v1/siakad/akademik/matakuliah', [
            'kurikulum_id' => $kurikulum->json('data.id'),
            'kode_mk' => 'IF2102',
            'nama' => 'Jaringan Komputer',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'semester_anjuran' => 3,
            'tipe' => 'wajib-ngawur',
        ]);
        $rejected->assertStatus(422);
    }

    public function test_alur_ubah_mode_penilaian_tersimpan_ke_database()
    {
        $ta = $this->postJson('/api/v1/siakad/akademik/tahun-akademik', [
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
        ]);
        $ta->assertStatus(201);
        $taId = $ta->json('data.id');

        $mode = $this->masterKode('mode_penilaian');

        $response = $this->patchJson("/api/v1/siakad/akademik/tahun-akademik/{$taId}/mode-penilaian", [
            'mode_penilaian' => $mode,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('spmb_master_tahun_akademik', [
            'id' => $taId,
            'mode_penilaian' => $mode,
        ]);

        $rejected = $this->patchJson("/api/v1/siakad/akademik/tahun-akademik/{$taId}/mode-penilaian", [
            'mode_penilaian' => 'mode-ngawur',
        ]);
        $rejected->assertStatus(422);
    }

    public function test_alur_buka_kelas_menyimpan_hari_dari_master()
    {
        $prodiId = DB::table('spmb_master_program_studi')->insertGetId([
            'kode_prodi' => 'IF',
            'nama' => 'Teknik Informatika',
            'jenjang' => $this->masterKode('jenjang_prodi'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kurikulum = $this->postJson('/api/v1/siakad/akademik/kurikulum', [
            'program_studi_id' => $prodiId,
            'kode' => 'KUR-IF-2026',
            'nama' => 'Kurikulum Informatika 2026',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
        ]);

        $mk = $this->postJson('/api/v1/siakad/akademik/matakuliah', [
            'kurikulum_id' => $kurikulum->json('data.id'),
            'kode_mk' => 'IF2101',
            'nama' => 'Pemrograman Web Lanjut',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'semester_anjuran' => 3,
            'tipe' => $this->masterKode('tipe_mk'),
        ]);

        $ta = $this->postJson('/api/v1/siakad/akademik/tahun-akademik', [
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
        ]);

        $hari = $this->masterKode('hari_kuliah');

        $response = $this->postJson('/api/v1/siakad/perkuliahan/kelas', [
            'mata_kuliah_id' => $mk->json('data.id'),
            'tahun_akademik_id' => $ta->json('data.id'),
            'program_studi_id' => $prodiId,
            'kode_kelas' => 'IF2101-A',
            'nama_kelas' => 'Pemrograman Web Lanjut (Kelas A)',
            'kapasitas' => 40,
            'kuota_krs' => 40,
            'hari' => $hari,
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('siakad_kelas', [
            'kode_kelas' => 'IF2101-A',
            'hari' => $hari,
        ]);
    }
}
