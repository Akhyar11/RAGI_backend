<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\ProfilLulusan;
use App\Models\Siakad\BahanKajian;

class SiakadObeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private MasterProgramStudi $prodi;
    private Kurikulum $kurikulum;
    private Cpl $cpl;
    private MataKuliah $mataKuliah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        
        $this->admin = User::factory()->create();
        
        $this->prodi = MasterProgramStudi::create([
            'kode_prodi' => 'INF',
            'nama' => 'Informatika',
            'jenjang' => 'S1'
        ]);

        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-2026',
            'nama' => 'Kurikulum OBE 2026',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);

        $this->cpl = Cpl::create([
            'program_studi_id' => $this->prodi->id,
            'kode_cpl' => 'CPL-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Menguasai konsep pemrograman berorientasi objek.',
            'is_active' => true,
        ]);

        $this->mataKuliah = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'INF-101',
            'nama' => 'Pemrograman Dasar',
            'sks_teori' => 3,
            'sks_praktik' => 1,
            'total_sks' => 4,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_manage_profil_lulusan()
    {
        Passport::actingAs($this->admin);

        // 1. Store Profil Lulusan
        $responseStore = $this->postJson('/api/v1/siakad/obe/profil-lulusan', [
            'program_studi_id' => $this->prodi->id,
            'kode_pl' => 'PL-01',
            'nama' => 'Software Engineer',
            'deskripsi' => 'Mampu merancang dan mengembangkan sistem perangkat lunak.',
            'urutan' => 1,
        ]);

        $responseStore->assertStatus(200)
                      ->assertJsonPath('status', 'success')
                      ->assertJsonPath('data.kode_pl', 'PL-01');

        $this->assertDatabaseHas('siakad_profil_lulusan', [
            'kode_pl' => 'PL-01',
            'nama' => 'Software Engineer',
        ]);

        $plId = $responseStore->json('data.id');

        // 2. Get Profil Lulusan
        $responseGet = $this->getJson("/api/v1/siakad/obe/profil-lulusan?program_studi_id={$this->prodi->id}");
        $responseGet->assertStatus(200)
                    ->assertJsonCount(1, 'data');

        // 3. Map PL to CPL
        $responseMap = $this->postJson('/api/v1/siakad/obe/profil-lulusan/cpl', [
            'profil_lulusan_id' => $plId,
            'cpl_ids' => [$this->cpl->id],
        ]);

        $responseMap->assertStatus(200)
                    ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('siakad_profil_lulusan_cpl', [
            'profil_lulusan_id' => $plId,
            'cpl_id' => $this->cpl->id,
        ]);

        // 4. Delete Profil Lulusan
        $responseDelete = $this->deleteJson("/api/v1/siakad/obe/profil-lulusan/{$plId}");
        $responseDelete->assertStatus(200);

        $this->assertSoftDeleted('siakad_profil_lulusan', [
            'id' => $plId,
        ]);
    }

    public function test_admin_can_manage_bahan_kajian()
    {
        Passport::actingAs($this->admin);

        // 1. Store Bahan Kajian
        $responseStore = $this->postJson('/api/v1/siakad/obe/bahan-kajian', [
            'program_studi_id' => $this->prodi->id,
            'kode_bk' => 'BK-01',
            'nama_bk' => 'Rekayasa Perangkat Lunak',
            'deskripsi' => 'Pengkajian siklus hidup perangkat lunak.',
        ]);

        $responseStore->assertStatus(200)
                      ->assertJsonPath('status', 'success')
                      ->assertJsonPath('data.kode_bk', 'BK-01');

        $this->assertDatabaseHas('siakad_bahan_kajian', [
            'kode_bk' => 'BK-01',
            'nama_bk' => 'Rekayasa Perangkat Lunak',
        ]);

        $bkId = $responseStore->json('data.id');

        // 2. Get Bahan Kajian
        $responseGet = $this->getJson("/api/v1/siakad/obe/bahan-kajian?program_studi_id={$this->prodi->id}");
        $responseGet->assertStatus(200)
                    ->assertJsonCount(1, 'data');

        // 3. Map MK to BK
        $responseMap = $this->postJson('/api/v1/siakad/obe/matakuliah/bahan-kajian', [
            'mata_kuliah_id' => $this->mataKuliah->id,
            'bahan_kajian_ids' => [$bkId],
        ]);

        $responseMap->assertStatus(200)
                    ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('siakad_mata_kuliah_bahan_kajian', [
            'mata_kuliah_id' => $this->mataKuliah->id,
            'bahan_kajian_id' => $bkId,
        ]);

        // 4. Delete Bahan Kajian
        $responseDelete = $this->deleteJson("/api/v1/siakad/obe/bahan-kajian/{$bkId}");
        $responseDelete->assertStatus(200);

        $this->assertSoftDeleted('siakad_bahan_kajian', [
            'id' => $bkId,
        ]);
    }
}
