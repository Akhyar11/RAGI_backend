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

class SiakadBahanKajianTest extends TestCase
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
            'kode_prodi' => 'BK99',
            'nama' => 'Program Studi BK Test',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        Passport::actingAs($this->admin);
    }

    public function test_crud_bahan_kajian(): void
    {
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-BK-01',
            'nama' => 'Kurikulum BK Test',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $dosen = \App\Models\Siakad\Dosen::create([
            'nama_lengkap' => 'Dr. Koordinator BK',
            'nik' => '3273010102030011',
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/siakad/obe/bahan-kajian', [
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_bk' => 'BK-01',
            'nama_bk' => 'Analisis dan Perancangan Sistem',
            'koordinator_id' => $dosen->id,
        ]);
        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $id = $res->json('data.id');

        $this->assertDatabaseHas('siakad_bahan_kajian', [
            'id' => $id,
            'kode_bk' => 'BK-01',
            'koordinator_id' => $dosen->id,
        ]);

        // Program studi diturunkan dari kurikulum bila tidak dikirim.
        $res2 = $this->postJson('/api/v1/siakad/obe/bahan-kajian', [
            'kurikulum_id' => $kurikulum->id,
            'kode_bk' => 'BK-02',
            'nama_bk' => 'Basis Data',
        ]);
        $res2->assertStatus(201);
        $this->assertDatabaseHas('siakad_bahan_kajian', [
            'kode_bk' => 'BK-02',
            'program_studi_id' => $this->prodi->id,
        ]);

        // Kode duplikat pada prodi yang sama ditolak.
        $this->postJson('/api/v1/siakad/obe/bahan-kajian', [
            'program_studi_id' => $this->prodi->id,
            'kode_bk' => 'BK-01',
            'nama_bk' => 'Duplikat',
        ])->assertStatus(422);

        // List: filter + sort + pagination.
        $this->getJson('/api/v1/siakad/obe/bahan-kajian?search=Basis&kurikulum_id=' . $kurikulum->id . '&sort_by=kode_bk&sort_order=desc&page=1&per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['kode_bk' => 'BK-02']);

        $this->putJson('/api/v1/siakad/obe/bahan-kajian/' . $id, [
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_bk' => 'BK-01',
            'nama_bk' => 'Analisis dan Perancangan Sistem (Revisi)',
            'koordinator_id' => $dosen->id,
        ])->assertStatus(200);
        $this->assertDatabaseHas('siakad_bahan_kajian', [
            'id' => $id,
            'nama_bk' => 'Analisis dan Perancangan Sistem (Revisi)',
        ]);

        $this->deleteJson('/api/v1/siakad/obe/bahan-kajian/' . $id)->assertStatus(200);
        $this->assertSoftDeleted('siakad_bahan_kajian', ['id' => $id]);
    }

    public function test_matrix_cpl_bahan_kajian(): void
    {
        $cpl = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kode_cpl' => 'CPL-BK-1',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Capaian untuk pemetaan BK.',
            'is_active' => true,
        ]);
        $bk1 = BahanKajian::create([
            'program_studi_id' => $this->prodi->id,
            'kode_bk' => 'BK-A',
            'nama_bk' => 'Bahan A',
        ]);
        $bk2 = BahanKajian::create([
            'program_studi_id' => $this->prodi->id,
            'kode_bk' => 'BK-B',
            'nama_bk' => 'Bahan B',
        ]);

        $res = $this->getJson('/api/v1/siakad/obe/bahan-kajian/matrix/cpl?program_studi_id=' . $this->prodi->id);
        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertCount(1, $res->json('data.cpls'));
        $this->assertCount(2, $res->json('data.bahan_kajians'));
        $this->assertCount(0, $res->json('data.pairs'));

        $this->postJson('/api/v1/siakad/obe/cpl/bahan-kajian', [
            'cpl_id' => $cpl->id,
            'bahan_kajian_ids' => [$bk1->id, $bk2->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('siakad_cpl_bahan_kajian', [
            'cpl_id' => $cpl->id,
            'bahan_kajian_id' => $bk1->id,
        ]);

        $res = $this->getJson('/api/v1/siakad/obe/bahan-kajian/matrix/cpl?program_studi_id=' . $this->prodi->id);
        $this->assertCount(2, $res->json('data.pairs'));

        // Uncheck satu: hanya satu pasangan tersisa.
        $this->postJson('/api/v1/siakad/obe/cpl/bahan-kajian', [
            'cpl_id' => $cpl->id,
            'bahan_kajian_ids' => [$bk2->id],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('siakad_cpl_bahan_kajian', [
            'cpl_id' => $cpl->id,
            'bahan_kajian_id' => $bk1->id,
        ]);
    }

    public function test_matrix_bahan_kajian_mata_kuliah(): void
    {
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-BK-MK',
            'nama' => 'Kurikulum BK-MK',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);
        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'BK-101',
            'nama' => 'Mata Kuliah Pemetaan BK',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'total_sks' => 2,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);
        $bk = BahanKajian::create([
            'program_studi_id' => $this->prodi->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_bk' => 'BK-MK-1',
            'nama_bk' => 'Bahan untuk MK',
        ]);

        $res = $this->getJson('/api/v1/siakad/obe/bahan-kajian/matrix/mata-kuliah?program_studi_id=' . $this->prodi->id);
        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertCount(1, $res->json('data.mata_kuliahs'));
        $this->assertCount(1, $res->json('data.bahan_kajians'));

        $this->postJson('/api/v1/siakad/obe/bahan-kajian/mata-kuliah', [
            'bahan_kajian_id' => $bk->id,
            'mata_kuliah_ids' => [$mk->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('siakad_mata_kuliah_bahan_kajian', [
            'bahan_kajian_id' => $bk->id,
            'mata_kuliah_id' => $mk->id,
        ]);

        // Kosongkan untuk melepas pemetaan.
        $this->postJson('/api/v1/siakad/obe/bahan-kajian/mata-kuliah', [
            'bahan_kajian_id' => $bk->id,
            'mata_kuliah_ids' => [],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('siakad_mata_kuliah_bahan_kajian', [
            'bahan_kajian_id' => $bk->id,
            'mata_kuliah_id' => $mk->id,
        ]);
    }
}
