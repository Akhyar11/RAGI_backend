<?php

namespace Tests\Unit\Services\SPMB;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\SIKEU\PembayaranSpmbService;
use App\Services\SPMB\PendaftaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PendaftaranServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PendaftaranService $pendaftaranService;
    protected $pembayaranServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock PembayaranSpmbService so we don't actually trigger HTTP calls to SIKEU during unit tests
        $this->pembayaranServiceMock = $this->createMock(PembayaranSpmbService::class);
        $this->pembayaranServiceMock->expects($this->any())
                                    ->method('generateTagihanPendaftaran')
                                    ->willReturn(new \App\Models\Spmb\PembayaranSpmb());

        $this->pendaftaranService = new PendaftaranService($this->pembayaranServiceMock);

        \Illuminate\Support\Facades\DB::table('gelombang_penerimaan')->insert([
            'jalur_masuk_id' => \Illuminate\Support\Facades\DB::table('jalur_masuk')->insertGetId([
                'kode' => 'TEST',
                'nama' => 'Jalur Test',
                'is_active' => true,
            ]),
            'tahun_akademik_id' => 1,
            'nama' => 'Gelombang Test',
            'tanggal_buka' => now()->toDateString(),
            'tanggal_tutup' => now()->addMonth()->toDateString(),
            'kuota_total' => 100,
            'kuota_terisi' => 0,
            'biaya_pendaftaran' => 0,
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_create_pendaftaran_and_trigger_notification()
    {
        Notification::fake();

        // Create a dummy user
        $user = User::factory()->create();

        $data = [
            'gelombang_id' => 1,
            'program_studi_id' => 101,
            'nama_lengkap' => 'Budi Calon Mahasiswa',
            'nik' => '3201010101010001',
            'tanggal_lahir' => '2005-01-01',
            'tempat_lahir' => 'Jakarta',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMAN 1 Jakarta',
        ];

        // Execute service
        $pendaftaran = $this->pendaftaranService->create($data, $user);

        // Assert Database
        $this->assertDatabaseHas('pendaftaran_calon_mhs', [
            'user_id' => $user->id,
            'gelombang_id' => 1,
            'program_studi_id' => 101,
            'nama_lengkap' => 'Budi Calon Mahasiswa',
            'nik' => '3201010101010001',
            'status' => 'draft'
        ]);

        $this->assertNotNull($pendaftaran->no_pendaftaran);
        $this->assertStringStartsWith('PMB-', $pendaftaran->no_pendaftaran);

        // Assert Notification was sent
        Notification::assertSentTo(
            [$user],
            \App\Notifications\PendaftaranSuksesNotification::class
        );
    }

    public function test_can_update_verifikasi_status()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        
        // Create dummy Pendaftaran directly (or via factory)
        $pendaftaranId = \Illuminate\Support\Facades\DB::table('pendaftaran_calon_mhs')->insertGetId([
            'user_id' => $user->id,
            'gelombang_id' => 1,
            'program_studi_id' => 101,
            'no_pendaftaran' => 'PMB-TEST-001',
            'nama_lengkap' => 'Test User',
            'nik' => '1234567812345678',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Load the model instance
        $pendaftaran = PendaftaranCalonMhs::find($pendaftaranId);

        $updateData = [
            'status' => 'lulus_administrasi',
            'catatan' => 'Berkas lengkap dan sesuai'
        ];

        $updated = $this->pendaftaranService->updateVerifikasi($pendaftaran, $updateData, $admin);

        $this->assertEquals('lulus_administrasi', $updated->status);
        $this->assertEquals('Berkas lengkap dan sesuai', $updated->catatan_verifikasi);
        
        $this->assertDatabaseHas('pendaftaran_calon_mhs', [
            'id' => $pendaftaranId,
            'status' => 'lulus_administrasi',
            'diverifikasi_oleh' => $admin->id
        ]);
    }
}
