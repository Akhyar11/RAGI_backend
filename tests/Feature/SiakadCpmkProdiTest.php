<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\CpmkProdi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

/**
 * Rumusan CPMK Program Studi (CPMK-PS).
 *
 * Menguji CRUD, filter, otorisasi per prodi untuk Tim Kurikulum non-admin,
 * serta validasi penolakan saat menautkan CPL milik prodi lain.
 */
class SiakadCpmkProdiTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $prodi;
    protected $kurikulum;
    protected $cpl;

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
            'kode_prodi' => 'CP99',
            'nama' => 'Program Studi CPMK Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-CP-01',
            'nama' => 'Kurikulum CPMK Test',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $this->cpl = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL-CP-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'CPL untuk uji CPMK',
            'is_active' => true,
        ]);
    }

    public function test_crud_cpmk_prodi(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/cpmk-prodi', [
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK-01',
            'deskripsi' => 'Mampu merancang arsitektur sistem berbasis cloud.',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode_cpmk', 'CPMK-01');

        $id = $res->json('data.id');

        $list = $this->getJson('/api/v1/siakad/obe/cpmk-prodi?kurikulum_id=' . $this->kurikulum->id);
        $list->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertCount(1, collect($list->json('data')));

        $this->putJson('/api/v1/siakad/obe/cpmk-prodi/' . $id, [
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK-01',
            'deskripsi' => 'Mampu merancang arsitektur sistem berbasis cloud terdistribusi.',
        ])->assertStatus(200);

        $this->assertSame(
            'Mampu merancang arsitektur sistem berbasis cloud terdistribusi.',
            CpmkProdi::findOrFail($id)->deskripsi
        );

        $this->deleteJson('/api/v1/siakad/obe/cpmk-prodi/' . $id)->assertStatus(200);
        $this->assertSoftDeleted('siakad_cpmk_prodi', ['id' => $id]);
    }

    public function test_kode_cpmk_unik_per_kurikulum(): void
    {
        CpmkProdi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK-UNIK',
            'deskripsi' => 'Deskripsi pertama',
        ]);

        // Simpan duplikat kode pada kurikulum yang sama harus ditolak 422.
        $this->postJson('/api/v1/siakad/obe/cpmk-prodi', [
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK-UNIK',
            'deskripsi' => 'Deskripsi kedua',
        ])->assertStatus(422)->assertJsonValidationErrors(['kode_cpmk']);
    }

    public function test_menolak_cpl_milik_prodi_lain(): void
    {
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'CP98',
            'nama' => 'Prodi Lain CPL Cross',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $kurLain = Kurikulum::create([
            'program_studi_id' => $prodiLain->id,
            'kode' => 'KUR-CP-02',
            'nama' => 'Kur prodi lain',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $cplLain = Cpl::create([
            'program_studi_id' => $prodiLain->id,
            'kurikulum_id' => $kurLain->id,
            'kode_cpl' => 'CPL-CP-LAIN',
            'kategori' => 'sikap',
            'deskripsi' => 'CPL milik prodi lain',
            'is_active' => true,
        ]);

        // Kurikulum prodi ini + CPL prodi lain harus ditolak validasi.
        $this->postJson('/api/v1/siakad/obe/cpmk-prodi', [
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $cplLain->id,
            'kode_cpmk' => 'CPMK-CROSS',
            'deskripsi' => 'Deskripsi',
        ])->assertStatus(422)->assertJsonValidationErrors(['cpl_id']);
    }

    public function test_tim_kurikulum_dapat_mengelola_cpmk_prodi_sendiri(): void
    {
        $tim = User::factory()->create(['is_active' => true]);
        $role = \App\Models\Role::create(['name' => 'Tim Kurikulum CPMK', 'slug' => 'tim_kurikulum_cpmk', 'is_active' => true]);
        $role->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.kurikulum.read')->first()->id);
        $tim->roles()->attach($role->id);
        \App\Models\Siakad\Dosen::create([
            'user_id' => $tim->id,
            'nidn' => '900002',
            'nama_lengkap' => 'Dosen Tim Kurikulum CPMK',
            'program_studi_id' => $this->prodi->id,
        ]);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $tim->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE / Tim Kurikulum',
            'is_active' => true,
        ]);

        Passport::actingAs($tim);

        $create = $this->postJson('/api/v1/siakad/obe/cpmk-prodi', [
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK-TIM',
            'deskripsi' => 'Rumusan oleh tim kurikulum',
        ]);
        $create->assertStatus(201);

        $id = $create->json('data.id');

        $this->getJson('/api/v1/siakad/obe/cpmk-prodi')->assertStatus(200);
        $this->deleteJson('/api/v1/siakad/obe/cpmk-prodi/' . $id)->assertStatus(200);
    }
}