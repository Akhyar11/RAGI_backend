<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\User;
use App\Services\Simpeg\PayrollCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SimpegPayrollMatriksAndAbsensiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $this->adminUser = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'superadmin@campus.ac.id',
        ]);
        Passport::actingAs($this->adminUser);

        $this->pegawai = Pegawai::create([
            'nama_lengkap' => 'Budi Tester',
            'nip' => 'PEG-TEST-9988',
            'jenis_pegawai' => 'tendik',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'is_active' => true,
            'terdampak_absensi' => true,
        ]);
    }

    public function test_get_pegawai_matriks_returns_correct_columns_and_values()
    {
        $komp = MasterKomponenGaji::firstOrCreate([
            'kode' => 'TUNJ_TEST_KOMP',
        ], [
            'nama' => 'Tunjangan Test Komponen',
            'jenis' => 'pendapatan',
            'tipe_nilai' => 'tetap',
            'nilai_default' => 500000,
            'is_taxable' => false,
            'is_active' => true,
            'is_kolom_aktif' => true,
            'urutan' => 99,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/simpeg/payroll/pegawai-matriks?search=PEG-TEST-9988');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'pegawai',
                'active_columns',
            ],
            'meta'
        ]);

        $this->assertTrue(collect($response->json('data.active_columns'))->contains('id', $komp->id));
    }

    public function test_toggle_kolom_komponen_switches_visibility()
    {
        $komp = MasterKomponenGaji::firstOrCreate([
            'kode' => 'TOGGLE_TEST_KOMP',
        ], [
            'nama' => 'Toggle Komponen Test',
            'jenis' => 'pendapatan',
            'tipe_nilai' => 'tetap',
            'nilai_default' => 100000,
            'is_taxable' => false,
            'is_active' => true,
            'is_kolom_aktif' => true,
            'urutan' => 99,
        ]);

        $res = $this->actingAs($this->adminUser)
            ->patchJson("/api/simpeg/payroll/komponen/{$komp->id}/toggle-kolom");

        $res->assertStatus(200);
        $this->assertFalse($komp->fresh()->is_kolom_aktif);
    }

    public function test_update_terdampak_absensi_and_payroll_calculation()
    {
        $this->pegawai->update(['terdampak_absensi' => false]);

        $calcService = app(PayrollCalculationService::class);
        $komponens = MasterKomponenGaji::where('is_active', true)->get();

        $gaji = $calcService->calculateSinglePegawaiPayroll($this->pegawai, $komponens, '2026-10');

        $potonganKeterlambatan = $gaji->details->where('komponenGaji.kode', 'POT_KETERLAMBATAN')->first();
        if ($potonganKeterlambatan) {
            $this->assertEquals(0, (float) $potonganKeterlambatan->nominal);
            $this->assertStringContainsString('Bebas Presensi', (string) $potonganKeterlambatan->keterangan);
        }

        $res = $this->actingAs($this->adminUser)
            ->patchJson("/api/simpeg/payroll/pegawai/{$this->pegawai->id}/terdampak-absensi", [
                'terdampak_absensi' => true,
            ]);

        $res->assertStatus(200);
        $this->assertTrue($this->pegawai->fresh()->terdampak_absensi);
    }
}
