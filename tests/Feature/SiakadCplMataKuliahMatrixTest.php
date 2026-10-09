<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\BahanKajian;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\MataKuliah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Aturan kelayakan matriks CPL-MK: sebuah sel hanya boleh dicentang bila sudah
 * ada jalur CPL -> BK -> MK. Laporan CPL-BK-MK bersifat read-only dan disusun
 * dari komposisi pemetaan yang sama.
 */
class SiakadCplMataKuliahMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $prodi;
    protected $kurikulum;
    protected $cpl1;
    protected $cpl2;
    protected $bk1;
    protected $mk1;
    protected $mk2;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $superAdminRole = \App\Models\Role::where('slug', 'superadmin')->first();
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($superAdminRole->id);
        Passport::actingAs($this->admin);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'MK97',
            'nama' => 'Program Studi CPL-MK Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-MK-01',
            'nama' => 'Kurikulum CPL-MK Test',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $this->cpl1 = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-MK-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'CPL satu',
            'is_active' => true,
        ]);
        $this->cpl2 = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-MK-02',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL dua',
            'is_active' => true,
        ]);

        $this->bk1 = BahanKajian::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_bk' => 'BK-MK-01',
            'nama_bk' => 'Bahan kajian satu',
        ]);

        $this->mk1 = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'MK-MK-01',
            'nama' => 'Mata kuliah satu',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);
        $this->mk2 = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'MK-MK-02',
            'nama' => 'Mata kuliah dua',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'total_sks' => 2,
            'semester_anjuran' => 2,
            'is_active' => true,
        ]);

        // Jalur: CPL-MK-01 -> BK-MK-01 -> MK-MK-01
        DB::table('siakad_cpl_bahan_kajian')->insert([
            'cpl_id' => $this->cpl1->id,
            'bahan_kajian_id' => $this->bk1->id,
        ]);
        DB::table('siakad_mata_kuliah_bahan_kajian')->insert([
            'mata_kuliah_id' => $this->mk1->id,
            'bahan_kajian_id' => $this->bk1->id,
        ]);
    }

    public function test_eligible_hanya_menyertakan_sel_yang_punya_jalur(): void
    {
        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-mata-kuliah');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $eligible = collect($res->json('data.eligible'))
            ->map(fn($e) => $e['cpl_id'] . '-' . $e['mata_kuliah_id']);

        // Hanya CPL-01 x MK-01 yang punya jalur CPL -> BK -> MK.
        $this->assertTrue($eligible->contains($this->cpl1->id . '-' . $this->mk1->id));
        $this->assertFalse($eligible->contains($this->cpl1->id . '-' . $this->mk2->id), 'MK tanpa BK tidak boleh eligible');
        $this->assertFalse($eligible->contains($this->cpl2->id . '-' . $this->mk1->id), 'CPL tanpa BK tidak boleh eligible');
        $this->assertCount(1, $eligible);
    }

    public function test_toggle_menolak_sel_tanpa_jalur(): void
    {
        // CPL-01 -> MK-02 tidak punya jalur karena MK-02 belum dipetakan ke BK.
        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk2->id,
            'is_checked' => true,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('siakad_mata_kuliah_cpl', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk2->id,
        ]);
    }

    public function test_toggle_menolak_sel_lintas_prodi(): void
    {
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'MK96',
            'nama' => 'Prodi Lain CPL-MK',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $kurikulumLain = Kurikulum::create([
            'program_studi_id' => $prodiLain->id,
            'kode' => 'KUR-MK-02',
            'nama' => 'Kurikulum prodi lain',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $mkLain = MataKuliah::create([
            'kurikulum_id' => $kurikulumLain->id,
            'kode_mk' => 'MK-LAIN-01',
            'nama' => 'MK prodi lain',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $mkLain->id,
            'is_checked' => true,
        ])->assertStatus(422);
    }

    public function test_toggle_menyimpan_dan_melepas_sel_yang_eligible(): void
    {
        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
            'is_checked' => true,
        ])->assertStatus(200)->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('siakad_mata_kuliah_cpl', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
        ]);

        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
            'is_checked' => false,
        ])->assertStatus(200);

        $this->assertDatabaseMissing('siakad_mata_kuliah_cpl', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
        ]);
    }

    public function test_pemetaan_yatim_ditandai_bukan_dihapus(): void
    {
        // Tick dulu sel yang eligible.
        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
            'is_checked' => true,
        ])->assertStatus(200);

        // Jalur BK -> MK diputus dari sisi Pemetaan BK-MK.
        DB::table('siakad_mata_kuliah_bahan_kajian')
            ->where('mata_kuliah_id', $this->mk1->id)
            ->delete();

        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-mata-kuliah');
        $res->assertStatus(200);

        // Tick tetap ada agar riwayat akreditasi tidak hilang...
        $this->assertCount(1, collect($res->json('data.pairs')));
        // ...tapi ditandai yatim supaya Kaprodi meninjau.
        $this->assertContains(
            $this->cpl1->id . '-' . $this->mk1->id,
            collect($res->json('data.yatim'))->all()
        );
        // Jalur sudah tidak ada, sehingga tidak lagi eligible.
        $this->assertCount(0, collect($res->json('data.eligible')));
    }

    public function test_lepas_centang_yatim_tetap_diizinkan(): void
    {
        DB::table('siakad_mata_kuliah_cpl')->insert([
            'mata_kuliah_id' => $this->mk1->id,
            'cpl_id' => $this->cpl1->id,
            'created_at' => now(),
        ]);

        // Lepas centang harus tetap bisa dilakukan walau sudah yatim.
        $this->postJson('/api/v1/siakad/obe/cpl/mata-kuliah', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
            'is_checked' => false,
        ])->assertStatus(200);

        $this->assertDatabaseMissing('siakad_mata_kuliah_cpl', [
            'cpl_id' => $this->cpl1->id,
            'mata_kuliah_id' => $this->mk1->id,
        ]);
    }

    public function test_laporan_cpl_bk_mk_read_only_menyusun_daftar_mk(): void
    {
        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-bahan-kajian-mata-kuliah');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $isi = $res->json('data.isi');
        $daftar = $isi[$this->cpl1->id][$this->bk1->id];

        $this->assertCount(1, $daftar);
        $this->assertSame('MK-MK-01', $daftar[0]['kode_mk']);
        $this->assertSame('Mata kuliah satu', $daftar[0]['nama_mk']);

        // Laporan bersifat read-only: tidak ada endpoint tulis untuk matriks ini.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('obe.cpl-bk-mk.sync'),
            'Laporan CPL-BK-MK tidak boleh memiliki endpoint penyimpanan.'
        );
    }

    public function test_laporan_tidak_mencampur_prodi(): void
    {
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'MK95',
            'nama' => 'Prodi Lain Laporan',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $bkLain = BahanKajian::create([
            'program_studi_id' => $prodiLain->id,
            'kode_bk' => 'BK-LAIN-01',
            'nama_bk' => 'BK prodi lain',
        ]);

        // Sengaja mencobasecutive cross-prodi edge pada BK milik prodi lain.
        DB::table('siakad_cpl_bahan_kajian')->insert([
            'cpl_id' => $this->cpl1->id,
            'bahan_kajian_id' => $bkLain->id,
        ]);

        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-bahan-kajian-mata-kuliah');
        $res->assertStatus(200);

        $isi = $res->json('data.isi');
        $this->assertArrayNotHasKey($bkLain->id, $isi[$this->cpl1->id] ?? []);
    }
}