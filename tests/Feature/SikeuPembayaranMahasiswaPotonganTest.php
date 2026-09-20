<?php

namespace Tests\Feature;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\PotonganMahasiswa;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SikeuPembayaranMahasiswaPotonganTest extends TestCase
{
    protected User $admin;
    protected MasterProgramStudi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.keuangan@kampus.ac.id'],
            ['username' => 'adminkeuangan', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $this->prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'POTONG-TI'],
            ['nama' => 'Teknik Informatika Potongan', 'jenjang' => 'S1', 'is_active' => true]
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

    protected function createCalonMahasiswa(array $attributes = []): PendaftaranCalonMhs
    {
        $jalur = JalurMasuk::firstOrCreate(
            ['kode' => 'REG-POTONG-TEST'],
            ['nama' => 'Jalur Reguler Potong Test', 'tipe' => 'reguler', 'is_active' => true]
        );

        $gelombang = GelombangPenerimaan::firstOrCreate(
            ['nama' => 'Gelombang 1 Potong Test'],
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
            'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'Calon Mhs ' . uniqid(),
            'no_pendaftaran' => 'REG-' . rand(10000, 99999),
            'nik' => '3201' . rand(100000000000, 999999999999),
            'no_hp' => '0812' . rand(10000000, 99999999),
            'status' => 'verified',
        ];

        return PendaftaranCalonMhs::create(array_merge($defaults, $attributes));
    }

