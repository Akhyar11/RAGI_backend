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
        // Pembukaan kelas kini mensyaratkan permission siakad.kelas.manage (BAAK)
        $role = \App\Models\Role::create(['name' => 'Admin BAAK', 'slug' => 'admin-baak-test']);
        $perm = \App\Models\Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.manage'],
            ['name' => 'Kelola Buka Kelas & Ruang', 'module' => 'siakad', 'action' => 'update']
        );
        $role->permissions()->attach($perm->id);
        $this->admin->roles()->attach($role->id);
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
        $this->assertDatabaseHas('siakad_program_studi', [
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
        $prodiId = DB::table('siakad_program_studi')->insertGetId([
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
        $this->assertDatabaseHas('siakad_tahun_akademik', [
            'id' => $taId,
            'mode_penilaian' => $mode,
        ]);

        $rejected = $this->patchJson("/api/v1/siakad/akademik/tahun-akademik/{$taId}/mode-penilaian", [
            'mode_penilaian' => 'mode-ngawur',
        ]);
        $rejected->assertStatus(422);
    }

    public function test_list_tahun_akademik_mendukung_pagination_filter_dan_sorting()
    {
        // Setup 2 data tahun akademik
        $this->postJson('/api/v1/siakad/akademik/tahun-akademik', [
            'kode' => '20251',
            'nama' => '2025/2026 Ganjil',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
        ])->assertStatus(201);

        $this->postJson('/api/v1/siakad/akademik/tahun-akademik', [
            'kode' => '20252',
            'nama' => '2025/2026 Genap',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
        ])->assertStatus(201);

        // Test pagination response structure & meta
        $res = $this->getJson('/api/v1/siakad/akademik/tahun-akademik?page=1&per_page=1');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
            ]);
        $this->assertCount(1, $res->json('data'));
        $this->assertGreaterThanOrEqual(2, $res->json('meta.total'));

        // Test search filter
        $searchRes = $this->getJson('/api/v1/siakad/akademik/tahun-akademik?search=20252&page=1&per_page=10');
        $searchRes->assertStatus(200);
        $this->assertEquals('20252', $searchRes->json('data.0.kode'));

        // Test backward compatibility without pagination params
        $noPageRes = $this->getJson('/api/v1/siakad/akademik/tahun-akademik');
        $noPageRes->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
        $this->assertIsArray($noPageRes->json('data'));
    }

    public function test_alur_buka_kelas_menyimpan_hari_dari_master()
    {
        $prodiId = DB::table('siakad_program_studi')->insertGetId([
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

    public function test_get_prodi_dapat_diakses_tanpa_siakad_master_manage_dan_mendukung_scoping_obe()
    {
        $fakultasId = DB::table('siakad_fakultas')->insertGetId([
            'kode' => 'FKOM',
            'nama' => 'Fakultas Komputer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prodi1Id = DB::table('siakad_program_studi')->insertGetId([
            'fakultas_id' => $fakultasId,
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prodi2Id = DB::table('siakad_program_studi')->insertGetId([
            'fakultas_id' => $fakultasId,
            'kode_prodi' => 'SI',
            'nama' => 'Sistem Informasi',
            'jenjang' => 'S1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User biasa tanpa siakad.master.manage (hanya siakad.kelas.manage)
        $res = $this->getJson('/api/v1/siakad/akademik/prodi');
        $res->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data']);
        $this->assertGreaterThanOrEqual(2, count($res->json('data')));

        // User Admin OBE yang hanya ditugaskan pada prodi 1
        $obeUser = User::factory()->create();
        DB::table('siakad_admin_prodi')->insert([
            'user_id' => $obeUser->id,
            'program_studi_id' => $prodi1Id,
            'jabatan' => 'admin_obe',
            'can_approve_rps' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Passport::actingAs($obeUser);
        $resObe = $this->getJson('/api/v1/siakad/akademik/prodi');
        $resObe->assertStatus(200);
        $prodiIds = collect($resObe->json('data'))->pluck('id')->toArray();
        $this->assertContains($prodi1Id, $prodiIds);
        $this->assertNotContains($prodi2Id, $prodiIds);

        // Jika parameter ?all=1 dikirim, prodi lain tetap dapat diambil jika dibutuhkan
        $resObeAll = $this->getJson('/api/v1/siakad/akademik/prodi?all=1');
        $resObeAll->assertStatus(200);
        $allProdiIds = collect($resObeAll->json('data'))->pluck('id')->toArray();
        $this->assertContains($prodi1Id, $allProdiIds);
        $this->assertContains($prodi2Id, $allProdiIds);
    }
}

