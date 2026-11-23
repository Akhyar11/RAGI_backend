<?php

namespace Tests\Feature\SPMB;

use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use App\Services\Spmb\MasterBiayaService;
use App\Services\Sikeu\SpmbSikeuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbFeeSourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        Passport::actingAs(User::factory()->create(['username' => 'spmb_fee_policy_admin']));
    }

    public function test_gelombang_rejects_and_hides_wave_level_registration_fee(): void
    {
        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $payload = [
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang Uji',
            'tanggal_buka' => '01-01',
            'tanggal_tutup' => '12-31',
            'kuota_total' => 100,
            'status' => 'draft',
            'biaya_pendaftaran' => 250000,
        ];

        $this->postJson('/api/spmb/gelombang', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('biaya_pendaftaran');

        unset($payload['biaya_pendaftaran']);
        $created = $this->postJson('/api/spmb/gelombang', $payload)
            ->assertCreated()
            ->assertJsonMissingPath('data.biaya_pendaftaran');

        $waveId = $created->json('data.id');
        $updatePayload = $payload;
        $updatePayload['biaya_pendaftaran'] = 250000;
        $this->putJson("/api/spmb/gelombang/{$waveId}", $updatePayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('biaya_pendaftaran');

        DB::table('spmb_gelombang_penerimaan')
            ->where('id', $waveId)
            ->update(['biaya_pendaftaran' => 250000]);

        $this->getJson('/api/spmb/gelombang')
            ->assertOk()
            ->assertJsonMissingPath('data.0.biaya_pendaftaran');
    }

    public function test_missing_registration_component_does_not_fallback_to_legacy_wave_fee(): void
    {
        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $wave = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang Historis',
            'tanggal_buka' => '01-01',
            'tanggal_tutup' => '12-31',
        ]);
        DB::table('spmb_gelombang_penerimaan')
            ->where('id', $wave->id)
            ->update(['biaya_pendaftaran' => 250000]);

        $program = MasterProgramStudi::create([
            'kode_prodi' => 'TI01',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
        ]);

        try {
            app(MasterBiayaService::class)->buildDetailBebanPendaftaran($jalur->id, $program->id, $wave->id);
            $this->fail('Expected missing Master Biaya registration components to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('biaya', $exception->errors());
        }

        try {
            app(MasterBiayaService::class)->buildDetailBebanDaftarUlang($jalur->id, $program->id);
            $this->fail('Expected missing Master Biaya re-enrollment components to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('biaya', $exception->errors());
        }
    }

    public function test_legacy_sikeu_registration_tariff_endpoint_is_gone(): void
    {
        $this->getJson('/api/spmb/tarif')
            ->assertStatus(410)
            ->assertJsonPath('status', 'error');

        try {
            app(SpmbSikeuService::class)->getTarifPendaftaranSpmb(1, 1);
            $this->fail('Expected the legacy SIKEU tariff service to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('biaya', $exception->errors());
        }
    }
}