<?php

namespace Tests\Feature;

use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use Tests\TestCase;

class ValidasiPembayaranPublikTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_validasi_pembayaran_publik_berhasil_untuk_transaksi_valid(): void
    {
        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan' => 'TAG-VAL-' . uniqid(),
            'total_tagihan' => 2500000,
            'total_bayar' => 2500000,
            'sisa' => 0,
            'status' => 'lunas',
            'mahasiswa_id' => 99991,
        ]);

        $kodeTransaksi = 'TRX-VAL-' . uniqid();
        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => $kodeTransaksi,
            'jumlah_bayar' => 2500000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
            'catatan' => 'Uji validasi kuitansi digital publik',
        ]);

        // Akses endpoint publik tanpa header Authorization / Token
        $response = $this->getJson("/api/v1/sikeu/pembayaran/validasi/{$kodeTransaksi}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'kode_transaksi' => $kodeTransaksi,
                    'status' => 'success',
                    'is_valid' => true,
                    'jumlah_bayar' => 2500000,
                    'channel_bayar' => 'LOKET_TUNAI',
                ],
            ]);

        $this->assertArrayHasKey('mahasiswa', $response->json('data'));
        $this->assertArrayHasKey('tagihan', $response->json('data'));
        $this->assertArrayHasKey('security_hash', $response->json('data'));
    }

    public function test_validasi_pembayaran_publik_mengembalikan_404_jika_kode_tidak_ditemukan(): void
    {
        $response = $this->getJson('/api/v1/sikeu/pembayaran/validasi/TRX-TIDAK-ADA-999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'data' => null,
            ]);
    }
}
