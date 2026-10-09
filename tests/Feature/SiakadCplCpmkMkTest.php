<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\CpmkProdi;
use App\Models\Siakad\MataKuliah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

/**
 * Pemetaan CPL-CPMK-MK (Distribusi Rumusan CPMK Prodi ke Mata Kuliah yang mengampunya).
 */
class SiakadCplCpmkMkTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $prodi;
    protected $kurikulum;
    protected $cpl;
    protected $cpmkProdi;
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
            'kode_prodi' => 'CC99',
            'nama' => 'Program Studi CPL-CPMK-MK Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-CC-01',
            'nama' => 'Kurikulum CC Test',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $this->cpl = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_cpl' => 'CPL01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Mampu mengembangkan jiwa wirausaha',
            'is_active' => true,
        ]);
        $this->cpmkProdi = CpmkProdi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'cpl_id' => $this->cpl->id,
            'kode_cpmk' => 'CPMK011',
            'deskripsi' => 'Mampu mengembangkan ide wirausaha mandiri',
        ]);
        $this->mk1 = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'PM-IK-1-3-004',
            'nama' => 'WORKSHOP CREATIVE THINKING',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'is_active' => true,
        ]);
        $this->mk2 = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'PM-IK-4-2-005',
            'nama' => 'KEWIRAUSAHAAN PRODUKSI MEDIA',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'total_sks' => 2,
            'semester_anjuran' => 4,
            'is_active' => true,
        ]);
    }

    public function test_get_pemetaan_cpl_cpmk_mk(): void
    {
        $res = $this->getJson('/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk?kurikulum_id=' . $this->kurikulum->id);
        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertCount(1, collect($res->json('data')));
        $this->assertSame('CPMK011', $res->json('data.0.kode_cpmk'));
        $this->assertSame('CPL01', $res->json('data.0.cpl.kode_cpl'));
    }

    public function test_sync_cpmk_prodi_mata_kuliah(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk/sync', [
            'cpmk_prodi_id' => $this->cpmkProdi->id,
            'mata_kuliah_ids' => [$this->mk1->id, $this->mk2->id],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('siakad_cpmk_prodi_mata_kuliah', [
            'cpmk_prodi_id' => $this->cpmkProdi->id,
            'mata_kuliah_id' => $this->mk1->id,
        ]);
        $this->assertDatabaseHas('siakad_cpmk_prodi_mata_kuliah', [
            'cpmk_prodi_id' => $this->cpmkProdi->id,
            'mata_kuliah_id' => $this->mk2->id,
        ]);

        $getRes = $this->getJson('/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk');
        $this->assertCount(2, $getRes->json('data.0.mata_kuliahs'));
    }

    public function test_tim_kurikulum_dapat_sync_mk_prodi_sendiri(): void
    {
        $tim = User::factory()->create(['is_active' => true]);
        $role = \App\Models\Role::create(['name' => 'Tim Kurikulum Pemetaan', 'slug' => 'tim_kurikulum_pemetaan', 'is_active' => true]);
        $role->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.kurikulum.read')->first()->id);
        $tim->roles()->attach($role->id);
        \App\Models\Siakad\Dosen::create([
            'user_id' => $tim->id,
            'nidn' => '900003',
            'nama_lengkap' => 'Dosen Tim Kurikulum Pemetaan',
            'program_studi_id' => $this->prodi->id,
        ]);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $tim->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE / Tim Kurikulum',
            'is_active' => true,
        ]);

        Passport::actingAs($tim);

        $res = $this->postJson('/api/v1/siakad/obe/pemetaan-cpl-cpmk-mk/sync', [
            'cpmk_prodi_id' => $this->cpmkProdi->id,
            'mata_kuliah_ids' => [$this->mk1->id],
        ]);
        $res->assertStatus(200);
    }
}
