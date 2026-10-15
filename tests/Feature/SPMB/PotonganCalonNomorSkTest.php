<?php

namespace Tests\Feature\SPMB;

use App\Models\Arsip\NomorSurat;
use App\Models\MasterTipeJalur;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\PotonganCalon;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PotonganCalonNomorSkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PendaftaranCalonMhs $pendaftaran;
    private MasterKomponenBiaya $komponen;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Storage::fake('local');
        Storage::fake('public');

        // User ID 1 is superadmin by fallback in User::isSuperAdmin
        $this->user = User::factory()->create([
            'id' => 1,
            'username' => 'admin_spmb_potongan',
            'email' => 'admin_spmb_potongan@kampus.ac.id',
        ]);
        Passport::actingAs($this->user);

        $tipeJalur = MasterTipeJalur::create([
            'kode' => 'REG',
            'nama' => 'Jalur Reguler',
            'is_active' => true,
        ]);

        $jalur = JalurMasuk::create([
            'kode' => 'REG-01',
            'nama' => 'Reguler 2026',
            'is_active' => true,
        ]);

        $gelombang = GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id,
            'nama' => 'Gelombang 1',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-12-31',
            'biaya_pendaftaran' => 250000,
            'status' => 'aktif',
        ]);

        $prodi = MasterProgramStudi::create([
            'kode_prodi' => 'TI-01',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
        ]);

        $this->pendaftaran = PendaftaranCalonMhs::create([
            'gelombang_id' => $gelombang->id,
            'user_id' => $this->user->id,
            'program_studi_id' => $prodi->id,
            'no_pendaftaran' => 'REG-POT-'.uniqid(),
            'nama_lengkap' => 'Calon Mahasiswa Uji Potongan',
            'nik' => '3201123456789012',
            'status' => PendaftaranCalonMhs::STATUS_DRAFT,
            'status_pembayaran' => PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
        ]);

        $this->komponen = MasterKomponenBiaya::create([
            'kode' => 'DPI-01',
            'nama' => 'Dana Pengembangan Institusi',
            'kategori' => 'daftar_ulang',
            'is_active' => true,
        ]);
    }

    public function test_create_potongan_with_invalid_sk_rejected(): void
    {
        $payload = [
            'komponen_biaya_id' => $this->komponen->id,
            'nama_potongan' => 'Potongan Rektorat Khusus',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
            'tahap' => 'daftar_ulang',
            'nomor_sk' => 'SK-TIDAK-TERDAFTAR/999',
            'status' => 'aktif',
        ];

        $res = $this->postJson("/api/spmb/pendaftaran/{$this->pendaftaran->id}/potongan", $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_create_potongan_with_cancelled_sk_rejected(): void
    {
        NomorSurat::create([
            'nomor_surat' => '20/SK/BATAL/X/2026',
            'nomor_urut' => 20,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Potongan Batal',
            'status' => 'dibatalkan',
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'komponen_biaya_id' => $this->komponen->id,
            'nama_potongan' => 'Potongan Rektorat Khusus',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
            'tahap' => 'daftar_ulang',
            'nomor_sk' => '20/SK/BATAL/X/2026',
            'status' => 'aktif',
        ];

        $res = $this->postJson("/api/spmb/pendaftaran/{$this->pendaftaran->id}/potongan", $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_create_potongan_with_sk_without_document_rejected(): void
    {
        NomorSurat::create([
            'nomor_surat' => '21/SK/NODOC/X/2026',
            'nomor_urut' => 21,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Tanpa Dokumen Fisik',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => null,
        ]);

        $payload = [
            'komponen_biaya_id' => $this->komponen->id,
            'nama_potongan' => 'Potongan Rektorat Khusus',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
            'tahap' => 'daftar_ulang',
            'nomor_sk' => '21/SK/NODOC/X/2026',
            'status' => 'aktif',
        ];

        $res = $this->postJson("/api/spmb/pendaftaran/{$this->pendaftaran->id}/potongan", $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_create_potongan_with_valid_sk_and_document_succeeds(): void
    {
        $file = UploadedFile::fake()->create('sk_potongan.pdf', 100, 'application/pdf');
        $storedPath = app(FileStorageService::class)->store($file, 'arsip/dokumen', private: true);

        NomorSurat::create([
            'nomor_surat' => '22/SK/SAH/X/2026',
            'nomor_urut' => 22,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Pemberian Potongan Khusus Calon Mahasiswa',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => $storedPath,
        ]);

        $payload = [
            'komponen_biaya_id' => $this->komponen->id,
            'nama_potongan' => 'Potongan Rektorat 25%',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
            'tahap' => 'daftar_ulang',
            'nomor_sk' => '22/SK/SAH/X/2026',
            'status' => 'aktif',
        ];

        $res = $this->postJson("/api/spmb/pendaftaran/{$this->pendaftaran->id}/potongan", $payload);

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_sk' => '22/SK/SAH/X/2026',
                ],
            ]);

        $this->assertDatabaseHas('spmb_potongan_calon', [
            'pendaftaran_id' => $this->pendaftaran->id,
            'nomor_sk' => '22/SK/SAH/X/2026',
        ]);

        $this->assertNotNull($res->json('data.arsip_nomor_surat'));
        $this->assertTrue($res->json('data.arsip_nomor_surat.has_dokumen'));
    }

    public function test_create_potongan_without_sk_rejected(): void
    {
        $payload = [
            'komponen_biaya_id' => $this->komponen->id,
            'nama_potongan' => 'Potongan Bebas SK',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 500000,
            'tahap' => 'keduanya',
            'nomor_sk' => null,
            'status' => 'aktif',
        ];

        $res = $this->postJson("/api/spmb/pendaftaran/{$this->pendaftaran->id}/potongan", $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_update_potongan_with_invalid_sk_rejected(): void
    {
        $potongan = PotonganCalon::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'komponen_biaya_id' => $this->komponen->id,
            'nama_komponen' => $this->komponen->nama,
            'nama_potongan' => 'Potongan Awal',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 10,
            'tahap' => 'daftar_ulang',
            'status' => 'aktif',
        ]);

        $res = $this->putJson("/api/spmb/potongan-calon/{$potongan->id}", [
            'nomor_sk' => 'SK-TIDAK-VALID/888',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_update_potongan_with_valid_sk_succeeds(): void
    {
        $file = UploadedFile::fake()->create('sk_revisi.pdf', 100, 'application/pdf');
        $storedPath = app(FileStorageService::class)->store($file, 'arsip/dokumen', private: true);

        NomorSurat::create([
            'nomor_surat' => '23/SK/REVISI/X/2026',
            'nomor_urut' => 23,
            'kode_unit' => 'YAY',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Revisi Potongan',
            'status' => 'terpakai',
            'created_by' => $this->user->id,
            'file_path' => $storedPath,
        ]);

        $potongan = PotonganCalon::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'komponen_biaya_id' => $this->komponen->id,
            'nama_komponen' => $this->komponen->nama,
            'nama_potongan' => 'Potongan Awal',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 10,
            'tahap' => 'daftar_ulang',
            'status' => 'aktif',
        ]);

        $res = $this->putJson("/api/spmb/potongan-calon/{$potongan->id}", [
            'nomor_sk' => '23/SK/REVISI/X/2026',
            'nilai_potongan' => 20,
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_sk' => '23/SK/REVISI/X/2026',
                    'nilai_potongan' => '20.00',
                ],
            ]);

        $this->assertNotNull($res->json('data.arsip_nomor_surat'));
    }
}
