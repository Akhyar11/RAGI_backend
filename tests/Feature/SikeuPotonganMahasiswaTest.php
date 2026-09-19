<?php

namespace Tests\Feature;

use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\PotonganMahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuPotonganMahasiswaTest extends TestCase
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
            ['email' => 'admin.potongan@kampus.ac.id'],
            ['username' => 'potonganadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('potongan-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    protected function makeKomponen(string $kode, string $nama): MasterBiaya
    {
        return MasterBiaya::firstOrCreate(
            ['kode' => $kode],
            ['nama' => $nama, 'tipe' => 'spp', 'is_active' => true]
        );
    }

    public function test_store_potongan_dengan_tagihan_dan_semester_terpilih(): void
    {
        $biaya = $this->makeKomponen('SPP_TEST', 'SPP Kuliah');

        $payload = [
            'mahasiswa_id' => 991,
            'nim' => '20260001',
            'nama_mahasiswa' => 'Budi Santoso',
            'nama_potongan' => 'Diskon Prestasi',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 500000,
            'master_biaya_id' => $biaya->id,
            'semester' => 3,
            'status' => 'aktif',
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/potongan-mahasiswa', $payload);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_potongan_mahasiswa', [
            'mahasiswa_id' => 991,
            'master_biaya_id' => $biaya->id,
            'semester' => 3,
            'nilai_potongan' => 500000,
        ]);
    }

    public function test_store_potongan_dengan_tagihan_terpilih_dan_semester_belum_terpilih(): void
    {
        $biaya = $this->makeKomponen('UKT_TEST', 'UKT Pokok');

        // Kasus: pengguna memilih tagihan apa (master_biaya_id), namun semester belum dipilih (kosong / null)
        $payload = [
            'mahasiswa_id' => 992,
            'nim' => '20260002',
            'nama_mahasiswa' => 'Siti Aminah',
            'nama_potongan' => 'Keringanan UKT Rektor',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
            'master_biaya_id' => $biaya->id,
            'semester' => null, // belum terpilih
            'status' => 'aktif',
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/potongan-mahasiswa', $payload);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_potongan_mahasiswa', [
            'mahasiswa_id' => 992,
            'master_biaya_id' => $biaya->id,
            'semester' => null,
            'nilai_potongan' => 25,
        ]);
    }

    public function test_store_potongan_dengan_string_kosong_pada_semester_dan_master_biaya(): void
    {
        // Kasus: client mengirim string kosong "" untuk semester dan master_biaya_id
        $payload = [
            'mahasiswa_id' => 993,
            'nim' => '20260003',
            'nama_mahasiswa' => 'Rian Hidayat',
            'nama_potongan' => 'Diskon Afirmasi',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 1000000,
            'master_biaya_id' => '',
            'semester' => '',
            'status' => 'aktif',
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/potongan-mahasiswa', $payload);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_potongan_mahasiswa', [
            'mahasiswa_id' => 993,
            'master_biaya_id' => null,
            'semester' => null,
        ]);
    }

    public function test_update_potongan_mengubah_semester_menjadi_null(): void
    {
        $biaya = $this->makeKomponen('DPP_TEST', 'DPP Gedung');

        $item = PotonganMahasiswa::create([
            'mahasiswa_id' => 994,
            'nim' => '20260004',
            'nama_mahasiswa' => 'Doni Saputra',
            'nama_potongan' => 'Diskon Saudara',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 750000,
            'master_biaya_id' => $biaya->id,
            'semester' => 2,
            'status' => 'aktif',
        ]);

        // Update semester to null (semua semester)
        $response = $this->withHeaders($this->headers())
            ->putJson("/api/v1/sikeu/master/potongan-mahasiswa/{$item->id}", [
                'semester' => null,
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_potongan_mahasiswa', [
            'id' => $item->id,
            'semester' => null,
            'master_biaya_id' => $biaya->id,
        ]);
    }

    public function test_destroy_potongan_mahasiswa(): void
    {
        $item = PotonganMahasiswa::create([
            'mahasiswa_id' => 995,
            'nim' => '20260005',
            'nama_mahasiswa' => 'Andi Wijaya',
            'nama_potongan' => 'Diskon Hapus',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 300000,
            'status' => 'aktif',
        ]);

        $response = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/master/potongan-mahasiswa/{$item->id}");

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_potongan_mahasiswa', [
            'id' => $item->id,
        ]);
    }

    public function test_store_potongan_otomatis_sync_ke_tagihan_belum_lunas_dan_update_va(): void
    {
        $biaya = $this->makeKomponen('BIAYA_SYNC', 'Biaya Sync Tagihan');

        $tagihan = \App\Models\Sikeu\TagihanMahasiswa::create([
            'mahasiswa_id' => 998,
            'nomor_tagihan' => 'INV-SYNC-998',
            'total_tagihan' => 2000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        \App\Models\Sikeu\DetailTagihan::create([
            'tagihan_id' => $tagihan->id,
            'master_biaya_id' => $biaya->id,
            'nominal' => 2000000,
            'potongan' => 0,
            'nominal_bersih' => 2000000,
        ]);

        $va = \App\Models\Sikeu\VirtualAccount::create([
            'tagihan_id' => $tagihan->id,
            'bank_kode' => 'BNI',
            'bank_nama' => 'Bank Negara Indonesia',
            'va_number' => '9881234567899980',
            'nominal' => 2000000,
            'status' => 'aktif',
            'expired_at' => now()->addDays(30),
        ]);

        $payload = [
            'mahasiswa_id' => 998,
            'nim' => '20260998',
            'nama_mahasiswa' => 'Mahasiswa Sync Test',
            'nama_potongan' => 'Keringanan UKT 500rb',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 500000,
            'status' => 'aktif',
            'sync_unpaid_bills' => true,
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/potongan-mahasiswa', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'synced_bills_count' => 1,
            ]);

        // Verifikasi database: record potongan_tagihan terbentuk
        $this->assertDatabaseHas('sikeu_potongan_tagihan', [
            'tagihan_id' => $tagihan->id,
            'nominal_potongan' => 500000,
        ]);

        // Verifikasi tagihan total_potongan terupdate
        $tagihan->refresh();
        $this->assertEquals(500000, (float)$tagihan->total_potongan);
        $this->assertEquals('belum_bayar', $tagihan->status);

        // Verifikasi nominal VA terpotong menjadi 1.500.000
        $va->refresh();
        $this->assertEquals(1500000, (float)$va->nominal);
        $this->assertEquals('aktif', $va->status);
    }

    public function test_store_potongan_melunasi_tagihan_dan_nonaktifkan_va(): void
    {
        $biaya = $this->makeKomponen('BIAYA_LUNAS', 'Biaya Lunas Full');

        $tagihan = \App\Models\Sikeu\TagihanMahasiswa::create([
            'mahasiswa_id' => 997,
            'nomor_tagihan' => 'INV-SYNC-997',
            'total_tagihan' => 1000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        $va = \App\Models\Sikeu\VirtualAccount::create([
            'tagihan_id' => $tagihan->id,
            'bank_kode' => 'MANDIRI',
            'bank_nama' => 'Bank Mandiri',
            'va_number' => '8881234567899970',
            'nominal' => 1000000,
            'status' => 'aktif',
            'expired_at' => now()->addDays(30),
        ]);

        $payload = [
            'mahasiswa_id' => 997,
            'nim' => '20260997',
            'nama_mahasiswa' => 'Mahasiswa Lunas Test',
            'nama_potongan' => 'Beasiswa Full 100 Persen',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 100,
            'tagihan_id' => $tagihan->id,
            'status' => 'aktif',
            'sync_unpaid_bills' => true,
        ];

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/potongan-mahasiswa', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'synced_bills_count' => 1,
            ]);

        // Tagihan menjadi lunas
        $tagihan->refresh();
        $this->assertEquals(1000000, (float)$tagihan->total_potongan);
        $this->assertEquals('lunas', $tagihan->status);

        // VA menjadi status dibayar/lunas karena sisa tagihan 0
        $va->refresh();
        $this->assertEquals(0, (float)$va->nominal);
        $this->assertEquals('dibayar', $va->status);
    }
}
