<?php

namespace Tests\Feature;

use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuKasirGlobalTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.kasir@kampus.ac.id'],
            ['username' => 'kasiradmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('kasir-token');
        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    protected function createCalonMahasiswa(array $attributes = []): PendaftaranCalonMhs
    {
        $jalur = \App\Models\Spmb\JalurMasuk::firstOrCreate(
            ['kode' => 'REG-TEST'],
            ['nama' => 'Jalur Reguler Test', 'tipe' => 'reguler', 'is_active' => true]
        );

        $gelombang = \App\Models\Spmb\GelombangPenerimaan::firstOrCreate(
            ['nama' => 'Gelombang 1 Test'],
            [
                'jalur_masuk_id' => $jalur->id,
                'tahun_akademik_id' => 1,
                'tanggal_buka' => now()->subMonth(),
                'tanggal_tutup' => now()->addMonth(),
                'status' => 'aktif',
            ]
        );

        $defaults = [
            'gelombang_id' => $gelombang->id,
            'user_id' => $this->admin->id,
            'program_studi_id' => 1,
            'nama_lengkap' => 'Calon Mhs ' . uniqid(),
            'no_pendaftaran' => 'REG-' . rand(10000, 99999),
            'nik' => '3201' . rand(100000000000, 999999999999),
            'no_hp' => '0812' . rand(10000000, 99999999),
            'status' => 'verified',
        ];

        return PendaftaranCalonMhs::create(array_merge($defaults, $attributes));
    }

    public function test_search_mahasiswa_finds_calon_mahasiswa_without_nim()
    {
        $calon = $this->createCalonMahasiswa([
            'nama_lengkap' => 'Budi Calon Baru ' . uniqid(),
            'no_pendaftaran' => 'REG-' . rand(10000, 99999),
            'nik' => '320101' . rand(1000000000, 9999999999),
            'no_hp' => '0812' . rand(10000000, 99999999),
        ]);

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/mahasiswa-search?q=' . $calon->no_pendaftaran);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');

        $data = $res->json('data');
        $found = collect($data)->firstWhere('calon_mahasiswa_id', $calon->id);

        $this->assertNotNull($found);
        $this->assertTrue($found['is_calon_mahasiswa']);
        $this->assertEquals($calon->no_pendaftaran, $found['no_pendaftaran']);
        $this->assertEquals('-', $found['nim']);
    }

    public function test_get_unpaid_bills_for_calon_mahasiswa()
    {
        $calon = $this->createCalonMahasiswa([
            'nama_lengkap' => 'Siti Calon ' . uniqid(),
            'no_pendaftaran' => 'REG-' . rand(10000, 99999),
            'nik' => '320102' . rand(1000000000, 9999999999),
        ]);

        $tagihan = TagihanMahasiswa::create([
            'calon_mahasiswa_id' => $calon->id,
            'tipe_referensi' => 'calon_mahasiswa',
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-TEST-' . uniqid(),
            'total_tagihan' => 5000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/mahasiswa/{$calon->id}/unpaid-bills?type=calon");

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $this->assertEquals($calon->no_pendaftaran, $res->json('data.mahasiswa.no_pendaftaran'));
        $this->assertEquals('-', $res->json('data.mahasiswa.nim'));
        $this->assertTrue($res->json('data.mahasiswa.is_calon_mahasiswa'));
        $this->assertGreaterThanOrEqual(1, count($res->json('data.bills')));
    }

    public function test_direct_cashier_payment_for_calon_mahasiswa()
    {
        $calon = $this->createCalonMahasiswa([
            'nama_lengkap' => 'Ahmad Calon ' . uniqid(),
            'no_pendaftaran' => 'REG-' . rand(10000, 99999),
            'nik' => '320103' . rand(1000000000, 9999999999),
        ]);

        $payload = [
            'calon_mahasiswa_id' => $calon->id,
            'tipe_referensi' => 'calon_mahasiswa',
            'items' => [
                [
                    'nominal' => 2500000,
                    'keterangan' => 'Biaya Orientasi Mahasiswa Baru',
                ]
            ],
            'jumlah_bayar' => 2500000,
            'channel_bayar' => 'LOKET_TUNAI',
            'catatan' => 'Pembayaran tunai kasir loket',
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran/direct-cashier', $payload);

        $res->assertStatus(201);
        $res->assertJsonPath('status', 'success');
        $tagihanId = $res->json('data.tagihan_id');

        $tagihan = TagihanMahasiswa::find($tagihanId);
        $this->assertNotNull($tagihan);
        $this->assertEquals($calon->id, $tagihan->calon_mahasiswa_id);
        $this->assertNull($tagihan->mahasiswa_id);
        $this->assertEquals('calon_mahasiswa', $tagihan->tipe_referensi);
        $this->assertEquals('lunas', $tagihan->status);
    }

    public function test_koreksi_payment_reverses_cashier_discount()
    {
        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => 1,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-KOREKSI-' . uniqid(),
            'total_tagihan' => 3000000,
            'total_potongan' => 500000,
            'total_denda' => 0,
            'total_bayar' => 2500000,
            'status' => 'lunas',
            'jatuh_tempo' => now()->addDays(10),
        ]);

        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'TRX-TEST-' . uniqid(),
            'jumlah_bayar' => 2500000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);

        $potongan = PotonganTagihan::create([
            'tagihan_id' => $tagihan->id,
            'tipe' => 'diskon',
            'nominal_potongan' => 500000,
            'keterangan' => 'Potongan Kasir Loket',
            'diinput_oleh' => $this->admin->id,
            'created_at' => $pembayaran->created_at,
        ]);

        $res = $this->withHeaders($this->headers())
            ->postJson("/api/v1/sikeu/pembayaran/{$pembayaran->id}/koreksi", [
                'alasan_koreksi' => 'Salah input nominal kasir pembayaran loket',
            ]);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');

        $pembayaran->refresh();
        $tagihan->refresh();

        $this->assertEquals('reversed', $pembayaran->status);
        $this->assertEquals(0, (float)$tagihan->total_bayar);
        $this->assertEquals(0, (float)$tagihan->total_potongan);
        $this->assertEquals('belum_bayar', $tagihan->status);
    }

    public function test_kasir_alokasi_fifo_per_komponen_dan_koreksi_lifo(): void
    {
        $biayaA = MasterBiaya::firstOrCreate(
            ['kode' => 'FIFO_A'],
            ['nama' => 'Komponen A FIFO', 'tipe' => 'spp', 'nominal_standar' => 1000000, 'is_active' => true]
        );
        $biayaB = MasterBiaya::firstOrCreate(
            ['kode' => 'FIFO_B'],
            ['nama' => 'Komponen B FIFO', 'tipe' => 'spp', 'nominal_standar' => 2000000, 'is_active' => true]
        );

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => 1,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-FIFO-' . uniqid(),
            'total_tagihan' => 3000000,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(10),
        ]);
        $detailA = \App\Models\Sikeu\DetailTagihan::create([
            'tagihan_id' => $tagihan->id, 'master_biaya_id' => $biayaA->id,
            'nominal' => 1000000, 'potongan' => 0, 'nominal_bersih' => 1000000,
        ]);
        $detailB = \App\Models\Sikeu\DetailTagihan::create([
            'tagihan_id' => $tagihan->id, 'master_biaya_id' => $biayaB->id,
            'nominal' => 2000000, 'potongan' => 0, 'nominal_bersih' => 2000000,
        ]);

        // Bayar parsial 1,5jt: komponen A lunas (1jt), komponen B terbayar 500rb
        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran/kasir', [
                'tagihan_ids' => [$tagihan->id],
                'jumlah_bayar' => 1500000,
                'channel_bayar' => 'LOKET_TUNAI',
            ]);

        $res->assertStatus(201)->assertJson(['status' => 'success']);
        $this->assertArrayHasKey('rincian_komponen', $res->json('data.kuitansi'));

        $this->assertDatabaseHas('sikeu_detail_tagihan', ['id' => $detailA->id, 'terbayar' => 1000000]);
        $this->assertDatabaseHas('sikeu_detail_tagihan', ['id' => $detailB->id, 'terbayar' => 500000]);

        // Jurnal pembayaran akrual: Dr Kas / Cr Piutang (bukan pendapatan)
        $jurnalPay = \App\Models\Sikeu\JurnalUmum::where('nomor_jurnal', 'like', 'JRN-PAY-%')
            ->where('referensi_id', $res->json('data.pembayaran.id'))
            ->first();
        $this->assertNotNull($jurnalPay);
        $piutangId = \App\Models\Sikeu\AkunKeuangan::where('kode_akun', '103.01')->first()->id;
        $kreditPiutang = \App\Models\Sikeu\DetailJurnalUmum::where('jurnal_id', $jurnalPay->id)
            ->where('akun_id', $piutangId)->first();
        $this->assertEquals(1500000, (float) $kreditPiutang->kredit);

        // Rekap unpaid bills memuat sisa per komponen
        $bills = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/mahasiswa/1/unpaid-bills');
        $bills->assertStatus(200);
        $first = collect($bills->json('data.bills'))->firstWhere('id', $tagihan->id);
        $this->assertNotNull($first);
        $this->assertEquals(0, $first['details'][0]['sisa']);
        $this->assertEquals(1500000, $first['details'][1]['sisa']);

        // Koreksi mengembalikan alokasi LIFO: komponen B dulu
        $pembayaranId = $res->json('data.pembayaran.id');
        $resKoreksi = $this->withHeaders($this->headers())
            ->postJson("/api/v1/sikeu/pembayaran/{$pembayaranId}/koreksi", [
                'alasan_koreksi' => 'Koreksi alokasi FIFO per komponen',
            ]);
        $resKoreksi->assertStatus(200);

        $this->assertDatabaseHas('sikeu_detail_tagihan', ['id' => $detailA->id, 'terbayar' => 0]);
        $this->assertDatabaseHas('sikeu_detail_tagihan', ['id' => $detailB->id, 'terbayar' => 0]);

        // Jurnal koreksi: Dr Piutang / Cr Kas
        $jurnalRev = \App\Models\Sikeu\JurnalUmum::where('nomor_jurnal', 'like', 'JRN-REV-%')
            ->where('referensi_id', $pembayaranId)
            ->first();
        $this->assertNotNull($jurnalRev);
        $debetPiutang = \App\Models\Sikeu\DetailJurnalUmum::where('jurnal_id', $jurnalRev->id)
            ->where('akun_id', $piutangId)->first();
        $this->assertEquals(1500000, (float) $debetPiutang->debet);
    }

    public function test_va_number_service_generates_consistent_format()
    {
        $va = \App\Services\Sikeu\VaNumberService::generate('20261001');
        $this->assertEquals('880120020261001', $va);

        $vaCustom = \App\Services\Sikeu\VaNumberService::generate('123456', '70012');
        $this->assertEquals('700120000123456', $vaCustom);
    }

    public function test_cetak_bukti_fails_if_dispensasi_not_approved()
    {
        $prodi = \App\Models\Spmb\MasterProgramStudi::firstOrCreate(
            ['id' => 1],
            ['kode_prodi' => 'TI', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = \App\Models\Siakad\Mahasiswa::firstOrCreate(
            ['nim' => '2026999901'],
            [
                'nama_lengkap' => 'Dispensasi Test Mhs',
                'program_studi_id' => $prodi->id,
                'angkatan' => 2026,
                'status' => 'aktif',
            ]
        );

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-TEST-DISP-' . uniqid(),
            'total_tagihan' => 3000000,
            'status' => 'belum_bayar',
            'tahun_akademik_id' => 1,
        ]);

        $disp = \App\Models\Sikeu\DispensasiTagihan::create([
            'mahasiswa_id' => $mhs->id,
            'tagihan_id' => $tagihan->id,
            'tipe_dispensasi' => 'penundaan_jatuh_tempo',
            'nominal_per_cicilan' => 1000000,
            'jatuh_tempo_baru' => now()->addMonth()->toDateString(),
            'status' => 'pending',
            'alasan' => 'Menunggu pencairan dana beasiswa',
        ]);

        // Trying to print unapproved dispensation should return 422
        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/dispensasi/{$disp->id}/cetak-bukti");

        $res->assertStatus(422);
        $res->assertJsonPath('status', 'error');

        // Approve it and try again
        $disp->update(['status' => 'approved', 'disetujui_oleh' => $this->admin->id, 'tanggal_persetujuan' => now()]);

        $resApproved = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/dispensasi/{$disp->id}/cetak-bukti");

        $resApproved->assertStatus(200);
        $resApproved->assertJsonPath('status', 'success');
        $resApproved->assertJsonStructure([
            'status',
            'data' => [
                'nomor_dispensasi',
                'status',
                'mahasiswa',
                'tagihan',
                'pejabat_approver' => ['digital_signature_hash']
            ]
        ]);
        $this->assertStringStartsWith('SIG-DISP-', $resApproved->json('data.pejabat_approver.digital_signature_hash'));
    }

    public function test_setting_tarif_show_and_protected_destroy()
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'TEST-BIAYA-TARIF'],
            ['nama' => 'Biaya Ujian Praktikum Test', 'tipe' => 'praktikum', 'is_active' => true]
        );

        $tarif = \App\Models\Sikeu\SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'jalur_kelas' => 'REGULER',
            'nominal' => 750000,
            'is_active' => true,
        ]);

        // Test show
        $resShow = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/master/setting-tarif/{$tarif->id}");

        $resShow->assertStatus(200);
        $resShow->assertJsonPath('status', 'success');
        $resShow->assertJsonPath('data.id', $tarif->id);

        // Delete without tagihan should succeed
        $resDel = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/master/setting-tarif/{$tarif->id}");

        $resDel->assertStatus(200);
        $resDel->assertJsonPath('status', 'success');
    }

    public function test_preview_mass_target_returns_correct_structure()
    {
        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/tagihan/preview-mass-target?tahun_angkatan=2026&jalur_kelas=REGULER');

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $res->assertJsonStructure([
            'status',
            'data' => [
                'total_mahasiswa',
                'sample_mahasiswa',
            ]
        ]);
    }
}

