<?php

namespace Tests\Feature\SPMB;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;

class MasterBiayaGelombangTest extends TestCase
{
    use RefreshDatabase;

    private GelombangPenerimaan $gelombang;

    private MasterProgramStudi $prodi;

    private MasterKomponenBiaya $komponen;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        // User pertama (id=1) dianggap superadmin oleh fallback.
        Passport::actingAs(User::factory()->create(['username' => 'super_root']));

        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $this->gelombang = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang 1',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-06-30',
        ]);
        $this->prodi = MasterProgramStudi::create([
            'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1',
        ]);
        $this->komponen = MasterKomponenBiaya::create(['kode' => 'UKT', 'nama' => 'UKT']);
    }

    public function test_schema_uses_gelombang_instead_of_ta_and_jalur(): void
    {
        $this->assertTrue(Schema::hasColumn('spmb_master_biaya', 'gelombang_id'));
        $this->assertFalse(Schema::hasColumn('spmb_master_biaya', 'tahun_akademik_id'));
        $this->assertFalse(Schema::hasColumn('spmb_master_biaya', 'jalur_masuk_id'));
    }

    public function test_store_master_biaya_by_gelombang(): void
    {
        $res = $this->postJson('/api/spmb/master/biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
            'items' => [
                ['komponen_biaya_id' => $this->komponen->id, 'nominal' => 5000000],
            ],
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('spmb_master_biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
        ]);

        // Pasangan gelombang+prodi yang sama tidak boleh ganda (idempoten).
        $this->postJson('/api/spmb/master/biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'items' => [
                ['komponen_biaya_id' => $this->komponen->id, 'nominal' => 6000000],
            ],
        ])->assertStatus(201);
        $this->assertEquals(1, MasterBiaya::where('gelombang_id', $this->gelombang->id)->count());
    }

    public function test_store_requires_gelombang_id(): void
    {
        $this->postJson('/api/spmb/master/biaya', [
            'program_studi_id' => $this->prodi->id,
            'items' => [
                ['komponen_biaya_id' => $this->komponen->id, 'nominal' => 1000],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('gelombang_id');
    }

    public function test_index_filters_by_gelombang_with_relation(): void
    {
        MasterBiaya::create([
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
        ]);

        $res = $this->getJson('/api/spmb/master/biaya?gelombang_id=' . $this->gelombang->id);
        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertNotEmpty($res->json('data'));
        $this->assertEquals('Gelombang 1', $res->json('data.0.gelombang.nama'));
    }
}
