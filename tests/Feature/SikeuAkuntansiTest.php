<?php

namespace Tests\Feature;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuAkuntansiTest extends TestCase
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
            ['email' => 'admin.akuntansi@kampus.ac.id'],
            ['username' => 'adminakuntansi', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('akuntansi-token');

        return ['Authorization' => 'Bearer ' . ($result->plainTextToken ?? $result->accessToken)];
    }

    public function test_jurnal_list_mengembalikan_envelope_standar(): void
    {
        JurnalUmum::create([
            'nomor_jurnal' => 'JRN-TEST-' . uniqid(),
            'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Jurnal test envelope',
            'status_posting' => 'posted',
            'total_debet' => 1000,
            'total_kredit' => 1000,
        ]);

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/jurnal');

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'data', 'meta' => ['current_page', 'per_page', 'total']]);
        $this->assertIsArray($res->json('data'));
    }

    public function test_laporan_dihitung_dari_buku_bukan_tabel_operasional(): void
    {
        $kasId = AkunKeuangan::where('kode_akun', '101.01')->first()->id;
        $piutangId = AkunKeuangan::where('kode_akun', '103.01')->first()->id;
        $pendapatanId = AkunKeuangan::where('kode_akun', '401.01')->first()->id;

        // Simulasi: terbit tagihan 5jt (Dr Piutang / Cr Pendapatan), bayar 2jt (Dr Kas / Cr Piutang)
        $j1 = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-TAG-TEST1', 'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'pembayaran_mahasiswa', 'keterangan' => 'Terbit',
            'status_posting' => 'posted', 'total_debet' => 5000000, 'total_kredit' => 5000000,
        ]);
        DetailJurnalUmum::create(['jurnal_id' => $j1->id, 'akun_id' => $piutangId, 'debet' => 5000000, 'kredit' => 0]);
        DetailJurnalUmum::create(['jurnal_id' => $j1->id, 'akun_id' => $pendapatanId, 'debet' => 0, 'kredit' => 5000000]);

        $j2 = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-PAY-TEST1', 'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'pembayaran_mahasiswa', 'keterangan' => 'Bayar',
            'status_posting' => 'posted', 'total_debet' => 2000000, 'total_kredit' => 2000000,
        ]);
        DetailJurnalUmum::create(['jurnal_id' => $j2->id, 'akun_id' => $kasId, 'debet' => 2000000, 'kredit' => 0]);
        DetailJurnalUmum::create(['jurnal_id' => $j2->id, 'akun_id' => $piutangId, 'debet' => 0, 'kredit' => 2000000]);

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/laporan');

        $res->assertStatus(200)->assertJson(['status' => 'success']);
        // Pendapatan penuh 5jt (akrual, bukan 2jt kas)
        $this->assertEquals(5000000, $res->json('data.laba_rugi.pendapatan.total_pendapatan'));
        // Piutang sisa 3jt, kas 2jt
        $this->assertEquals(3000000, $res->json('data.neraca.aset.piutang_mahasiswa'));
        $this->assertEquals(2000000, $res->json('data.neraca.aset.kas_bank'));
        // Neraca seimbang
        $this->assertEquals(
            $res->json('data.neraca.aset.total_aset'),
            $res->json('data.neraca.total_pasiva')
        );
    }

    public function test_tutup_buku_mengunci_periode_dan_menutup_saldo(): void
    {
        $kasId = AkunKeuangan::where('kode_akun', '101.01')->first()->id;
        $pendapatanId = AkunKeuangan::where('kode_akun', '401.01')->first()->id;
        $labaId = AkunKeuangan::where('kode_akun', '301.02')->first()->id;

        $j = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-PAY-TUTUP', 'tanggal_jurnal' => '2026-01-15',
            'jenis_sumber' => 'pembayaran_mahasiswa', 'keterangan' => 'Bayar Januari',
            'status_posting' => 'posted', 'total_debet' => 1000000, 'total_kredit' => 1000000,
        ]);
        DetailJurnalUmum::create(['jurnal_id' => $j->id, 'akun_id' => $kasId, 'debet' => 1000000, 'kredit' => 0]);
        DetailJurnalUmum::create(['jurnal_id' => $j->id, 'akun_id' => $pendapatanId, 'debet' => 0, 'kredit' => 1000000]);

        // Buat periode
        $resPeriode = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/periode', [
                'nama_periode' => 'Januari 2026',
                'tanggal_mulai' => '2026-01-01',
                'tanggal_selesai' => '2026-01-31',
            ]);
        $resPeriode->assertStatus(201);
        $periodeId = $resPeriode->json('data.id');

        // Periode bertabrakan ditolak
        $resOverlap = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/periode', [
                'nama_periode' => 'Overlap',
                'tanggal_mulai' => '2026-01-15',
                'tanggal_selesai' => '2026-02-15',
            ]);
        $resOverlap->assertStatus(422);

        // Tutup buku
        $resTutup = $this->withHeaders($this->headers())
            ->postJson("/api/v1/sikeu/periode/{$periodeId}/tutup");
        $resTutup->assertStatus(200)->assertJson(['status' => 'success']);

        // Jurnal penutup ada: Dr 401.01 1jt / Cr 301.02 1jt
        $tutup = JurnalUmum::where('nomor_jurnal', 'like', 'JRN-TUTUP-%')->first();
        $this->assertNotNull($tutup);
        $this->assertEquals(1000000, (float) DetailJurnalUmum::where('jurnal_id', $tutup->id)->where('akun_id', $labaId)->sum('kredit'));

        // Periode terkunci
        $this->assertDatabaseHas('sikeu_periode_akuntansi', ['id' => $periodeId, 'status' => 'ditutup']);

        // Tutup dua kali ditolak
        $resTutup2 = $this->withHeaders($this->headers())
            ->postJson("/api/v1/sikeu/periode/{$periodeId}/tutup");
        $resTutup2->assertStatus(422);

        // Laporan: surplus berjalan 0 (sudah ditutup), laba ditahan 1jt
        $resLap = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/laporan');
        $resLap->assertStatus(200);
        $this->assertEquals(0, $resLap->json('data.laba_rugi.surplus_defisit'));
        $this->assertEquals(1000000, $resLap->json('data.neraca.ekuitas.laba_ditahan_301'));
    }

    public function test_edit_jurnal_manual_dengan_pengaman(): void
    {
        $kasId = AkunKeuangan::where('kode_akun', '101.01')->first()->id;
        $bebanId = AkunKeuangan::where('kode_akun', '502.01')->first()->id;

        $manual = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-MANUAL-001', 'tanggal_jurnal' => '2026-03-10',
            'jenis_sumber' => 'penyesuaian', 'keterangan' => 'Koreksi manual awal',
            'status_posting' => 'posted', 'total_debet' => 500000, 'total_kredit' => 500000,
        ]);
        DetailJurnalUmum::create(['jurnal_id' => $manual->id, 'akun_id' => $bebanId, 'debet' => 500000, 'kredit' => 0]);
        DetailJurnalUmum::create(['jurnal_id' => $manual->id, 'akun_id' => $kasId, 'debet' => 0, 'kredit' => 500000]);

        // Sukses: ubah keterangan + nominal seimbang
        $resOk = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/akuntansi/jurnal/{$manual->id}", [
                'keterangan' => 'Koreksi manual revisi',
                'details' => [
                    ['akun_id' => $bebanId, 'debet' => 600000, 'kredit' => 0],
                    ['akun_id' => $kasId, 'debet' => 0, 'kredit' => 600000],
                ],
            ]);
        $resOk->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('sikeu_jurnal_umum', ['id' => $manual->id, 'total_debet' => 600000]);

        // Gagal: tidak seimbang
        $resBal = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/akuntansi/jurnal/{$manual->id}", [
                'details' => [
                    ['akun_id' => $bebanId, 'debet' => 600000, 'kredit' => 0],
                    ['akun_id' => $kasId, 'debet' => 0, 'kredit' => 100000],
                ],
            ]);
        $resBal->assertStatus(422);

        // Gagal: jurnal otomatis terkunci
        $auto = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-PAY-AUTO1', 'tanggal_jurnal' => '2026-03-10',
            'jenis_sumber' => 'pembayaran_mahasiswa', 'referensi_id' => 999,
            'keterangan' => 'Otomatis', 'status_posting' => 'posted',
            'total_debet' => 1000, 'total_kredit' => 1000,
        ]);
        $resAuto = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/akuntansi/jurnal/{$auto->id}", ['keterangan' => 'Coba ubah']);
        $resAuto->assertStatus(422);

        // Gagal: tanggal masuk periode yang sudah ditutup
        $periode = \App\Models\Sikeu\PeriodeAkuntansi::create([
            'nama_periode' => 'Maret 2026', 'tahun' => 2026, 'bulan' => 3,
            'tanggal_mulai' => '2026-03-01', 'tanggal_selesai' => '2026-03-31', 'status' => 'ditutup',
        ]);
        $resTutup = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/akuntansi/jurnal/{$manual->id}", ['keterangan' => 'Coba ubah periode tutup']);
        $resTutup->assertStatus(422);
        $this->assertDatabaseHas('sikeu_jurnal_umum', ['id' => $manual->id, 'keterangan' => 'Koreksi manual revisi']);
    }

    public function test_hapus_jurnal_manual_dengan_pengaman(): void
    {
        $kasId = AkunKeuangan::where('kode_akun', '101.01')->first()->id;
        $bebanId = AkunKeuangan::where('kode_akun', '502.01')->first()->id;

        $manual = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-MANUAL-DEL', 'tanggal_jurnal' => '2026-04-10',
            'jenis_sumber' => 'penyesuaian', 'keterangan' => 'Hapus saya',
            'status_posting' => 'posted', 'total_debet' => 100000, 'total_kredit' => 100000,
        ]);
        DetailJurnalUmum::create(['jurnal_id' => $manual->id, 'akun_id' => $bebanId, 'debet' => 100000, 'kredit' => 0]);
        DetailJurnalUmum::create(['jurnal_id' => $manual->id, 'akun_id' => $kasId, 'debet' => 0, 'kredit' => 100000]);

        // Sukses hapus manual
        $resOk = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/akuntansi/jurnal/{$manual->id}");
        $resOk->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('sikeu_jurnal_umum', ['id' => $manual->id]);
        $this->assertDatabaseMissing('sikeu_detail_jurnal_umum', ['jurnal_id' => $manual->id]);

        // Gagal: jurnal otomatis terkunci
        $auto = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-PAY-AUTO9', 'tanggal_jurnal' => '2026-04-10',
            'jenis_sumber' => 'pembayaran_mahasiswa', 'referensi_id' => 123,
            'keterangan' => 'Otomatis', 'status_posting' => 'posted',
            'total_debet' => 1000, 'total_kredit' => 1000,
        ]);
        $resAuto = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/akuntansi/jurnal/{$auto->id}");
        $resAuto->assertStatus(422);
        $this->assertDatabaseHas('sikeu_jurnal_umum', ['id' => $auto->id]);
    }

    public function test_pengaturan_prefix_nomor_jurnal(): void
    {
        // Default
        $resGet = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pengaturan-jurnal');
        $resGet->assertStatus(200);
        $this->assertEquals('JRN-PAY', $resGet->json('data.pembayaran.nilai'));

        // Ubah mengikuti kebijakan kampus
        $resPut = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/pengaturan-jurnal', [
                'prefix' => ['pembayaran' => 'KWT-BYR', 'tidak_ada' => 'XXX'],
            ]);
        $resPut->assertStatus(200);
        $this->assertEquals('KWT-BYR', $resPut->json('data.pembayaran.nilai'));
        $this->assertArrayNotHasKey('tidak_ada', $resPut->json('data'));

        // Format salah ditolak
        $resBad = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/pengaturan-jurnal', [
                'prefix' => ['pembayaran' => 'ada spasi!'],
            ]);
        $resBad->assertStatus(422);

        $this->assertEquals('KWT-BYR', \App\Services\Sikeu\JurnalSikeuService::prefix('pembayaran'));
    }
}
