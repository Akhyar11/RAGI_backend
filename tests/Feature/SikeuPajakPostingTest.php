<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuAccess;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use Database\Seeders\Sikeu\SikeuAkuntansiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SikeuPajakPostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SikeuAkuntansiSeeder::class);
        $this->withoutMiddleware(CheckMenuAccess::class);
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function test_expense_uses_explicit_category_and_tax_accounts_and_posts_balanced_journal(): void
    {
        $cashAccount = AkunKeuangan::where('kode_akun', '101.01')->firstOrFail();
        $unitKas = UnitKas::create([
            'nama_kas' => 'Kas Pengujian',
            'akun_keuangan_id' => $cashAccount->id,
            'saldo_awal' => 50000,
            'saldo_saat_ini' => 50000,
            'status' => true,
        ]);

        $this->postJson('/api/v1/sikeu/pengeluaran', [
            'kategori' => 'operasional',
            'nominal' => 10000,
            'tanggal_transaksi' => '2026-11-09',
            'nama_vendor' => 'Vendor Tes',
            'jenis_pajak' => 'pph_23',
            'unit_kas_id' => $unitKas->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.jenis_pajak', 'pph_23');

        $pengeluaran = PengeluaranKampus::firstOrFail();
        $jurnal = JurnalUmum::where('referensi_id', $pengeluaran->id)->firstOrFail();
        $this->assertSame((float) $jurnal->total_debet, (float) $jurnal->total_kredit);
        $this->assertDatabaseHas('sikeu_detail_jurnal_umum', [
            'jurnal_id' => $jurnal->id,
            'akun_id' => AkunKeuangan::where('kode_akun', '502.01')->value('id'),
            'debet' => 10000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('sikeu_detail_jurnal_umum', [
            'jurnal_id' => $jurnal->id,
            'akun_id' => AkunKeuangan::where('kode_akun', '202.02')->value('id'),
            'debet' => 0,
            'kredit' => 200,
        ]);
        $this->assertDatabaseHas('sikeu_unit_kas', [
            'id' => $unitKas->id,
            'saldo_saat_ini' => 40200,
        ]);
    }

    public function test_tax_settlement_uses_selected_unit_mapping_and_tax_specific_liability_account(): void
    {
        $cashAccount = AkunKeuangan::where('kode_akun', '101.01')->firstOrFail();
        $liabilityAccount = AkunKeuangan::where('kode_akun', '202.02')->firstOrFail();
        $unitKas = UnitKas::create([
            'nama_kas' => 'Kas Pajak Tes',
            'akun_keuangan_id' => $cashAccount->id,
            'saldo_awal' => 5000,
            'saldo_saat_ini' => 5000,
            'status' => true,
        ]);
        $pengeluaran = PengeluaranKampus::create([
            'nomor_transaksi' => 'EXP-TAX-TEST-1',
            'kategori' => 'operasional',
            'akun_beban_id' => AkunKeuangan::where('kode_akun', '502.01')->value('id'),
            'akun_kas_id' => $cashAccount->id,
            'nominal' => 10000,
            'tanggal_transaksi' => '2026-11-09',
            'nama_vendor' => 'Vendor Tes',
            'jenis_pajak' => 'pph_23',
            'tarif_pajak_persen' => 2,
            'nominal_pajak' => 200,
            'net_dibayarkan' => 9800,
            'status_pembayaran' => 'lunas',
        ]);

        $this->postJson("/api/v1/sikeu/pajak/{$pengeluaran->id}/setor", [
            'ntpn' => 'NTPN-TEST-2026',
            'unit_kas_id' => $unitKas->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'disetor');

        $jurnal = JurnalUmum::where('referensi_id', $pengeluaran->id)
            ->where('nomor_jurnal', 'like', 'JRN-TAX-%')
            ->firstOrFail();
        $this->assertSame(200.0, (float) $jurnal->total_debet);
        $this->assertSame(200.0, (float) $jurnal->total_kredit);
        $this->assertDatabaseHas('sikeu_detail_jurnal_umum', [
            'jurnal_id' => $jurnal->id,
            'akun_id' => $liabilityAccount->id,
            'debet' => 200,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('sikeu_detail_jurnal_umum', [
            'jurnal_id' => $jurnal->id,
            'akun_id' => $cashAccount->id,
            'debet' => 0,
            'kredit' => 200,
        ]);
        $this->assertDatabaseHas('sikeu_pengeluaran_kampus', [
            'id' => $pengeluaran->id,
            'status_pembayaran' => 'disetor',
            'file_bukti_bayar' => 'NTPN-TEST-2026',
        ]);
        $this->assertDatabaseHas('sikeu_unit_kas', [
            'id' => $unitKas->id,
            'saldo_saat_ini' => 4800,
        ]);
    }

    public function test_settlement_fails_without_explicit_cash_coa_mapping_and_preserves_source_data(): void
    {
        $unitKas = UnitKas::create([
            'nama_kas' => 'Kas Tanpa Mapping',
            'saldo_awal' => 5000,
            'saldo_saat_ini' => 5000,
            'status' => true,
        ]);
        $pengeluaran = PengeluaranKampus::create([
            'nomor_transaksi' => 'EXP-TAX-TEST-2',
            'kategori' => 'operasional',
            'nominal' => 10000,
            'tanggal_transaksi' => '2026-11-09',
            'nama_vendor' => 'Vendor Tes',
            'jenis_pajak' => 'pph_21',
            'nominal_pajak' => 500,
            'net_dibayarkan' => 9500,
            'status_pembayaran' => 'lunas',
        ]);

        $this->postJson("/api/v1/sikeu/pajak/{$pengeluaran->id}/setor", [
            'ntpn' => 'NTPN-TEST-2027',
            'unit_kas_id' => $unitKas->id,
        ])->assertStatus(422);

        $this->assertDatabaseHas('sikeu_pengeluaran_kampus', [
            'id' => $pengeluaran->id,
            'status_pembayaran' => 'lunas',
        ]);
        $this->assertDatabaseHas('sikeu_unit_kas', [
            'id' => $unitKas->id,
            'saldo_saat_ini' => 5000,
        ]);
        $this->assertDatabaseMissing('sikeu_jurnal_umum', [
            'referensi_id' => $pengeluaran->id,
        ]);
    }

    public function test_expense_and_tax_settlement_require_an_explicit_cash_unit(): void
    {
        $this->postJson('/api/v1/sikeu/pengeluaran', [
            'kategori' => 'operasional',
            'nominal' => 10000,
            'tanggal_transaksi' => '2026-11-09',
            'nama_vendor' => 'Vendor Tes',
            'jenis_pajak' => 'tanpa_pajak',
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_kas_id']);

        $pengeluaran = PengeluaranKampus::create([
            'nomor_transaksi' => 'EXP-TAX-TEST-3',
            'kategori' => 'operasional',
            'nominal' => 10000,
            'tanggal_transaksi' => '2026-11-09',
            'nama_vendor' => 'Vendor Tes',
            'jenis_pajak' => 'pph_21',
            'nominal_pajak' => 500,
            'net_dibayarkan' => 9500,
            'status_pembayaran' => 'lunas',
        ]);

        $this->postJson("/api/v1/sikeu/pajak/{$pengeluaran->id}/setor", [
            'ntpn' => 'NTPN-TEST-2028',
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_kas_id']);

        $this->assertDatabaseHas('sikeu_pengeluaran_kampus', [
            'id' => $pengeluaran->id,
            'status_pembayaran' => 'lunas',
        ]);
    }
}
