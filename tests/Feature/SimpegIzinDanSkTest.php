<?php

namespace Tests\Feature;

use App\Models\Simpeg\IzinJamKerja;
use App\Models\Simpeg\MasterJenisIzinJamKerja;
use App\Models\Simpeg\MasterKategoriSk;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\SkPegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Database\Seeders\SimpegIzinDanSkMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpegIzinDanSkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userDosen;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->seed(SimpegIzinDanSkMasterSeeder::class);

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $unitKerja = UnitKerja::create([
            'nama' => 'Teknik Informatika',
            'kode' => 'TI',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        $this->userDosen = User::factory()->create([
            'username' => 'dosen.ahmad',
            'email' => 'ahmad@campus.ac.id',
        ]);

        $this->pegawai = Pegawai::create([
            'user_id' => $this->userDosen->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '198805122015041003',
            'nik' => '3271011205880001',
            'nidn' => '0412058801',
            'nama_lengkap' => 'Ahmad Dahlan, M.T.',
            'tanggal_lahir' => '1988-05-12',
            'tempat_lahir' => 'Surabaya',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'tanggal_masuk' => '2015-04-01',
            'status' => 'aktif',
        ]);
    }

    public function test_can_get_izin_jam_kerja_masters()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/izin-kerja/masters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'jenis_izin',
                ],
            ]);
    }

    public function test_can_create_and_list_izin_jam_kerja()
    {
        Storage::fake('public');
        $jenisIzin = MasterJenisIzinJamKerja::first();

        $file = UploadedFile::fake()->create('surat_keterangan.pdf', 150, 'application/pdf');

        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'master_jenis_izin_id' => $jenisIzin->id,
            'tanggal' => '2026-09-20',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:30',
            'alasan' => 'Menghadiri rapat koordinasi kemitraan luar kampus',
            'file_bukti' => $file,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/izin-kerja', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.alasan', 'Menghadiri rapat koordinasi kemitraan luar kampus');

        $listResponse = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/izin-kerja');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_approve_izin_jam_kerja_and_sync_presensi()
    {
        $jenisIzinTerlambat = MasterJenisIzinJamKerja::where('kode', 'TERLAMBAT')->first();

        // Siapkan data presensi terlambat
        $presensi = PresensiPegawai::create([
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-09-21',
            'jam_masuk' => '08:45:00',
            'status' => 'terlambat',
            'late_minutes' => 45,
            'catatan' => 'Terlambat 45 menit',
        ]);

        $izin = IzinJamKerja::create([
            'pegawai_id' => $this->pegawai->id,
            'master_jenis_izin_id' => $jenisIzinTerlambat->id,
            'tanggal' => '2026-09-21',
            'jam_mulai' => '08:00',
            'jam_selesai' => '08:45',
            'alasan' => 'Terjebak banjir di jalur tol arah kampus',
            'status' => 'menunggu',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/izin-kerja/{$izin->id}/approve", [
                'status' => 'disetujui',
                'catatan_approval' => 'Disetujui karena faktor force majeure banjir',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        // Verifikasi sinkronisasi presensi harian
        $presensi->refresh();
        $this->assertEquals('approved', $presensi->status);
        $this->assertEquals(0, $presensi->late_minutes);
        $this->assertStringContainsString('Izin Resmi', $presensi->catatan);
    }

    public function test_can_get_sk_pegawai_masters()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/sk-pegawai/masters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'kategori_sk',
                ],
            ]);
    }

    public function test_can_report_and_verify_sk_pegawai()
    {
        Storage::fake('public');
        $kategoriSk = MasterKategoriSk::first();

        $file = UploadedFile::fake()->create('sk_mengajar_gasal_2026.pdf', 300, 'application/pdf');

        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'kategori_sk_id' => $kategoriSk->id,
            'nomor_sk' => 'SK/REK/2026/089',
            'judul_sk' => 'SK Penugasan Beban Mengajar Dosen Semester Gasal 2026/2027',
            'pejabat_penetap' => 'Rektor Universitas',
            'tanggal_sk' => '2026-08-15',
            'tmt_sk' => '2026-09-01',
            'keterangan' => 'Digunakan untuk klaim BKD gasal',
            'file_sk' => $file,
        ];

        // Dosen mengunggah SK
        $storeResponse = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/sk-pegawai', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nomor_sk', 'SK/REK/2026/089')
            ->assertJsonPath('data.status_verifikasi', 'pending');

        $skId = $storeResponse->json('data.id');

        // Verifikator HR memverifikasi SK
        $verifyResponse = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/sk-pegawai/{$skId}/verify", [
                'status_verifikasi' => 'terverifikasi',
                'catatan_verifikasi' => 'Dokumen asli telah diperiksa dan sesuai ketentuan BKD',
            ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('data.status_verifikasi', 'terverifikasi')
            ->assertJsonPath('data.verified_by', $this->admin->id);
    }
}
