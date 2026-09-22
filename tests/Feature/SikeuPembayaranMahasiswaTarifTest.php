<?php

namespace Tests\Feature;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\SettingTarif;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuPembayaranMahasiswaTarifTest extends TestCase
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
            ['email' => 'admin.keuangan@kampus.ac.id'],
            ['username' => 'adminkeuangan', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('keuangan-token');
        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    public function test_get_katalog_biaya_dan_prodi_list(): void
    {
        MasterBiaya::firstOrCreate(
            ['kode' => 'SPP_TETAP'],
            ['nama' => 'SPP Tetap Semester', 'tipe' => 'spp', 'nominal_standar' => 4500000, 'is_active' => true]
        );

        $resKatalog = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/katalog-biaya');

        $resKatalog->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $resProdi = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/prodi-list');

        $resProdi->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    public function test_store_multi_tarif_untuk_komponen_biaya_sama_dengan_prodi_berbeda(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SPP_MULTI'],
            ['nama' => 'SPP Kuliah Reguler', 'tipe' => 'spp', 'nominal_standar' => 5000000, 'is_active' => true]
        );

        $prodiTI = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI01'],
            ['nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]
        );

        $prodiSI = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'SI01'],
            ['nama' => 'Sistem Informasi', 'jenjang' => 'S1', 'is_active' => true]
        );

        // 1. Tarif SPP untuk Prodi TI Angkatan 2026: Rp 5.000.000
        $payloadTI = [
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'program_studi_id' => $prodiTI->id,
            'nominal' => 5000000,
            'keterangan' => 'Tarif SPP TI Angkatan 2026',
            'is_active' => true,
        ];

        $res1 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', $payloadTI);

        $res1->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'master_biaya_id' => $biaya->id,
                    'tahun_angkatan' => 2026,
                    'program_studi_id' => $prodiTI->id,
                    'nominal' => 5000000,
                ]
            ]);

        // 2. Variasi Tarif Kedua untuk SPP yang sama: Prodi SI Angkatan 2026: Rp 4.500.000
        $payloadSI = [
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'program_studi_id' => $prodiSI->id,
            'nominal' => 4500000,
            'keterangan' => 'Tarif SPP SI Angkatan 2026',
            'is_active' => true,
        ];

        $res2 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', $payloadSI);

        $res2->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'master_biaya_id' => $biaya->id,
                    'tahun_angkatan' => 2026,
                    'program_studi_id' => $prodiSI->id,
                    'nominal' => 4500000,
                ]
            ]);

        // 3. Verifikasi kombinasi duplikat ditolak (422)
        $resDuplikat = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', $payloadTI);

        $resDuplikat->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_tidak_bisa_input_tarif_jika_sudah_ada_tarif_aktif_semua_prodi(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'UKT_GLOBAL_TEST'],
            ['nama' => 'UKT Global Kampus', 'tipe' => 'spp', 'nominal_standar' => 3500000, 'is_active' => true]
        );

        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'MN01'],
            ['nama' => 'Manajemen', 'jenjang' => 'S1', 'is_active' => true]
        );

        // Pasang tarif aktif untuk Semua Program Studi (Global)
        SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2027,
            'program_studi_id' => null,
            'nominal' => 3500000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
        ]);

        // Coba input tarif baru untuk prodi spesifik pada angkatan & biaya yang sama -> Wajib ditolak 422
        $resProdi = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2027,
                'program_studi_id' => $prodi->id,
                'nominal' => 3600000,
                'is_active' => true,
            ]);

        $resProdi->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Komponen biaya ini sudah disetting aktif untuk Semua Program Studi pada angkatan ini. Tidak perlu menginputkan tarif lagi.',
            ]);

        // Coba input lagi untuk Semua Program Studi -> Wajib ditolak 422
        $resGlobal = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2027,
                'program_studi_id' => null,
                'nominal' => 3700000,
                'is_active' => true,
            ]);

        $resGlobal->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_tidak_bisa_input_tarif_global_jika_sudah_ada_tarif_prodi_aktif(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'LAB_TEST_2028'],
            ['nama' => 'Biaya Praktikum Khusus', 'tipe' => 'praktikum', 'nominal_standar' => 800000, 'is_active' => true]
        );

        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI02'],
            ['nama' => 'Teknik Informatika Khusus', 'jenjang' => 'S1', 'is_active' => true]
        );

        // Pasang tarif prodi aktif
        SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2028,
            'program_studi_id' => $prodi->id,
            'nominal' => 800000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
        ]);

        // Coba input tarif Semua Program Studi (Global) padahal sudah ada tarif prodi -> Wajib ditolak 422
        $resGlobal = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2028,
                'program_studi_id' => null,
                'nominal' => 750000,
                'is_active' => true,
            ]);

        $resGlobal->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Sudah terdapat tarif aktif spesifik per program studi untuk komponen biaya ini pada angkatan ini. Harap nonaktifkan tarif prodi terlebih dahulu jika ingin menerapkan satu tarif untuk Semua Program Studi.',
            ]);
    }

    public function test_get_tarif_dinamis_mahasiswa_sesuai_hierarki(): void
    {
        $prodiTI = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI99'],
            ['nama' => 'Teknik Komputer', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => '20290001'],
            [
                'nama_lengkap' => 'Budi Santoso Test',
                'program_studi_id' => $prodiTI->id,
                'angkatan' => 2029,
                'status' => 'aktif',
            ]
        );

        $biayaSPP = MasterBiaya::firstOrCreate(
            ['kode' => 'SPP_2029'],
            ['nama' => 'SPP Semester Angkatan 2029', 'tipe' => 'spp', 'nominal_standar' => 3000000, 'is_active' => true]
        );

        $biayaLab = MasterBiaya::firstOrCreate(
            ['kode' => 'LAB_2029'],
            ['nama' => 'Praktikum Lab Komputer 2029', 'tipe' => 'praktikum', 'nominal_standar' => 600000, 'is_active' => true]
        );

        // 1. SPP disetting Global (Semua Prodi) = Rp 3.200.000
        SettingTarif::create([
            'master_biaya_id' => $biayaSPP->id,
            'tahun_angkatan' => 2029,
            'program_studi_id' => null,
            'nominal' => 3200000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
        ]);

        // 2. Praktikum disetting spesifik Prodi TI = Rp 650.000
        SettingTarif::create([
            'master_biaya_id' => $biayaLab->id,
            'tahun_angkatan' => 2029,
            'program_studi_id' => $prodiTI->id,
            'nominal' => 650000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
        ]);

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa?mahasiswa_id={$mhs->id}");

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.mahasiswa.nim', '20290001')
            ->assertJsonPath('data.tahun_angkatan', 2029);

        $items = collect($res->json('data.komponen_tarif'));
        $sppItem = $items->firstWhere('kode', 'SPP_2029');
        $labItem = $items->firstWhere('kode', 'LAB_2029');

        $this->assertNotNull($sppItem);
        $this->assertEquals(3200000, $sppItem['nominal']);
        $this->assertEquals('global_kampus', $sppItem['cakupan']);

        $this->assertNotNull($labItem);
        $this->assertEquals(650000, $labItem['nominal']);
        $this->assertEquals('spesifik_prodi', $labItem['cakupan']);
    }

    public function test_store_tagihan_mahasiswa_dengan_komponen_dinamis(): void
    {
        $prodiTI = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI99'],
            ['nama' => 'Teknik Komputer', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => '20290002'],
            ['nama_lengkap' => 'Siti Nurhaliza', 'program_studi_id' => $prodiTI->id, 'angkatan' => 2029, 'status' => 'aktif']
        );

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SPP_BILL_TEST'],
            ['nama' => 'SPP Tagihan Test', 'tipe' => 'spp', 'nominal_standar' => 3000000, 'is_active' => true]
        );

        $payload = [
            'mahasiswa_id' => $mhs->id,
            'semester' => 1,
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
            'catatan' => 'Tagihan Semester 1',
            'mode_pembayaran' => 'terbitkan_tagihan',
            'items' => [
                [
                    'master_biaya_id' => $biaya->id,
                    'nominal' => 3000000,
                    'keterangan' => 'Tagihan SPP',
                ],
            ],
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'belum_bayar',
                    'total_tagihan' => 3000000,
                    'va_number' => null,
                ]
            ]);

        $this->assertNull($res->json('data.va_number'));
        $this->assertDatabaseMissing('sikeu_virtual_account', [
            'tagihan_id' => $res->json('data.tagihan.id'),
        ]);

        // Jurnal akrual penerbitan: Dr Piutang 103.01 / Cr Pendapatan 401.01
        $jurnal = \App\Models\Sikeu\JurnalUmum::where('jenis_sumber', 'pembayaran_mahasiswa')
            ->where('referensi_id', $res->json('data.tagihan.id'))
            ->where('nomor_jurnal', 'like', 'JRN-TAG-%')
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(3000000, (float) $jurnal->total_debet);
        $akunIds = \App\Models\Sikeu\DetailJurnalUmum::where('jurnal_id', $jurnal->id)->pluck('kredit', 'akun_id');
        $piutangId = \App\Models\Sikeu\AkunKeuangan::where('kode_akun', '103.01')->first()->id;
        $pendapatanId = \App\Models\Sikeu\AkunKeuangan::where('kode_akun', '401.01')->first()->id;
        $debet = \App\Models\Sikeu\DetailJurnalUmum::where('jurnal_id', $jurnal->id)->pluck('debet', 'akun_id');
        $this->assertEquals(3000000, (float) ($debet[$piutangId] ?? 0));
        $this->assertEquals(3000000, (float) ($akunIds[$pendapatanId] ?? 0));
    }

    public function test_store_tagihan_mahasiswa_mode_bayar_langsung_kasir_lunas(): void
    {
        $prodiTI = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI99'],
            ['nama' => 'Teknik Komputer', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => '20290003'],
            ['nama_lengkap' => 'Ahmad Kasir', 'program_studi_id' => $prodiTI->id, 'angkatan' => 2029, 'status' => 'aktif']
        );

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'DPP_CASHIER'],
            ['nama' => 'DPP Gedung', 'tipe' => 'lainnya', 'nominal_standar' => 2500000, 'is_active' => true]
        );

        $payload = [
            'mahasiswa_id' => $mhs->id,
            'semester' => 1,
            'jatuh_tempo' => date('Y-m-d'),
            'catatan' => 'Bayar tunai di loket kasir',
            'mode_pembayaran' => 'bayar_loket_tunai',
            'jumlah_bayar' => 2500000,
            'items' => [
                [
                    'master_biaya_id' => $biaya->id,
                    'nominal' => 2500000,
                    'keterangan' => 'DPP',
                ],
            ],
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'lunas',
                    'total_tagihan' => 2500000,
                ]
            ]);

        $this->assertNotNull($res->json('data.pembayaran'));
        $this->assertEquals('success', $res->json('data.pembayaran.status'));
    }

    public function test_index_dan_filter_tarif(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'PRAK_TEST'],
            ['nama' => 'Biaya Praktikum Laboratorium', 'tipe' => 'lainnya', 'nominal_standar' => 750000, 'is_active' => true]
        );

        SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'program_studi_id' => null,
            'nominal' => 750000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
            'keterangan' => 'Praktikum Komputer',
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif?search=Praktikum');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_update_dan_destroy_tarif(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'WIS_TEST'],
            ['nama' => 'Biaya Wisuda & Ijazah', 'tipe' => 'lainnya', 'nominal_standar' => 1750000, 'is_active' => true]
        );

        $tarif = SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2022,
            'program_studi_id' => null,
            'nominal' => 1750000,
            'jalur_kelas' => 'Reguler',
            'is_active' => true,
            'keterangan' => 'Biaya Toga & Ijazah',
        ]);

        // Update tarif
        $resUpdate = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif/{$tarif->id}", [
                'nominal' => 1850000,
                'keterangan' => 'Biaya Toga & Ijazah Revisi',
            ]);

        $resUpdate->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_setting_tarif', [
            'id' => $tarif->id,
            'nominal' => 1850000,
        ]);

        // Delete tarif
        $resDelete = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif/{$tarif->id}");

        $resDelete->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_setting_tarif', [
            'id' => $tarif->id,
        ]);
    }

    public function test_katalog_biaya_hanya_mengembalikan_skema_dinamis(): void
    {
        MasterBiaya::create([
            'kode' => 'FLAT_TEST',
            'nama' => 'Biaya Flat Non-Dinamis',
            'tipe' => 'wisuda',
            'skema_tarif' => 'flat',
            'nominal_standar' => 500000,
            'is_active' => true,
        ]);

        MasterBiaya::create([
            'kode' => 'DINAMIS_TEST',
            'nama' => 'Biaya Kuliah Dinamis',
            'tipe' => 'spp',
            'skema_tarif' => 'dinamis',
            'nominal_standar' => 0,
            'is_active' => true,
        ]);

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/katalog-biaya');

        $res->assertStatus(200);
        $katalog = collect($res->json('data'));

        $this->assertTrue($katalog->contains('kode', 'DINAMIS_TEST'));
        $this->assertFalse($katalog->contains('kode', 'FLAT_TEST'));
    }

    public function test_index_tagihan_sukses_tanpa_error_500(): void
    {
        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan');

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_preview_dan_generate_mass_tagihan(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs1 = Mahasiswa::create([
            'nim' => 'TEST2023001',
            'nama_lengkap' => 'Mahasiswa Mass 1',
            'angkatan' => 2023,
            'program_studi_id' => $prodi->id,
            'status' => 'aktif',
        ]);

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'MASS_TEST_FEE'],
            ['nama' => 'Biaya Massal Test', 'tipe' => 'spp', 'skema_tarif' => 'dinamis', 'nominal_standar' => 2000000, 'is_active' => true]
        );

        SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2023,
            'program_studi_id' => null, // Global
            'nominal' => 2500000,
            'is_active' => true,
        ]);

        // 1. Test Preview Mass Tagihan
        $resPreview = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan/preview?tahun_angkatan=2023');

        $resPreview->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.tahun_angkatan', 2023);

        $this->assertGreaterThanOrEqual(1, $resPreview->json('data.total_mahasiswa'));

        // 2. Test Store Mass Tagihan
        $resStore = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan', [
                'tahun_angkatan' => 2023,
                'semester' => 3,
                'jatuh_tempo' => now()->addMonth()->format('Y-m-d'),
                'catatan' => 'Tagihan Massal Angkatan 2023',
                'items' => [
                    [
                        'master_biaya_id' => $biaya->id,
                        'nominal' => 2500000,
                    ],
                ],
            ]);

        $resStore->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertGreaterThanOrEqual(1, $resStore->json('data.created_count'));

        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'mahasiswa_id' => $mhs1->id,
            'status' => 'belum_bayar',
            'source_system' => 'sikeu_pembayaran_mahasiswa_massal',
        ]);
    }

    public function test_delete_tagihan_satuan(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'TEST_DEL_01'],
            ['nama_lengkap' => 'Mahasiswa Delete Test', 'angkatan' => 2026, 'program_studi_id' => $prodi->id, 'status' => 'aktif']
        );

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-TEST-DEL-1',
            'total_tagihan' => 1000000,
            'status' => 'belum_bayar',
            'tahun_akademik_id' => 1,
            'source_system' => 'sikeu_test',
        ]);

        $res = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/pembayaran-mahasiswa/tagihan/{$tagihan->id}");

        $res->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_tagihan_mahasiswa', ['id' => $tagihan->id]);
    }

    public function test_batch_delete_tagihan(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'TEST_BATCH_DEL'],
            ['nama_lengkap' => 'Mahasiswa Batch Test', 'angkatan' => 2026, 'program_studi_id' => $prodi->id, 'status' => 'aktif']
        );

        $t1 = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-BATCH-1',
            'total_tagihan' => 500000,
            'status' => 'belum_bayar',
            'tahun_akademik_id' => 1,
            'source_system' => 'sikeu_test',
        ]);

        $t2 = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-BATCH-2',
            'total_tagihan' => 750000,
            'status' => 'belum_bayar',
            'tahun_akademik_id' => 1,
            'source_system' => 'sikeu_test',
        ]);

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan/batch-delete', [
                'tagihan_ids' => [$t1->id, $t2->id],
            ]);

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('sikeu_tagihan_mahasiswa', ['id' => $t1->id]);
        $this->assertDatabaseMissing('sikeu_tagihan_mahasiswa', ['id' => $t2->id]);
    }

    public function test_tarif_mahasiswa_returns_empty_when_angkatan_not_configured_in_setting_tarif(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        // Angkatan 2099 belum pernah disetting di SettingTarif
        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'TEST_ANGK_2099'],
            ['nama_lengkap' => 'Mahasiswa Belum Setting Tarif', 'angkatan' => 2099, 'program_studi_id' => $prodi->id, 'status' => 'aktif']
        );

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa?mahasiswa_id={$mhs->id}");

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.tahun_angkatan', 2099)
            ->assertJsonCount(0, 'data.komponen_tarif');
    }

    public function test_filter_range_date_jatuh_tempo_tagihan(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'TEST_JT_FILTER'],
            ['nama_lengkap' => 'Mahasiswa JT Filter', 'angkatan' => 2026, 'program_studi_id' => $prodi->id, 'status' => 'aktif']
        );

        // Tagihan 1: Jatuh tempo 2026-10-15
        $t1 = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-JT-01-' . uniqid(),
            'total_tagihan' => 1000000,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => '2026-10-15',
        ]);

        // Tagihan 2: Jatuh tempo 2026-11-20
        $t2 = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-JT-02-' . uniqid(),
            'total_tagihan' => 1500000,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => '2026-11-20',
        ]);

        // Filter range mencakup hanya t1
        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/pembayaran-mahasiswa/tagihan?jatuh_tempo_dari=2026-10-01&jatuh_tempo_sampai=2026-10-31");

        $res->assertStatus(200);
        $ids = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($t1->id, $ids);
        $this->assertNotContains($t2->id, $ids);
    }

    public function test_alihkan_pembayaran_antar_tagihan_mahasiswa_yang_sama(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'INF-TEST'],
            ['nama' => 'Informatika Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'TEST_ALIKH_01'],
            ['nama_lengkap' => 'Mahasiswa Alih Dana', 'angkatan' => 2026, 'program_studi_id' => $prodi->id, 'status' => 'aktif']
        );

        // Tagihan 1 (Sumber): Awalnya bayar 3.000.000, lalu dapat beasiswa atau kelebihan bayar
        $source = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-SRC-' . uniqid(),
            'total_tagihan' => 3000000,
            'total_potongan' => 2000000, // Misal dapat beasiswa 2jt susulan
            'total_bayar' => 3000000,    // Sebelumnya sudah bayar full 3jt
            'status' => 'lunas',
            'jatuh_tempo' => '2026-10-01',
        ]);

        // Tagihan 2 (Target): Tagihan semester berikutnya 2.500.000 belum bayar
        $target = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-TGT-' . uniqid(),
            'total_tagihan' => 2500000,
            'total_potongan' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => '2026-11-01',
        ]);

        // Alihkan kelebihan bayar 2.000.000 dari source ke target
        $payload = [
            'source_tagihan_id' => $source->id,
            'target_tagihan_id' => $target->id,
            'nominal' => 2000000,
            'alasan' => 'Pengalihan kelebihan pembayaran karena memperoleh beasiswa',
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/alihkan-pembayaran', $payload);

        $res->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $source->refresh();
        $target->refresh();

        // Source bayar berkurang dari 3jt jadi 1jt (pas dengan net tagihan 3jt - 2jt = 1jt)
        $this->assertEquals(1000000, (float)$source->total_bayar);
        $this->assertEquals('lunas', $source->status);

        // Target bayar bertambah 2jt (sebagian dari 2.5jt)
        $this->assertEquals(2000000, (float)$target->total_bayar);
        $this->assertEquals('sebagian', $target->status);

        // Record pembayaran tercatat pada target
        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id' => $target->id,
            'channel_bayar' => 'PENGALIHAN_DANA',
            'jumlah_bayar' => 2000000,
        ]);
    }

    public function test_tidak_bisa_terbitkan_tagihan_ganda_semester_sama(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'DUP-TEST'],
            ['nama' => 'Prodi Duplikat Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'DUP2026001'],
            ['nama_lengkap' => 'Mahasiswa Duplikat', 'program_studi_id' => $prodi->id, 'angkatan' => 2026, 'status' => 'aktif']
        );

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'DUP_FEE'],
            ['nama' => 'Biaya Duplikat Test', 'tipe' => 'spp', 'nominal_standar' => 1000000, 'is_active' => true]
        );

        $payload = [
            'mahasiswa_id' => $mhs->id,
            'semester' => 1,
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
            'mode_pembayaran' => 'terbitkan_tagihan',
            'items' => [
                ['master_biaya_id' => $biaya->id, 'nominal' => 1000000],
            ],
        ];

        $first = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan', $payload);
        $first->assertStatus(201)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'mahasiswa_id' => $mhs->id,
            'semester' => 1,
        ]);

        // Tagihan kedua semester sama wajib ditolak walau tagihan pertama belum dibayar
        $second = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan', $payload);
        $second->assertStatus(422)->assertJson(['status' => 'error']);

        // Semester berbeda tetap boleh
        $payload['semester'] = 2;
        $third = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tagihan', $payload);
        $third->assertStatus(201)->assertJson(['status' => 'success']);
    }

    public function test_mass_tagihan_melewati_mahasiswa_yang_sudah_ditagih(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'SKIP-TEST'],
            ['nama' => 'Prodi Skip Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::create([
            'nim' => 'SKIP2026001',
            'nama_lengkap' => 'Mahasiswa Skip',
            'angkatan' => 2026,
            'program_studi_id' => $prodi->id,
            'status' => 'aktif',
        ]);

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SKIP_FEE'],
            ['nama' => 'Biaya Skip Test', 'tipe' => 'spp', 'skema_tarif' => 'dinamis', 'nominal_standar' => 2000000, 'is_active' => true]
        );

        SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'program_studi_id' => null,
            'nominal' => 2000000,
            'is_active' => true,
        ]);

        $massPayload = [
            'tahun_angkatan' => 2026,
            'program_studi_id' => $prodi->id,
            'semester' => 1,
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
            'items' => [['master_biaya_id' => $biaya->id]],
        ];

        $run1 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan', $massPayload);
        $run1->assertStatus(201)->assertJsonPath('data.created_count', 1);

        // Generate ulang periode sama: tidak boleh duplikat, mahasiswa dilewati
        $run2 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan', $massPayload);
        $run2->assertStatus(201)
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertEquals(1, TagihanMahasiswa::where('mahasiswa_id', $mhs->id)->where('semester', 1)->count());
    }

    public function test_destroy_tarif_hanya_terblokir_oleh_tagihan_angkatan_sama(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SCOPE_FEE'],
            ['nama' => 'Biaya Scope Test', 'tipe' => 'spp', 'nominal_standar' => 1000000, 'is_active' => true]
        );

        $tarifLama = SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2023,
            'program_studi_id' => null,
            'nominal' => 900000,
            'is_active' => true,
        ]);

        $tarifBaru = SettingTarif::create([
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'program_studi_id' => null,
            'nominal' => 1000000,
            'is_active' => true,
        ]);

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'SCOPE2026001'],
            ['nama_lengkap' => 'Mahasiswa Scope', 'program_studi_id' => MasterProgramStudi::firstOrCreate(['kode_prodi' => 'SCOPE-PRODI'], ['nama' => 'Prodi Scope', 'jenjang' => 'S1', 'is_active' => true])->id, 'angkatan' => 2026, 'status' => 'aktif']
        );

        TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'tipe_referensi' => 'mahasiswa',
            'tahun_akademik_id' => 1,
            'semester' => 1,
            'nomor_tagihan' => 'INV-SCOPE-001',
            'total_tagihan' => 1000000,
            'status' => 'belum_bayar',
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
        ]);
        DetailTagihan::create([
            'tagihan_id' => TagihanMahasiswa::where('nomor_tagihan', 'INV-SCOPE-001')->first()->id,
            'master_biaya_id' => $biaya->id,
            'nominal' => 1000000,
            'potongan' => 0,
            'nominal_bersih' => 1000000,
        ]);

        // Tarif angkatan 2023 TIDAK dipakai tagihan angkatan 2026: boleh dihapus
        $resLama = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif/{$tarifLama->id}");
        $resLama->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('sikeu_setting_tarif', ['id' => $tarifLama->id]);

        // Tarif angkatan 2026 yang dipakai: tetap diproteksi
        $resBaru = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif/{$tarifBaru->id}");
        $resBaru->assertStatus(422)->assertJson(['status' => 'error']);
        $this->assertDatabaseHas('sikeu_setting_tarif', ['id' => $tarifBaru->id]);
    }

    public function test_store_tarif_semester_spesifik_dan_universal(): void
    {
        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'LAB_SEM_TEST'],
            ['nama' => 'Biaya Lab Semester Test', 'tipe' => 'praktikum', 'nominal_standar' => 500000, 'is_active' => true]
        );

        // Tarif khusus semester 3
        $res3 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2026,
                'semester' => 3,
                'nominal' => 500000,
            ]);
        $res3->assertStatus(201)->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('sikeu_setting_tarif', [
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2026,
            'semester' => 3,
        ]);

        // Semester 5 untuk komponen sama: boleh (cakupan berbeda)
        $res5 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2026,
                'semester' => 5,
                'nominal' => 600000,
            ]);
        $res5->assertStatus(201);

        // Duplikat semester 3: ditolak
        $resDup = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2026,
                'semester' => 3,
                'nominal' => 550000,
            ]);
        $resDup->assertStatus(422)->assertJson(['status' => 'error']);

        // Tarif universal (semua semester) bertabrakan dengan semester 3 yang aktif: ditolak
        $resAll = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', [
                'master_biaya_id' => $biaya->id,
                'tahun_angkatan' => 2026,
                'nominal' => 500000,
            ]);
        $resAll->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_tarif_mahasiswa_hanya_kembalikan_komponen_sesuai_semester(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'SEM-TEST'],
            ['nama' => 'Prodi Semester Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'SEM2026001'],
            ['nama_lengkap' => 'Mhs Semester', 'program_studi_id' => $prodi->id, 'angkatan' => 2026, 'status' => 'aktif']
        );

        $biayaUmum = MasterBiaya::firstOrCreate(
            ['kode' => 'SEM_UMUM'],
            ['nama' => 'Biaya Umum Test', 'tipe' => 'spp', 'skema_tarif' => 'dinamis', 'nominal_standar' => 1000000, 'is_active' => true]
        );
        $biayaLab = MasterBiaya::firstOrCreate(
            ['kode' => 'SEM_LAB'],
            ['nama' => 'Biaya Lab Test', 'tipe' => 'praktikum', 'skema_tarif' => 'dinamis', 'nominal_standar' => 500000, 'is_active' => true]
        );

        SettingTarif::create([
            'master_biaya_id' => $biayaUmum->id, 'tahun_angkatan' => 2026,
            'program_studi_id' => null, 'semester' => null, 'nominal' => 1000000, 'is_active' => true,
        ]);
        SettingTarif::create([
            'master_biaya_id' => $biayaLab->id, 'tahun_angkatan' => 2026,
            'program_studi_id' => null, 'semester' => 3, 'nominal' => 500000, 'is_active' => true,
        ]);

        // Menagih semester 1: hanya biaya umum
        $res1 = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa?mahasiswa_id={$mhs->id}&semester=1");
        $res1->assertStatus(200);
        $kodes1 = collect($res1->json('data.komponen_tarif'))->pluck('kode')->all();
        $this->assertContains('SEM_UMUM', $kodes1);
        $this->assertNotContains('SEM_LAB', $kodes1);

        // Menagih semester 3: keduanya ikut
        $res3 = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/pembayaran-mahasiswa/tarif-mahasiswa?mahasiswa_id={$mhs->id}&semester=3");
        $res3->assertStatus(200);
        $kodes3 = collect($res3->json('data.komponen_tarif'))->pluck('kode')->all();
        $this->assertContains('SEM_UMUM', $kodes3);
        $this->assertContains('SEM_LAB', $kodes3);
    }

    public function test_preview_massal_saring_semester_dan_tandai_sudah_ditagih(): void
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'PREV-TEST'],
            ['nama' => 'Prodi Preview Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => 'PREV2026001'],
            ['nama_lengkap' => 'Mhs Preview', 'program_studi_id' => $prodi->id, 'angkatan' => 2026, 'status' => 'aktif']
        );

        $biayaUmum = MasterBiaya::firstOrCreate(
            ['kode' => 'PREV_UMUM'],
            ['nama' => 'Biaya Umum Preview', 'tipe' => 'spp', 'skema_tarif' => 'dinamis', 'nominal_standar' => 1000000, 'is_active' => true]
        );
        $biayaLab = MasterBiaya::firstOrCreate(
            ['kode' => 'PREV_LAB'],
            ['nama' => 'Biaya Lab Preview', 'tipe' => 'praktikum', 'skema_tarif' => 'dinamis', 'nominal_standar' => 750000, 'is_active' => true]
        );

        SettingTarif::create([
            'master_biaya_id' => $biayaUmum->id, 'tahun_angkatan' => 2026,
            'program_studi_id' => null, 'semester' => null, 'nominal' => 1000000, 'is_active' => true,
        ]);
        SettingTarif::create([
            'master_biaya_id' => $biayaLab->id, 'tahun_angkatan' => 2026,
            'program_studi_id' => null, 'semester' => 3, 'nominal' => 750000, 'is_active' => true,
        ]);

        // Preview semester 1: lab tidak ikut, komponen terpakai hanya umum
        $res1 = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan/preview?tahun_angkatan=2026&semester=1');
        $res1->assertStatus(200);
        $terpakai1 = collect($res1->json('data.komponen_terpakai'))->pluck('kode')->all();
        $this->assertContains('PREV_UMUM', $terpakai1);
        $this->assertNotContains('PREV_LAB', $terpakai1);
        $this->assertEquals(0, $res1->json('data.sudah_ditagih_count'));

        // Terbitkan semester 1 untuk mahasiswa ini
        TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id, 'tipe_referensi' => 'mahasiswa',
            'tahun_akademik_id' => 1, 'semester' => 1,
            'nomor_tagihan' => 'INV-PREV-001', 'total_tagihan' => 1000000,
            'status' => 'belum_bayar', 'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
        ]);

        // Preview ulang: mahasiswa bertanda sudah ditagih, estimasi tidak menghitungnya
        $res2 = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/mass-tagihan/preview?tahun_angkatan=2026&semester=1');
        $res2->assertStatus(200);
        $this->assertEquals(1, $res2->json('data.sudah_ditagih_count'));
        $this->assertEquals(0, $res2->json('data.akan_diterbitkan_count'));
        $row = collect($res2->json('data.mahasiswa'))->firstWhere('id', $mhs->id);
        $this->assertTrue((bool) $row['sudah_ditagih']);
        $this->assertEquals('INV-PREV-001', $row['nomor_tagihan']);
    }
}
