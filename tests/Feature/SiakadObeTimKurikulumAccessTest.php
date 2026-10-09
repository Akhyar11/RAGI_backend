<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\MataKuliah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

/**
 * Otorisasi OBE untuk Tim Kurikulum non-admin.
 *
 * Dosen/Kaprodi yang terdaftar di `siakad_admin_prodi`boleh mengelola OBE pada
 * prodi aktifnya. Otorisasi ini harus konsisten di seluruh matriks pemetaan:
 * halaman yang hanya mengecek `permission` global akan menutup akses yang
 * sebenarnya sah, sedangkan halaman yang terlalu longgar berisiko menimpa prodi lain.
 */
class SiakadObeTimKurikulumAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $prodi;
    protected $kurikulum;
    protected $cpl;
    protected $mataKuliah;
    protected $timKurikulum;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'TK94',
            'nama' => 'Program Studi Tim Kurikulum',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-TK-01',
            'nama' => 'Kurikulum Tim Kurikulum',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $this->cpl = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-TK-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'CPL milik prodi sendiri',
            'is_active' => true,
        ]);
        $this->mataKuliah = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'MK-TK-01',
            'nama' => 'Mata kuliah milik prodi sendiri',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);

        // Tim Kurikulum: punya permission `read` saja, tetapi terdaftar di prodi
        // lewat siakad_admin_prodi sehingga berhak mengelola OBE prodi tersebut.
        $this->timKurikulum = User::factory()->create(['is_active' => true]);
        $role = \App\Models\Role::create(['name' => 'Tim Kurikulum Akses', 'slug' => 'tim_kurikulum_akses', 'is_active' => true]);
        $role->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.kurikulum.read')->first()->id);
        $this->timKurikulum->roles()->attach($role->id);
        \App\Models\Siakad\Dosen::create([
            'user_id' => $this->timKurikulum->id,
            'nidn' => '900001',
            'nama_lengkap' => 'Tim Kurikulum Uji',
            'program_studi_id' => $this->prodi->id,
        ]);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $this->timKurikulum->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE / Tim Kurikulum',
            'is_active' => true,
        ]);

        Passport::actingAs($this->timKurikulum);
    }

    public function test_matriks_cpl_mk_dapat_diakses_tim_kurikulum(): void
    {
        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-mata-kuliah');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $this->assertCount(1, collect($res->json('data.cpls')));
        $this->assertCount(1, collect($res->json('data.mata_kuliahs')));
    }

    public function test_matriks_menyertakan_kolom_sks_dari_total_sks(): void
    {
        $res = $this->getJson('/api/v1/siakad/obe/matrix/cpl-mata-kuliah');
        $res->assertStatus(200);

        // `siakad_mata_kuliah` menyimpan SKS pada `total_sks`, bukan kolom `sks`.
        $this->assertSame(3, (int) $res->json('data.mata_kuliahs.0.total_sks'));
        $this->assertNull($res->json('data.mata_kuliahs.0.sks'));
    }

    public function test_tim_kurikulum_dapat_menambah_cpl_pada_prodi_sendiri(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/cpl', [
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-TK-02',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL baru oleh tim kurikulum',
            'is_active' => true,
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertSame($this->prodi->id, (int) $res->json('data.program_studi_id'));
    }

    public function test_tim_kurikulum_ditolak_untuk_prodi_lain(): void
    {
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'TK93',
            'nama' => 'Program Studi lain',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $kurikulumLain = Kurikulum::create([
            'program_studi_id' => $prodiLain->id,
            'kode' => 'KUR-TK-02',
            'nama' => 'Kurikulum prodi lain',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/siakad/obe/cpl', [
            'kurikulum_id' => $kurikulumLain->id,
            'kode_cpl' => 'CPL-TK-LAIN',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL lintas prodi',
            'is_active' => true,
        ])->assertStatus(403);
    }

    public function test_tim_kurikulum_dapat_mengubah_dan_menghapus_cpl_prodi_sendiri(): void
    {
        $create = $this->postJson('/api/v1/siakad/obe/cpl', [
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-TK-03',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL untuk uji ubah/hapus',
            'is_active' => true,
        ])->assertStatus(201);

        $id = $create->json('data.id');

        $this->putJson('/api/v1/siakad/obe/cpl/' . $id, [
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-TK-03',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL telah diperbarui',
            'is_active' => true,
        ])->assertStatus(200);

        // `siakad_cpl` memakai soft delete, jadi baris tetap ada namun ditandai terhapus.
        $this->deleteJson('/api/v1/siakad/obe/cpl/' . $id)->assertStatus(200);
        $this->assertSoftDeleted('siakad_cpl', ['id' => $id]);
    }

    public function test_tim_kurikulum_ditolak_menghapus_cpl_prodi_lain(): void
    {
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'TK92',
            'nama' => 'Program Studi lain untuk uji hapus',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $kurikulumLain = Kurikulum::create([
            'program_studi_id' => $prodiLain->id,
            'kode' => 'KUR-TK-03',
            'nama' => 'Kurikulum prodi lain hapus',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $cplLain = Cpl::create([
            'program_studi_id' => $prodiLain->id,
            'kurikulum_id' => $kurikulumLain->id,
            'kode_cpl' => 'CPL-TK-LAIN',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL milik prodi lain',
            'is_active' => true,
        ]);

        $this->deleteJson('/api/v1/siakad/obe/cpl/' . $cplLain->id)->assertStatus(403);
        $this->assertDatabaseHas('siakad_cpl', ['id' => $cplLain->id]);
    }

    /**
     * Matriks pemetaan menampilkan SKS dengan nama kolom yang benar-benar ada di
     * skema. Endpoint ini menyeleksi kolom secara eksplisit, jadi salah nama kolom
     * akan memunculkan 500 bagi pengguna walaupun test lain memakai select('*').
     */
    public function test_nama_kolom_sks_pada_skema_dihindari_pada_query_eksplisit(): void
    {
        $kolom = \Illuminate\Support\Facades\Schema::getColumnListing('siakad_mata_kuliah');

        $this->assertContains('total_sks', $kolom);
        $this->assertNotContains('sks', $kolom, 'Kolom `sks` tidak pernah ada pada siakad_mata_kuliah.');
    }

    public function test_laporan_cpl_bk_mk_dapat_diakses_tim_kurikulum(): void
    {
        $this->getJson('/api/v1/siakad/obe/matrix/cpl-bahan-kajuan-mata-kuliah')
            ->assertStatus(404);

        $this->getJson('/api/v1/siakad/obe/matrix/cpl-bahan-kajian-mata-kuliah')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}