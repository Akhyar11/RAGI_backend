<?php

namespace Tests\Feature\SPMB;

use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbPembayaranEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private GelombangPenerimaan $gelombang;

    private MasterProgramStudi $prodi;

    private MasterKomponenBiaya $komponen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        $this->setUpPassport();

        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $this->gelombang = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang 1',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-06-30',
            'biaya_pendaftaran' => 250000,
            'status' => 'aktif',
        ]);
        $this->prodi = MasterProgramStudi::create([
            'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true,
        ]);
        $this->komponen = MasterKomponenBiaya::create([
            'kode' => 'REG-AWAL', 'nama' => 'Biaya Pendaftaran & Formulir', 'kategori' => 'pendaftaran', 'is_active' => true,
        ]);
    }

    public function test_alur_pembayaran_spmb_berhasil_dan_tercatat(): void
    {
        // 1. Admin menyiapkan master biaya pendaftaran.
        $admin = User::factory()->create(['username' => 'spmb_admin']); // id 1 => superadmin fallback
        Passport::actingAs($admin);

        $this->postJson('/api/spmb/master/biaya', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
            'items' => [
                ['komponen_biaya_id' => $this->komponen->id, 'nominal' => 250000, 'dibebankan_saat_pendaftaran' => true],
            ],
        ])->assertStatus(201);

        // 2. Calon mahasiswa mengisi biodata -> tagihan + VA diterbitkan.
        $camaba = User::factory()->create(['username' => 'camaba_bayar']);
        Passport::actingAs($camaba);

        $this->postJson('/api/spmb/pendaftaran/biodata', [
            'gelombang_id' => $this->gelombang->id,
            'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'Camaba Bayar',
            'nik' => '3273010101010001',
            'no_hp' => '081234567890',
        ])->assertStatus(200);

        $pendaftaran = PendaftaranCalonMhs::where('user_id', $camaba->id)->firstOrFail();

        $tagihan = TagihanMahasiswa::where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->firstOrFail();

        $this->assertSame('spmb_pendaftaran', $tagihan->tipe_referensi);
        $this->assertSame('belum_bayar', $tagihan->status);
        $this->assertEquals(250000, (float) $tagihan->total_tagihan);

        $this->assertDatabaseHas('sikeu_virtual_account', ['tagihan_id' => $tagihan->id]);
        $va = VirtualAccount::where('tagihan_id', $tagihan->id)->firstOrFail();

        // 3. Pembayaran via webhook callback (environment testing -> token opsional).
        $this->postJson('/api/v1/sikeu/callback/spmb/'.$pendaftaran->id, [
            'order_id' => 'TRX-SPMB-E2E-001',
            'nominal' => 250000,
            'status' => 'settlement',
            'bank_kode' => $va->bank_kode,
            'channel' => 'VA_'.$va->bank_kode,
        ])->assertStatus(200)->assertJsonPath('is_spmb_unlocked', true);

        // 4. Verifikasi tercatat.
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'id' => $tagihan->id,
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'TRX-SPMB-E2E-001',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $pendaftaran->id,
            'status_pembayaran' => 'lunas',
            'status' => PendaftaranCalonMhs::STATUS_SUBMITTED,
        ]);

        // Jurnal keuangan tercatat (auto-journal).
        $this->assertDatabaseHas('sikeu_jurnal_umum', [
            'jenis_sumber' => 'pembayaran_mahasiswa',
            'referensi_id' => $tagihan->id,
        ]);

        // Idempotency: callback kedua dengan order_id sama tidak menambah pembayaran.
        $this->postJson('/api/v1/sikeu/callback/spmb/'.$pendaftaran->id, [
            'order_id' => 'TRX-SPMB-E2E-001',
            'nominal' => 250000,
            'status' => 'settlement',
        ])->assertStatus(200);

        $this->assertSame(1, Pembayaran::where('kode_transaksi', 'TRX-SPMB-E2E-001')->count());
    }
}
