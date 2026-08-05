<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sikeu\JenisBiaya;
use App\Models\Sikeu\TarifSpmb;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\Sikeu\SpmbSikeuService;
use App\Events\Sikeu\PembayaranSpmbLunas;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpmbSikeuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\IAM\RoleSeeder::class);
        $this->seed(\Database\Seeders\IAM\PermissionSeeder::class);
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        $this->user = User::factory()->create();

        // Seed default JenisBiaya for SPMB Adm
        JenisBiaya::create([
            'kode' => 'SPMB_ADM',
            'nama' => 'Biaya Pendaftaran SPMB',
            'tipe' => 'spmb_adm',
            'nominal_standar' => 200000.00,
            'is_active' => true,
        ]);
    }

    public function test_service_get_tarif_spmb_returns_dynamic_rate_or_fallback()
    {
        $service = new SpmbSikeuService();

        // Fallback test: no specific TarifSpmb exists yet
        $fallbackRate = $service->getTarifPendaftaranSpmb('REGULER', 'GELOMBANG_1');
        $this->assertEquals(200000.00, $fallbackRate);

        // Specific rate creation
        TarifSpmb::create([
            'jalur_id' => 'REGULER',
            'gelombang_id' => 'GELOMBANG_1',
            'nominal' => 350000.00,
            'is_active' => true,
        ]);

        $dynamicRate = $service->getTarifPendaftaranSpmb('REGULER', 'GELOMBANG_1');
        $this->assertEquals(350000.00, $dynamicRate);
    }

    public function test_api_get_tarif_spmb()
    {
        TarifSpmb::create([
            'jalur_id' => 'EXECUTIVE',
            'gelombang_id' => 'GELOMBANG_2',
            'nominal' => 500000.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/sikeu/spmb/tarif?jalur_id=EXECUTIVE&gelombang_id=GELOMBANG_2');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nominal', 500000);
    }

    public function test_master_tarif_spmb_crud_endpoints()
    {
        // Store
        $storeResp = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/master/tarif-spmb', [
            'jalur_id' => 'PRESTASI',
            'gelombang_id' => 'GELOMBANG_1',
            'nominal' => 150000.00,
            'is_active' => true,
        ]);

        $storeResp->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $tarifId = $storeResp->json('data.id');

        $this->assertDatabaseHas('tarif_spmb', [
            'id' => $tarifId,
            'jalur_id' => 'PRESTASI',
            'nominal' => 150000.00,
        ]);

        // Update
        $updateResp = $this->actingAs($this->user, 'api')->putJson("/api/v1/sikeu/master/tarif-spmb/{$tarifId}", [
            'nominal' => 175000.00,
        ]);

        $updateResp->assertStatus(200);

        $this->assertDatabaseHas('tarif_spmb', [
            'id' => $tarifId,
            'nominal' => 175000.00,
        ]);

        // List
        $listResp = $this->actingAs($this->user, 'api')->getJson('/api/v1/sikeu/master/tarif-spmb');
        $listResp->assertStatus(200);

        // Delete
        $delResp = $this->actingAs($this->user, 'api')->deleteJson("/api/v1/sikeu/master/tarif-spmb/{$tarifId}");
        $delResp->assertStatus(200);

        $this->assertDatabaseMissing('tarif_spmb', ['id' => $tarifId]);
    }

    public function test_external_bill_supports_calon_mahasiswa_id()
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/tagihan/external', [
            'calon_mahasiswa_id' => 888,
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Tagihan Pendaftaran SPMB Calon Mhs 888',
            'details' => [
                [
                    'jenis_biaya_kode' => 'SPMB_ADM',
                    'nominal' => 250000,
                    'keterangan' => 'Biaya Formulir'
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('tagihan_mahasiswa', [
            'calon_mahasiswa_id' => 888,
            'tipe_referensi' => 'calon_mahasiswa',
            'source_system' => 'SPMB',
            'status' => 'belum_bayar',
        ]);
    }

    public function test_spmb_payment_callback_updates_status_and_dispatches_event()
    {
        Event::fake([PembayaranSpmbLunas::class]);

        $calonMhsId = 777;

        $tagihan = TagihanMahasiswa::create([
            'calon_mahasiswa_id' => $calonMhsId,
            'tipe_referensi' => 'calon_mahasiswa',
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-SPMB-20260805-TEST',
            'total_tagihan' => 250000,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'source_system' => 'SPMB',
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/sikeu/callback/spmb/{$calonMhsId}", [
            'order_id' => 'TRX-SPMB-777-01',
            'nominal' => 250000,
            'status' => 'settlement',
            'bank_kode' => 'BNI',
            'channel' => 'VA_BNI',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('spmb_unlock', true);

        $this->assertDatabaseHas('tagihan_mahasiswa', [
            'id' => $tagihan->id,
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('pembayaran', [
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'TRX-SPMB-777-01',
            'jumlah_bayar' => 250000,
            'status' => 'success',
        ]);

        Event::assertDispatched(PembayaranSpmbLunas::class, function ($event) use ($calonMhsId) {
            return $event->calonMahasiswaId == $calonMhsId;
        });
    }
}
