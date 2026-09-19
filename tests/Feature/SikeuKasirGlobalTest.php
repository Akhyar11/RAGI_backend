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
}