    public function test_get_potongan_list(): void
    {
        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/pembayaran-mahasiswa/potongan');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'summary' => ['total_potongan', 'total_aktif', 'total_nominal_terpotong', 'total_mahasiswa'],
            ]);
    }

    public function test_create_potongan_mahasiswa_siakad_with_target_bill(): void
    {
        $mhs = Mahasiswa::create([
            'nim' => '2023' . rand(1000, 9999),
            'nama_lengkap' => 'Budi Santoso Potongan',
            'program_studi_id' => $this->prodi->id,
            'angkatan' => 2023,
            'status' => 'aktif',
        ]);

        $biaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SPP_TEST_POT'],
            ['nama' => 'SPP Tetap Potongan Test', 'tipe' => 'spp', 'nominal_standar' => 3000000, 'is_active' => true]
        );

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-TEST-POT-' . Str::random(5),
            'total_tagihan' => 3000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        $va = VirtualAccount::create([
            'tagihan_id' => $tagihan->id,
            'va_number' => '88012' . rand(1000000000, 9999999999),
            'bank_kode' => 'BANK_KAMPUS',
            'nominal' => 3000000,
            'status' => 'aktif',
        ]);

        $payload = [
            'mahasiswa_id' => $mhs->id,
            'is_calon_mahasiswa' => false,
            'tipe_referensi' => 'mahasiswa',
            'nama_potongan' => 'Diskon Prestasi Akademik 1 Juta',
            'nomor_sk' => 'SK/REKTOR/2026/001',
            'keterangan' => 'Diskon IPK tinggi',
            'status' => 'aktif',
            'target_bills' => [
                [
                    'tagihan_id' => $tagihan->id,
                    'mode_potongan' => 'nominal',
                    'nominal_potongan' => 1000000,
                ],
            ],
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/potongan', $payload);

        $res->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.total_nominal_potongan', 1000000);

        // Verifikasi tagihan dan VA terupdate
        $tagihan->refresh();
        $this->assertEquals(1000000, (float)$tagihan->total_potongan);
        $this->assertEquals('belum_bayar', $tagihan->status);

        $va->refresh();
        $this->assertEquals(2000000, (float)$va->nominal);
    }

    public function test_create_potongan_spmb_calon_mahasiswa_with_full_discount(): void
    {
        $calon = $this->createCalonMahasiswa([
            'nama_lengkap' => 'Ani Lestari SPMB',
            'no_pendaftaran' => 'REG-SPMB-' . rand(10000, 99999),
        ]);

        $tagihan = TagihanMahasiswa::create([
            'calon_mahasiswa_id' => $calon->id,
            'tipe_referensi' => 'calon_mahasiswa',
            'nomor_tagihan' => 'INV-SPMB-POT-' . Str::random(5),
            'total_tagihan' => 1500000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(15),
        ]);

        $va = VirtualAccount::create([
            'tagihan_id' => $tagihan->id,
            'va_number' => '88012' . rand(1000000000, 9999999999),
            'bank_kode' => 'BANK_KAMPUS',
            'nominal' => 1500000,
            'status' => 'aktif',
        ]);

        $payload = [
            'calon_mahasiswa_id' => $calon->id,
            'is_calon_mahasiswa' => true,
            'tipe_referensi' => 'calon_mahasiswa',
            'nama_potongan' => 'Beasiswa Bebas Uang Gedung SPMB Gelombang 1',
            'nomor_sk' => 'SK/SPMB/2026/999',
            'status' => 'aktif',
            'target_bills' => [
                [
                    'tagihan_id' => $tagihan->id,
                    'mode_potongan' => 'seluruhnya',
                    'nominal_potongan' => 1500000,
                ],
            ],
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/potongan', $payload);

        $res->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.total_nominal_potongan', 1500000);

        // Verifikasi tagihan lunas dan VA dibayar
        $tagihan->refresh();
        $this->assertEquals(1500000, (float)$tagihan->total_potongan);
        $this->assertEquals('lunas', $tagihan->status);

        $va->refresh();
        $this->assertEquals(0, (float)$va->nominal);
        $this->assertEquals('dibayar', $va->status);
    }

    public function test_reject_discount_exceeding_bill_balance(): void
    {
        $mhs = Mahasiswa::create([
            'nim' => '2023' . rand(1000, 9999),
            'nama_lengkap' => 'Citra Dewi Validasi',
            'program_studi_id' => $this->prodi->id,
            'angkatan' => 2023,
            'status' => 'aktif',
        ]);

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-EXCEED-' . Str::random(5),
            'total_tagihan' => 500000,
            'total_potongan' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(10),
        ]);

        $payload = [
            'mahasiswa_id' => $mhs->id,
            'nama_potongan' => 'Potongan Berlebihan',
            'target_bills' => [
                [
                    'tagihan_id' => $tagihan->id,
                    'mode_potongan' => 'nominal',
                    'nominal_potongan' => 900000, // Melebihi sisa 500.000
                ],
            ],
        ];

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/potongan', $payload);

        $res->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_delete_potongan_reverts_bill_and_va(): void
    {
        $mhs = Mahasiswa::create([
            'nim' => '2023' . rand(1000, 9999),
            'nama_lengkap' => 'Dedi Suhendar Revert',
            'program_studi_id' => $this->prodi->id,
            'angkatan' => 2023,
            'status' => 'aktif',
        ]);

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'nomor_tagihan' => 'INV-REVERT-' . Str::random(5),
            'total_tagihan' => 2000000,
            'total_potongan' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(20),
        ]);

        $va = VirtualAccount::create([
            'tagihan_id' => $tagihan->id,
            'va_number' => '88012' . rand(1000000000, 9999999999),
            'bank_kode' => 'BANK_KAMPUS',
            'nominal' => 2000000,
            'status' => 'aktif',
        ]);

        // 1. Buat potongan
        $payload = [
            'mahasiswa_id' => $mhs->id,
            'nama_potongan' => 'Potongan Revert Test',
            'target_bills' => [
                [
                    'tagihan_id' => $tagihan->id,
                    'mode_potongan' => 'nominal',
                    'nominal_potongan' => 2000000,
                ],
            ],
        ];

        $resCreate = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/potongan', $payload);

        $resCreate->assertStatus(201);
        $potonganId = $resCreate->json('data.potongan.id');

        $tagihan->refresh();
        $this->assertEquals('lunas', $tagihan->status);

        // 2. Hapus potongan
        $resDelete = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/pembayaran-mahasiswa/potongan/{$potonganId}");

        $resDelete->assertStatus(200)
            ->assertJson(['status' => 'success']);

        // 3. Verifikasi saldo tagihan dan VA kembali
        $tagihan->refresh();
        $this->assertEquals(0, (float)$tagihan->total_potongan);
        $this->assertEquals('belum_bayar', $tagihan->status);

        $va->refresh();
        $this->assertEquals(2000000, (float)$va->nominal);
        $this->assertEquals('aktif', $va->status);
    }
}
