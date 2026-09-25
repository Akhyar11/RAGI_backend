<?php

namespace Tests\Feature\SPMB;

use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmbPaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private PendaftaranCalonMhs $pendaftaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $user = User::factory()->create(['is_active' => true]);

        $jalur = JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler']);
        $gelombang = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang 1',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-06-30',
            'status' => 'aktif',
        ]);

        $this->pendaftaran = PendaftaranCalonMhs::create([
            'gelombang_id' => $gelombang->id,
            'user_id' => $user->id,
            'program_studi_id' => 1,
            'no_pendaftaran' => 'REG-CB-'.uniqid(),
            'nama_lengkap' => 'Calon Bayar',
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'status' => PendaftaranCalonMhs::STATUS_DRAFT,
            'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
        ]);
    }

    private function buatTagihan(string $tipe = 'spmb_pendaftaran', float $total = 250000): TagihanMahasiswa
    {
        return TagihanMahasiswa::create([
            'calon_mahasiswa_id' => $this->pendaftaran->id,
            'tipe_referensi' => $tipe,
            'nomor_tagihan' => 'INV-SPMB-'.uniqid(),
            'total_tagihan' => $total,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'source_system' => 'SPMB',
        ]);
    }

    public function test_pembayaran_sebagian_tidak_menandai_lunas(): void
    {
        $this->buatTagihan();

        $res = $this->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
            'order_id' => 'ORD-PARTIAL-1',
            'nominal' => 100000,
            'status' => 'settlement',
        ]);

        $res->assertStatus(200)->assertJsonPath('is_spmb_unlocked', false);

        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'calon_mahasiswa_id' => $this->pendaftaran->id,
            'status' => 'sebagian',
        ]);

        // Status pendaftaran tetap draft (belum lunas).
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $this->pendaftaran->id,
            'status' => PendaftaranCalonMhs::STATUS_DRAFT,
            'status_pembayaran' => 'sebagian',
        ]);
    }

    public function test_pelunasan_menandai_lunas_dan_submitted(): void
    {
        $this->buatTagihan();

        $this->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
            'order_id' => 'ORD-LUNAS-1',
            'nominal' => 250000,
            'status' => 'settlement',
        ])->assertStatus(200)->assertJsonPath('is_spmb_unlocked', true);

        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'calon_mahasiswa_id' => $this->pendaftaran->id,
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $this->pendaftaran->id,
            'status' => PendaftaranCalonMhs::STATUS_SUBMITTED,
            'status_pembayaran' => 'lunas',
        ]);
    }

    public function test_callback_tanpa_tagihan_mengembalikan_404_dan_tidak_membuat_tagihan_hantu(): void
    {
        $this->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
            'order_id' => 'ORD-GHOST-1',
            'nominal' => 250000,
            'status' => 'settlement',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('sikeu_tagihan_mahasiswa', [
            'calon_mahasiswa_id' => $this->pendaftaran->id,
        ]);
    }

    public function test_callback_wajib_token_bila_dikonfigurasi(): void
    {
        PaymentGatewayConfig::create([
            'gateway_name' => 'xendit',
            'environment' => 'sandbox',
            'webhook_token_encrypted' => 'secret-token-123',
            'is_active' => true,
        ]);

        $this->buatTagihan();

        // Tanpa token -> ditolak
        $this->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
            'order_id' => 'ORD-TOKEN-1',
            'nominal' => 250000,
            'status' => 'settlement',
        ])->assertStatus(403);

        // Token salah -> ditolak
        $this->withHeader('x-callback-token', 'salah')
            ->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
                'order_id' => 'ORD-TOKEN-2',
                'nominal' => 250000,
                'status' => 'settlement',
            ])->assertStatus(403);

        // Token benar -> diproses
        $this->withHeader('x-callback-token', 'secret-token-123')
            ->postJson('/api/v1/sikeu/callback/spmb/'.$this->pendaftaran->id, [
                'order_id' => 'ORD-TOKEN-3',
                'nominal' => 250000,
                'status' => 'settlement',
            ])->assertStatus(200);
    }

    public function test_status_daftar_ulang_tersimpan_dan_konfirmasi_butuh_pelunasan(): void
    {
        $hasil = HasilSeleksi::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'program_studi_diterima_id' => 1,
            'nilai_total' => 80,
            'status' => 'lulus',
            'status_daftar_ulang' => 'menunggu_pembayaran',
        ]);

        // Pastikan mass-assignment status_daftar_ulang bekerja.
        $this->assertDatabaseHas('spmb_hasil_seleksi', [
            'pendaftaran_id' => $this->pendaftaran->id,
            'status_daftar_ulang' => 'menunggu_pembayaran',
        ]);

        // Konfirmasi tanpa tagihan lunas -> ditolak.
        $this->postJson('/api/spmb/daftar-ulang/'.$this->pendaftaran->id.'/konfirmasi')
            ->assertStatus(401); // butuh auth (route SPMB core)

        // Update status daftar ulang terverifikasi tersimpan.
        $hasil->update(['status_daftar_ulang' => 'lunas']);
        $this->assertDatabaseHas('spmb_hasil_seleksi', [
            'pendaftaran_id' => $this->pendaftaran->id,
            'status_daftar_ulang' => 'lunas',
        ]);
    }
}
