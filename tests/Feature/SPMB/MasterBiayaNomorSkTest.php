<?php

namespace Tests\Feature\SPMB;

use App\Models\Arsip\NomorSurat;
use App\Models\Arsip\RequestNomorSurat;
use App\Models\MasterTipeJalur;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MasterBiayaNomorSkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private MasterTipeJalur $tipeJalur;
    private MasterProgramStudi $prodi;
    private MasterKomponenBiaya $komponen;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create([
            'username' => 'admin_spmb_arsip',
            'email' => 'admin_spmb_arsip@kampus.ac.id',
        ]);
        Passport::actingAs($this->user);

        $this->tipeJalur = MasterTipeJalur::create([
            'kode' => 'REG',
            'nama' => 'Jalur Reguler',
            'is_active' => true,
        ]);

        $this->prodi = MasterProgramStudi::create([
            'kode_prodi' => 'TI-01',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
        ]);

        $this->komponen = MasterKomponenBiaya::create([
            'kode' => 'UKT-01',
            'nama' => 'Uang Kuliah Tunggal',
            'kategori' => 'daftar_ulang',
            'is_active' => true,
        ]);
    }

    public function test_check_sk_returns_not_found(): void
    {
        $res = $this->getJson('/api/arsip/nomor-surat/check?nomor_surat=SK-TIDAK-ADA/2026');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_found' => false,
                    'is_valid' => false,
                ],
            ]);
    }

    public function test_check_sk_fails_when_document_is_empty(): void
    {
        $surat = NomorSurat::create([
            'nomor_surat' => '10/SK/YAY/X/2026',
            'nomor_urut' => 10,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Penetapan Biaya Kuliah Mahasiswa Baru',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => null,
        ]);

        $res = $this->getJson('/api/arsip/nomor-surat/check?nomor_surat=10/SK/YAY/X/2026');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_found' => true,
                    'is_valid' => false,
                    'has_document' => false,
                ],
            ]);
    }

    public function test_check_sk_succeeds_when_document_exists(): void
    {
        $file = UploadedFile::fake()->create('sk_biaya.pdf', 100, 'application/pdf');
        $storedPath = app(FileStorageService::class)->store($file, 'arsip/dokumen', private: true);

        $surat = NomorSurat::create([
            'nomor_surat' => '11/SK/YAY/X/2026',
            'nomor_urut' => 11,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Penetapan Biaya Kuliah Mahasiswa Baru',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => $storedPath,
        ]);

        $res = $this->getJson('/api/arsip/nomor-surat/check?nomor_surat=11/SK/YAY/X/2026');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_surat' => '11/SK/YAY/X/2026',
                    'is_found' => true,
                    'is_valid' => true,
                    'has_document' => true,
                ],
            ]);

        $this->assertNotNull($res->json('data.dokumen_url'));
    }

    public function test_check_sk_succeeds_via_request_dokumen_lampiran(): void
    {
        $file = UploadedFile::fake()->create('lampiran_sk.pdf', 100, 'application/pdf');
        $storedPath = app(FileStorageService::class)->store($file, 'arsip/lampiran', private: true);

        $request = RequestNomorSurat::create([
            'kode_request' => 'REQ-SK-001',
            'module_origin' => 'spmb',
            'user_id' => $this->user->id,
            'perihal' => 'Permohonan SK Biaya',
            'tanggal_surat' => '2026-10-01',
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'jumlah_nomor' => 1,
            'dokumen_lampiran_path' => $storedPath,
            'status' => 'disetujui',
        ]);

        $surat = NomorSurat::create([
            'nomor_surat' => '12/SK/YAY/X/2026',
            'nomor_urut' => 12,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Biaya dari Request',
            'status' => 'terpakai',
            'request_id' => $request->id,
            'created_by' => $this->user->id,
            'file_path' => null,
        ]);

        $res = $this->getJson('/api/arsip/nomor-surat/check?nomor_surat=12/SK/YAY/X/2026');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_surat' => '12/SK/YAY/X/2026',
                    'is_found' => true,
                    'is_valid' => true,
                    'has_document' => true,
                ],
            ]);
    }

    public function test_create_master_biaya_with_invalid_sk_rejected(): void
    {
        $payload = [
            'master_tipe_jalur_id' => $this->tipeJalur->id,
            'program_studi_id' => $this->prodi->id,
            'nomor_sk' => 'SK-ASAL-ASALAN/999',
            'is_active' => true,
            'items' => [
                [
                    'komponen_biaya_id' => $this->komponen->id,
                    'nominal' => 5000000,
                    'dibebankan_saat_pendaftaran' => false,
                    'berlaku_diskon' => true,
                ],
            ],
        ];

        $res = $this->postJson('/api/spmb/master/biaya', $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_create_master_biaya_with_valid_sk_saved_successfully(): void
    {
        $file = UploadedFile::fake()->create('sk_sah.pdf', 100, 'application/pdf');
        $storedPath = app(FileStorageService::class)->store($file, 'arsip/dokumen', private: true);

        $surat = NomorSurat::create([
            'nomor_surat' => '15/SK/YAY/X/2026',
            'nomor_urut' => 15,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Biaya Sah',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => $storedPath,
        ]);

        $payload = [
            'master_tipe_jalur_id' => $this->tipeJalur->id,
            'program_studi_id' => $this->prodi->id,
            'nomor_sk' => '15/SK/YAY/X/2026',
            'is_active' => true,
            'items' => [
                [
                    'komponen_biaya_id' => $this->komponen->id,
                    'nominal' => 7500000,
                    'dibebankan_saat_pendaftaran' => false,
                    'berlaku_diskon' => true,
                ],
            ],
        ];

        $res = $this->postJson('/api/spmb/master/biaya', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_sk' => '15/SK/YAY/X/2026',
                ],
            ]);

        $this->assertDatabaseHas('spmb_master_biaya', [
            'master_tipe_jalur_id' => $this->tipeJalur->id,
            'program_studi_id' => $this->prodi->id,
            'nomor_sk' => '15/SK/YAY/X/2026',
        ]);
    }
}
