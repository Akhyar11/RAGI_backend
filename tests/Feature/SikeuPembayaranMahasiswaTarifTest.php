<?php

namespace Tests\Feature;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\SettingTarif;
use App\Models\Sikeu\TagihanMahasiswa;
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

    public function test_store_multi_tarif_untuk_komponen_biaya_sama_dengan_prodi_dan_angkatan_berbeda(): void
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

        // 3. Variasi Tarif Ketiga: SPP untuk Semua Prodi (Global) Angkatan 2025: Rp 4.000.000
        $payloadGlobal = [
            'master_biaya_id' => $biaya->id,
            'tahun_angkatan' => 2025,
            'program_studi_id' => null, // Berlaku semua prodi
            'nominal' => 4000000,
            'keterangan' => 'Tarif SPP Semua Prodi Angkatan 2025',
            'is_active' => true,
        ];

        $res3 = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', $payloadGlobal);

        $res3->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'master_biaya_id' => $biaya->id,
                    'tahun_angkatan' => 2025,
                    'program_studi_id' => null,
                    'nominal' => 4000000,
                ]
            ]);

        // 4. Verifikasi kombinasi duplikat ditolak (422)
        $resDuplikat = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/pembayaran-mahasiswa/tarif', $payloadTI);

        $resDuplikat->assertStatus(422)
            ->assertJson(['status' => 'error']);
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
}
