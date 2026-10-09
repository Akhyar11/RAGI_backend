<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\RumpunMataKuliah;
use App\Models\Siakad\JenisCpl;
use App\Models\Siakad\ObeRubrik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class SiakadObeMasterTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $superAdminRole = \App\Models\Role::where('slug', 'superadmin')->first();
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($superAdminRole->id);
        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'TI99',
            'nama' => 'Teknik Informatika Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        Passport::actingAs($this->admin);
    }

    public function test_crud_rumpun_mata_kuliah(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/rumpun-mk', [
            'program_studi_id' => $this->prodi->id,
            'kode_rumpun' => 'RMP-SE',
            'nama_rumpun' => 'Software Engineering',
            'deskripsi' => 'Rumpun bidang rekayasa perangkat lunak',
            'is_active' => true,
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $id = $res->json('data.id');

        $this->getJson('/api/v1/siakad/obe/rumpun-mk?program_studi_id=' . $this->prodi->id)
            ->assertStatus(200)
            ->assertJsonFragment(['kode_rumpun' => 'RMP-SE']);

        $this->putJson('/api/v1/siakad/obe/rumpun-mk/' . $id, [
            'program_studi_id' => $this->prodi->id,
            'kode_rumpun' => 'RMP-SE',
            'nama_rumpun' => 'Software Engineering & AI',
            'is_active' => true,
        ])->assertStatus(200);

        $this->deleteJson('/api/v1/siakad/obe/rumpun-mk/' . $id)
            ->assertStatus(200);
    }

    public function test_crud_jenis_cpl(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/jenis-cpl', [
            'program_studi_id' => $this->prodi->id,
            'kode_jenis' => 'SIKAP',
            'nama_jenis' => 'Sikap & Tata Nilai',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $res->assertStatus(201);

        $id = $res->json('data.id');

        $this->getJson('/api/v1/siakad/obe/jenis-cpl')
            ->assertStatus(200)
            ->assertJsonFragment(['kode_jenis' => 'SIKAP']);

        $this->deleteJson('/api/v1/siakad/obe/jenis-cpl/' . $id)
            ->assertStatus(200);
    }

    public function test_crud_obe_rubrik(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/rubrik', [
            'program_studi_id' => $this->prodi->id,
            'kode_rubrik' => 'RBK-PROJ-01',
            'nama_rubrik' => 'Rubrik Penilaian Proyek',
            'tipe_rubrik' => 'analitik',
            'kriterias' => [
                [
                    'nama_kriteria' => 'Kualitas Kode & Arsitektur',
                    'bobot_persen' => 50,
                    'deskripsi_sangat_baik' => 'Sangat rapi dan modular',
                    'deskripsi_baik' => 'Rapi',
                    'deskripsi_cukup' => 'Cukup',
                    'deskripsi_kurang' => 'Tidak rapi',
                ],
                [
                    'nama_kriteria' => 'Presentasi & Dokumentasi',
                    'bobot_persen' => 50,
                ]
            ],
            'is_active' => true,
        ]);
        $res->assertStatus(201);

        $id = $res->json('data.id');

        $this->getJson('/api/v1/siakad/obe/rubrik/' . $id)
            ->assertStatus(200)
            ->assertJsonPath('data.kriterias.0.nama_kriteria', 'Kualitas Kode & Arsitektur');

        $this->deleteJson('/api/v1/siakad/obe/rubrik/' . $id)
            ->assertStatus(200);
    }

    public function test_rubrik_tidak_menerima_program_studi_dari_klien_saat_store(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/rubrik', [
            'program_studi_id' => $this->prodi->id,
            'kode_rubrik' => 'RBK-EXPLICIT-01',
            'nama_rubrik' => 'Rubrik Prodi Eksplisit',
            'tipe_rubrik' => 'analitik',
            'is_active' => true,
        ]);

        // Superadmin terhubung ke seluruh prodi aktif; karena body mengirim prodi
        // eksplisit yang berada di dalam rentang tersebut, nilai itu diterima.
        $res->assertStatus(201);
        $this->assertSame($this->prodi->id, (int) $res->json('data.program_studi_id'));
    }

    public function test_rubrik_tanpa_program_studi_diturunkan_dari_prodi_aktif_scoped(): void
    {
        $scoped = User::factory()->create(['is_active' => true]);
        $role = \App\Models\Role::create(['name' => 'Tim Kurikulum Rubrik', 'slug' => 'tim_kurikulum_rubrik', 'is_active' => true]);
        $role->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.master.manage')->first()->id);
        $scoped->roles()->attach($role->id);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $scoped->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE',
            'is_active' => true,
        ]);

        Passport::actingAs($scoped);

        $res = $this->postJson('/api/v1/siakad/obe/rubrik', [
            'kode_rubrik' => 'RBK-NOPRODI-01',
            'nama_rubrik' => 'Rubrik Tanpa Input Prodi',
            'tipe_rubrik' => 'analitik',
            'is_active' => true,
        ]);

        $res->assertStatus(201);
        $this->assertSame(
            $this->prodi->id,
            (int) $res->json('data.program_studi_id'),
            'Prodi rubrik harus diturunkan otomatis dari prodi aktif user.'
        );
    }

    public function test_rubrik_422_bila_user_tidak_punya_prodi_aktif(): void
    {
        $tanpaProdi = User::factory()->create(['is_active' => true]);
        $roleTanpaProdi = \App\Models\Role::create(['name' => 'Tanpa Prodi Rubrik', 'slug' => 'tanpa_prodi_rubrik', 'is_active' => true]);
        $roleTanpaProdi->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.master.manage')->first()->id);
        $tanpaProdi->roles()->attach($roleTanpaProdi->id);
        ProgramStudi::create([
            'kode_prodi' => 'TK98',
            'nama' => 'Teknik Kimia Tanpa Akses',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        Passport::actingAs($tanpaProdi);

        $this->postJson('/api/v1/siakad/obe/rubrik', [
            'kode_rubrik' => 'RBK-NOPRODI-02',
            'nama_rubrik' => 'Rubrik Tanpa Prodi Aktif',
            'tipe_rubrik' => 'analitik',
            'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_update_rubrik_tidak_memindahkan_program_studi(): void
    {
        $id = $this->postJson('/api/v1/siakad/obe/rubrik', [
            'program_studi_id' => $this->prodi->id,
            'kode_rubrik' => 'RBK-PIN-01',
            'nama_rubrik' => 'Rubrik Prodi Terkunci',
            'tipe_rubrik' => 'analitik',
            'is_active' => true,
        ])->json('data.id');

        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'TE97',
            'nama' => 'Teknik Elektro Tujuan',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $this->putJson('/api/v1/siakad/obe/rubrik/' . $id, [
            'program_studi_id' => $prodiLain->id,
            'kode_rubrik' => 'RBK-PIN-01',
            'nama_rubrik' => 'Rubrik Prodi Terkunci (Diubah)',
            'tipe_rubrik' => 'analitik',
            'is_active' => true,
        ])->assertStatus(200);

        $this->assertSame(
            $this->prodi->id,
            (int) ObeRubrik::findOrFail($id)->program_studi_id,
            'Prodi rubrik tidak boleh dipindahkan lewat update.'
        );
    }

    public function test_crud_cpl(): void
    {
        \Illuminate\Support\Facades\DB::table('spmb_master_referensi')->updateOrInsert(
            ['modul' => 'siakad', 'tipe' => 'kategori_cpl', 'kode' => 'pengetahuan'],
            ['nama' => 'Penguasaan Pengetahuan (P)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        $kurikulum = \App\Models\Siakad\Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-CPL-01',
            'nama' => 'Kurikulum CPL Test',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/siakad/obe/cpl', [
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_cpl' => 'CPL-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Menguasai konsep dasar keilmuan.',
            'is_active' => true,
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $id = $res->json('data.id');

        $this->getJson('/api/v1/siakad/obe/cpl?program_studi_id=' . $this->prodi->id . '&kategori=pengetahuan&is_active=true&sort_by=kode_cpl&sort_order=asc&page=1&per_page=10')
            ->assertStatus(200)
            ->assertJsonFragment(['kode_cpl' => 'CPL-01']);

        $this->putJson('/api/v1/siakad/obe/cpl/' . $id, [
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_cpl' => 'CPL-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Menguasai konsep dasar keilmuan (revisi).',
            'is_active' => true,
        ])->assertStatus(200);

        $this->deleteJson('/api/v1/siakad/obe/cpl/' . $id)
            ->assertStatus(200);
    }

    public function test_crud_profesi_karir(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/profesi-karir', [
            'program_studi_id' => $this->prodi->id,
            'nama' => 'Software Engineer',
            'sumber' => 'SKKNI',
            'is_active' => true,
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $id = $res->json('data.id');
        $this->assertDatabaseHas('siakad_profesi_karir', ['id' => $id, 'nama' => 'Software Engineer']);

        $this->getJson('/api/v1/siakad/obe/profesi-karir?search=Software&program_studi_id=' . $this->prodi->id . '&is_active=true')
            ->assertStatus(200)
            ->assertJsonFragment(['nama' => 'Software Engineer']);

        $this->getJson('/api/v1/siakad/obe/profesi-karir?is_active=false')
            ->assertStatus(200)
            ->assertJsonMissing(['nama' => 'Software Engineer']);

        $this->putJson('/api/v1/siakad/obe/profesi-karir/' . $id, [
            'program_studi_id' => $this->prodi->id,
            'nama' => 'Software Engineer',
            'sumber' => 'SKKNI & IEEE',
            'is_active' => false,
        ])->assertStatus(200);
        $this->assertDatabaseHas('siakad_profesi_karir', ['id' => $id, 'is_active' => false]);

        $this->getJson('/api/v1/siakad/obe/profesi-karir?is_active=true')
            ->assertStatus(200)
            ->assertJsonMissing(['nama' => 'Software Engineer']);

        $this->deleteJson('/api/v1/siakad/obe/profesi-karir/' . $id)
            ->assertStatus(200);
        $this->assertDatabaseMissing('siakad_profesi_karir', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_jenis_cpl_scoped_to_own_prodi(): void
    {
        $prodiB = ProgramStudi::create([
            'kode_prodi' => 'TRPL99',
            'nama' => 'TRPL Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        JenisCpl::create(['program_studi_id' => null, 'kode_jenis' => 'SIKAP', 'nama_jenis' => 'Sikap', 'is_active' => true]);
        JenisCpl::create(['program_studi_id' => $this->prodi->id, 'kode_jenis' => 'KU-A', 'nama_jenis' => 'Keterampilan Umum A', 'is_active' => true]);
        JenisCpl::create(['program_studi_id' => $prodiB->id, 'kode_jenis' => 'KU-B', 'nama_jenis' => 'Keterampilan Umum B', 'is_active' => true]);

        $scoped = User::factory()->create(['is_active' => true]);
        // Hak baca master (bukan admin): lolos gate 403 namun tetap ter-scope prodi.
        $role = \App\Models\Role::create(['name' => 'Tim Kurikulum', 'slug' => 'tim_kurikulum', 'is_active' => true]);
        $perm = \App\Models\Permission::where('slug', 'siakad.master.manage')->first();
        $role->permissions()->attach($perm->id);
        $scoped->roles()->attach($role->id);
        Passport::actingAs($scoped);

        // Tanpa mapping prodi: tidak melihat apa pun (baris global pun disembunyikan).
        $this->getJson('/api/v1/siakad/obe/jenis-cpl')
            ->assertStatus(200)
            ->assertJsonPath('data', []);

        // Mapping ke prodi A: hanya melihat milik prodi A.
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $scoped->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE',
            'is_active' => true,
        ]);
        $res = $this->getJson('/api/v1/siakad/obe/jenis-cpl')->assertStatus(200);
        $this->assertEquals(
            ['Keterampilan Umum A'],
            collect($res->json('data'))->pluck('nama_jenis')->all()
        );

        // Store tanpa prodi: teratribusi ke prodi sendiri, bukan global.
        $this->postJson('/api/v1/siakad/obe/jenis-cpl', [
            'kode_jenis' => 'KK-A',
            'nama_jenis' => 'Keterampilan Khusus A',
        ])->assertStatus(201);
        $this->assertDatabaseHas('siakad_jenis_cpl', [
            'kode_jenis' => 'KK-A',
            'program_studi_id' => $this->prodi->id,
        ]);
    }
}
