<?php

namespace Tests\Feature;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\H2hInquiry;
use App\Models\Sikeu\H2hReversal;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class H2hBankNativeTest extends TestCase
{
    use DatabaseTransactions;

    protected string $secretKey = 'test-btns-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        config([
            'services.sikeu.btns_api_token' => 'test-btns-token',
            'services.sikeu.btns_secret_key' => 'test-btns-secret-key',
        ]);
        $this->withHeaders([
            'sopingi-btns' => 'test-btns-token',
        ]);
    }

    public function test_ping_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/h2h/btns/tes');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'rc' => 'OK',
            ]);
    }

    public function test_inquiry_with_invalid_checksum_fails(): void
    {
        $payload = [
            'action' => 'inquiry',
            'kodeBank' => 'BTNS',
            'kodeBiller' => '31310',
            'kodeChannel' => 'MBANK',
            'kodeTerminal' => 'TERM_01',
            'nomorPembayaran' => '13202110065',
            'tanggalTransaksi' => '2026-11-10 10:00:00',
            'idTransaksi' => 'TRX-TEST-001',
            'checksum' => 'invalid_checksum',
        ];

        $response = $this->postJson('/api/v1/h2h/btns', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'rc' => '30',
                'msg' => 'Checksum invalid',
            ]);
    }

    public function test_inquiry_with_unknown_bank_fails(): void
    {
        $payload = [
            'action' => 'inquiry',
            'kodeBank' => 'MANDIRI',
            'nomorPembayaran' => '13202110065',
            'tanggalTransaksi' => '20261111100000',
            'checksum' => 'dummychecksum',
        ];

        $response = $this->postJson('/api/v1/h2h/btns', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'rc' => '31',
            ]);
    }

    public function test_inquiry_and_payment_successful_workflow(): void
    {
        // 1. Siapkan Unit Kas BSN
        $unitKas = UnitKas::firstOrCreate(
            ['bank_name' => 'BSN'],
            [
                'nama_kas' => 'Bank BSN Host-to-Host Kampus',
                'tipe_kas' => 'operasional',
                'kanal' => 'bank_h2h',
                'saldo_awal' => 0,
                'saldo_saat_ini' => 0,
                'status' => 1,
            ]
        );
        $initialKasSaldo = (float) $unitKas->saldo_saat_ini;

        // 2. Siapkan Tagihan Mahasiswa untuk tes
        $nomorPembayaran = '13' . rand(202100000, 202199999);
        $nominalTagihan = 1500000;

        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan' => 'TAG-TEST-' . uniqid(),
            'total_tagihan' => $nominalTagihan,
            'total_bayar' => 0,
            'total_potongan' => 0,
            'total_denda' => 0,
            'status' => 'belum_bayar',
            'h2h_custid' => $nomorPembayaran,
            'source_system' => 'SIAKAD',
        ]);

        $masterBiaya = \App\Models\Sikeu\MasterBiaya::firstOrCreate(
            ['kode' => 'SPP'],
            [
                'nama' => 'Sumbangan Pembinaan Pendidikan',
                'tipe' => 'spp',
                'nominal_standar' => $nominalTagihan,
                'is_active' => true,
            ]
        );

        DetailTagihan::create([
            'tagihan_id' => $tagihan->id,
            'master_biaya_id' => $masterBiaya->id,
            'nominal' => $nominalTagihan,
            'terbayar' => 0,
            'keterangan' => 'SPP Semester 3',
        ]);

        // 3. Test INQUIRY
        $tglTransaksi = '2026-11-10 10:15:00';
        $idTransaksiInquiry = 'INQ-' . uniqid();
        $inquiryChecksum = sha1($nomorPembayaran . $this->secretKey . $tglTransaksi);

        $inquiryPayload = [
            'action' => 'inquiry',
            'kodeBank' => 'BTNS',
            'kodeBiller' => '31310',
            'kodeChannel' => 'MBANK',
            'kodeTerminal' => 'ATM_SOLO_01',
            'nomorPembayaran' => $nomorPembayaran,
            'tanggalTransaksi' => $tglTransaksi,
            'idTransaksi' => $idTransaksiInquiry,
            'checksum' => $inquiryChecksum,
        ];

        $inquiryRes = $this->postJson('/api/v1/h2h/btns', $inquiryPayload);

        $inquiryRes->assertStatus(200)
            ->assertJson([
                'rc' => '00',
                'msg' => 'Inquiry Success',
                'nomorPembayaran' => $nomorPembayaran,
                'totalNominal' => (string) $nominalTagihan,
                'apiBank' => 'BTNS',
            ]);

        $inquiryData = $inquiryRes->json();
        $idTagihan = $inquiryData['idTagihan'];
        $this->assertNotEmpty($idTagihan);

        // Verifikasi row tersimpan di sikeu_h2h_inquiries
        $this->assertDatabaseHas('sikeu_h2h_inquiries', [
            'id_transaksi' => $idTransaksiInquiry,
            'nomor_pembayaran' => $nomorPembayaran,
        ]);

        // 4. Test PAYMENT
        $tglPayment = '2026-11-10 10:17:00';
        $idTransaksiPayment = 'PAY-' . uniqid();
        $nomorJurnal = 'JRN-BTNS-' . uniqid();
        $paymentChecksum = sha1($nomorPembayaran . $this->secretKey . $tglPayment . (string) $nominalTagihan . $nomorJurnal);

        $paymentPayload = [
            'action' => 'payment',
            'kodeBank' => 'BTNS',
            'kodeBiller' => '31310',
            'kodeChannel' => 'MBANK',
            'kodeTerminal' => 'ATM_SOLO_01',
            'nomorPembayaran' => $nomorPembayaran,
            'tanggalTransaksi' => $tglPayment,
            'idTransaksi' => $idTransaksiPayment,
            'idTagihan' => $idTagihan,
            'totalNominal' => (string) $nominalTagihan,
            'nomorJurnalPembukuan' => $nomorJurnal,
            'checksum' => $paymentChecksum,
        ];

        $paymentRes = $this->postJson('/api/v1/h2h/btns', $paymentPayload);

        $paymentRes->assertStatus(200)
            ->assertJson([
                'rc' => '00',
                'msg' => 'Payment Success',
                'totalNominal' => (string) $nominalTagihan,
            ]);

        // Verifikasi tagihan LUNAS
        $tagihanFresh = $tagihan->fresh();
        $this->assertEquals('lunas', $tagihanFresh->status);
        $this->assertEquals($nominalTagihan, (float) $tagihanFresh->total_bayar);

        // Verifikasi tercatat di sikeu_pembayaran
        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => $nomorJurnal,
            'channel_bayar' => 'VA_BSN',
            'status' => 'success',
        ]);

        // Verifikasi saldo kas bertambah
        $unitKasFresh = $unitKas->fresh();
        $this->assertEquals($initialKasSaldo + $nominalTagihan, (float) $unitKasFresh->saldo_saat_ini);

        // 5. Test IDEMPOTENSI (Bayar ulang dengan jurnal sama tidak duplicate)
        $paymentRes2 = $this->postJson('/api/v1/h2h/btns', $paymentPayload);
        $paymentRes2->assertStatus(200)->assertJson(['rc' => '00']);
        $this->assertEquals(1, Pembayaran::where('kode_transaksi', $nomorJurnal)->count());

        // 6. Test REVERSAL
        $tglReversal = '2026-11-10 10:20:00';
        $idTransaksiRev = 'REV-' . uniqid();
        $reversalChecksum = sha1($nomorPembayaran . $this->secretKey . $tglReversal . (string) $nominalTagihan . $nomorJurnal);

        $reversalPayload = [
            'action' => 'reversal',
            'kodeBank' => 'BTNS',
            'nomorPembayaran' => $nomorPembayaran,
            'tanggalTransaksi' => $tglReversal,
            'tanggalTransaksiAsal' => $tglPayment,
            'idTransaksi' => $idTransaksiRev,
            'totalNominal' => (string) $nominalTagihan,
            'nomorJurnalPembukuan' => $nomorJurnal,
            'checksum' => $reversalChecksum,
        ];

        $reversalRes = $this->postJson('/api/v1/h2h/btns', $reversalPayload);

        $reversalRes->assertStatus(200)
            ->assertJson([
                'rc' => '00',
                'msg' => 'Reversal Success',
            ]);

        // Tagihan kembali belum lunas setelah reversal
        $tagihanAfterRev = $tagihan->fresh();
        $this->assertEquals('belum_bayar', $tagihanAfterRev->status);
        $this->assertEquals(0, (float) $tagihanAfterRev->total_bayar);

        // Saldo kas berkurang kembali
        $unitKasAfterRev = $unitKas->fresh();
        $this->assertEquals($initialKasSaldo, (float) $unitKasAfterRev->saldo_saat_ini);

        // Tercatat di sikeu_h2h_reversals
        $this->assertDatabaseHas('sikeu_h2h_reversals', [
            'nomor_jurnal_pembukuan' => $nomorJurnal,
        ]);
    }

    public function test_xendit_and_h2h_coexist_in_same_database_and_tables(): void
    {
        // 1. Siapkan Unit Kas untuk kedua kanal
        $unitKasH2h = UnitKas::firstOrCreate(
            ['bank_name' => 'BSN'],
            [
                'nama_kas' => 'Bank BSN Host-to-Host Kampus',
                'tipe_kas' => 'operasional',
                'kanal' => 'bank_h2h',
                'saldo_awal' => 0,
                'saldo_saat_ini' => 0,
                'status' => 1,
            ]
        );

        $unitKasXendit = UnitKas::firstOrCreate(
            ['kanal' => 'xendit'],
            [
                'nama_kas' => 'Xendit Disbursement Kampus',
                'tipe_kas' => 'operasional',
                'bank_name' => 'Xendit',
                'saldo_awal' => 0,
                'saldo_saat_ini' => 0,
                'status' => 1,
            ]
        );

        // 2. Tagihan 1: Dibayar via H2H BTNS
        $tagihanH2h = TagihanMahasiswa::create([
            'nomor_tagihan' => 'TAG-H2H-' . uniqid(),
            'total_tagihan' => 2000000,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'h2h_custid' => '13202199001',
            'h2h_id_tagihan' => '2026111001',
            'source_system' => 'SIAKAD',
        ]);

        $tgl = '2026-11-10 11:00:00';
        $jurnalH2h = 'JRN-H2H-' . uniqid();
        $checksum = sha1('13202199001' . $this->secretKey . $tgl . '2000000' . $jurnalH2h);

        $this->postJson('/api/v1/h2h/btns', [
            'action' => 'payment',
            'kodeBank' => 'BTNS',
            'nomorPembayaran' => '13202199001',
            'tanggalTransaksi' => $tgl,
            'idTransaksi' => 'PAY-H2H-1',
            'idTagihan' => '2026111001',
            'totalNominal' => '2000000',
            'nomorJurnalPembukuan' => $jurnalH2h,
            'checksum' => $checksum,
        ])->assertStatus(200)->assertJson(['rc' => '00']);

        // 3. Tagihan 2: Terbit VA Xendit dan dibayar via Callback VA
        $tagihanXendit = TagihanMahasiswa::create([
            'nomor_tagihan' => 'TAG-XND-' . uniqid(),
            'total_tagihan' => 3000000,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'source_system' => 'SIAKAD',
        ]);

        $vaXendit = \App\Models\Sikeu\VirtualAccount::create([
            'tagihan_id' => $tagihanXendit->id,
            'va_number' => '8808' . rand(10000000, 99999999),
            'bank_kode' => 'BRI',
            'bank_nama' => 'Bank BRI',
            'nominal' => 3000000,
            'status' => 'aktif',
            'xendit_va_id' => 'va_xnd_' . uniqid(),
        ]);

        // Simulasikan Callback VA Paid
        $this->postJson('/api/v1/sikeu/callback/va-paid', [
            'va_number' => $vaXendit->va_number,
            'order_id' => 'XND-PAY-' . uniqid(),
            'channel' => 'VA_XENDIT_BRI',
            'nominal' => 3000000,
            'bank_kode' => 'BRI',
            'status' => 'settlement',
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        // 4. Verifikasi kedua pembayaran tersimpan di tabel yang sama (sikeu_pembayaran)
        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id' => $tagihanH2h->id,
            'channel_bayar' => 'VA_BSN',
            'unit_kas_id' => $unitKasH2h->id,
            'jumlah_bayar' => 2000000,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id' => $tagihanXendit->id,
            'channel_bayar' => 'VA_XENDIT_BRI',
            'unit_kas_id' => $unitKasXendit->id,
            'jumlah_bayar' => 3000000,
            'status' => 'success',
        ]);

        // Verifikasi kedua tagihan lunas secara harmonis
        $this->assertEquals('lunas', $tagihanH2h->fresh()->status);
        $this->assertEquals('lunas', $tagihanXendit->fresh()->status);
    }
}
