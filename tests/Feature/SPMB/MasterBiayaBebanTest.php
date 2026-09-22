<?php

namespace Tests\Feature\SPMB;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use App\Services\Spmb\MasterBiayaService;

class MasterBiayaBebanTest extends TestCase
{
    use RefreshDatabase;

    private GelombangPenerimaan $gelombang;

    private MasterProgramStudi $prodi;

    private MasterKomponenBiaya $komponenAwal;

    private MasterKomponenBiaya $komponenDaftar;

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
        $this->komponenAwal = MasterKomponenBiaya::create([
            'kode' => 'REG-AWAL', 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran',
        ]);
        $this->komponenDaftar = MasterKomponenBiaya::create([
            'kode' => 'UKT', 'nama' => 'UKT Semester 1', 'kategori' => 'daftar_ulang',
        ]);
    }

    public function test_flag_beban_tersimpan_dan_split_komponen_benar(): void
    {
        $res = $this->postJson('/api/spmb/master/biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
            'items' => [
                ['komponen_biaya_id' => $this->komponenAwal->id, 'nominal' => 250000, 'dibebankan_saat_pendaftaran' => true],
                ['komponen_biaya_id' => $this->komponenDaftar->id, 'nominal' => 5000000, 'dibebankan_saat_pendaftaran' => false],
            ],
        ]);

        $res->assertStatus(201);

        $this->assertDatabaseHas('spmb_master_biaya_item', [
            'komponen_biaya_id' => $this->komponenAwal->id,
            'dibebankan_saat_pendaftaran' => true,
        ]);
        $this->assertDatabaseHas('spmb_master_biaya_item', [
            'komponen_biaya_id' => $this->komponenDaftar->id,
            'dibebankan_saat_pendaftaran' => false,
        ]);

        $service = app(MasterBiayaService::class);

        $awal = $service->getKomponenBeban($this->gelombang->id, $this->prodi->id, true);
        $daftar = $service->getKomponenBeban($this->gelombang->id, $this->prodi->id, false);

        $this->assertCount(1, $awal);
        $this->assertSame($this->komponenAwal->id, $awal->first()->komponen_biaya_id);
        $this->assertCount(1, $daftar);
        $this->assertSame($this->komponenDaftar->id, $daftar->first()->komponen_biaya_id);
    }

    public function test_endpoint_biaya_pendaftaran_mengembalikan_rincian_beban(): void
    {
        $this->postJson('/api/spmb/master/biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
            'items' => [
                ['komponen_biaya_id' => $this->komponenAwal->id, 'nominal' => 250000, 'dibebankan_saat_pendaftaran' => true],
                ['komponen_biaya_id' => $this->komponenDaftar->id, 'nominal' => 5000000, 'dibebankan_saat_pendaftaran' => false],
            ],
        ])->assertStatus(201);

        $res = $this->getJson('/api/spmb/biaya-pendaftaran?gelombang_id=' . $this->gelombang->id . '&program_studi_id=' . $this->prodi->id);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.beban_pendaftaran')
            ->assertJsonCount(1, 'data.beban_daftar_ulang')
            ->assertJsonPath('data.beban_pendaftaran.0.kode', 'REG-AWAL')
            ->assertJsonPath('data.beban_daftar_ulang.0.kode', 'UKT');
    }
}
