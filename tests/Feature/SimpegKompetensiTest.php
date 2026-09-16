<?php

namespace Tests\Feature;

use App\Models\Simpeg\MasterJenisPelatihan;
use App\Models\Simpeg\MasterJenisSertifikasi;
use App\Models\Simpeg\MasterJenisTes;
use App\Models\Simpeg\MasterPeranPelatihan;
use App\Models\Simpeg\MasterTingkatKegiatan;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Database\Seeders\SimpegKompetensiMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpegKompetensiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        // Seed master kompetensi
        $this->seed(SimpegKompetensiMasterSeeder::class);

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

        $this->pegawai = Pegawai::create([
            'user_id' => $this->admin->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '198501152010121001',
            'nik' => '3271011501850002',
            'nidn' => '0415018501',
            'nama_lengkap' => 'Dr. Akhyar Pratama, M.Kom.',
            'tanggal_lahir' => '1985-01-15',
            'tempat_lahir' => 'Bandung',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'tanggal_masuk' => '2010-12-01',
            'status' => 'aktif',
        ]);
    }

    public function test_can_get_kompetensi_masters()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/kompetensi/masters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'jenis_sertifikasi',
                    'jenis_tes',
                    'jenis_pelatihan',
                    'peran_pelatihan',
                    'tingkat_kegiatan',
                ],
            ]);
    }

    public function test_can_create_and_list_sertifikasi_dosen()
    {
        Storage::fake('public');
        $master = MasterJenisSertifikasi::first();

        $file = UploadedFile::fake()->create('serdos.pdf', 500, 'application/pdf');

        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'jenis_sertifikasi_id' => $master->id,
            'nama_sertifikat' => 'Sertifikat Pendidik Profesional',
            'bidang_studi' => 'Ilmu Komputer',
            'nomor_registrasi' => 'REG-2024-001',
            'nomor_sk' => 'SK/DIKTI/2024/099',
            'tahun_sertifikasi' => 2024,
            'penyelenggara' => 'Kementerian Pendidikan Tinggi',
            'file' => $file,
        ];

        $resCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/kompetensi/sertifikasi', $payload);

        $resCreate->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama_sertifikat' => 'Sertifikat Pendidik Profesional',
                    'tahun_sertifikasi' => 2024,
                ],
            ]);

        $this->assertDatabaseHas('simpeg_sertifikasi_dosen', [
            'pegawai_id' => $this->pegawai->id,
            'nama_sertifikat' => 'Sertifikat Pendidik Profesional',
        ]);

        $resList = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/kompetensi/sertifikasi');

        $resList->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'nama_sertifikat', 'bidang_studi', 'tahun_sertifikasi'],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_can_create_and_list_riwayat_tes()
    {
        Storage::fake('public');
        $master = MasterJenisTes::where('kode', 'TOEFL_ITP')->first();

        $file = UploadedFile::fake()->create('toefl.pdf', 300, 'application/pdf');

        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'jenis_tes_id' => $master->id,
            'nama_tes' => 'TOEFL ITP Test Institusional',
            'penyelenggara' => 'Language Center ITB',
            'tahun' => 2025,
            'skor' => 580,
            'masa_berlaku' => '2027-01-01',
            'file' => $file,
        ];

        $resCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/kompetensi/tes', $payload);

        $resCreate->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'skor' => 580,
                    'tahun' => 2025,
                ],
            ]);

        $this->assertDatabaseHas('simpeg_riwayat_tes', [
            'pegawai_id' => $this->pegawai->id,
            'skor' => 580,
        ]);
    }

    public function test_can_create_and_list_riwayat_pelatihan()
    {
        $jenisPelatihan = MasterJenisPelatihan::first();
        $peran = MasterPeranPelatihan::first();
        $tingkat = MasterTingkatKegiatan::first();

        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'nama_kegiatan' => 'Pelatihan Pekerti Dosen Muda Angkatan 12',
            'jenis_pelatihan_id' => $jenisPelatihan->id,
            'peran_id' => $peran->id,
            'tingkat_id' => $tingkat->id,
            'tanggal_mulai' => '2026-03-01',
            'tanggal_selesai' => '2026-03-05',
            'jumlah_jam' => 40,
            'penyelenggara' => 'LP3M Universitas',
            'tempat' => 'Gedung Rektorat',
            'nomor_sertifikat' => 'CERT-PEKERTI-2026-045',
        ];

        $resCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/kompetensi/pelatihan', $payload);

        $resCreate->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama_kegiatan' => 'Pelatihan Pekerti Dosen Muda Angkatan 12',
                    'jumlah_jam' => 40,
                ],
            ]);

        $this->assertDatabaseHas('simpeg_riwayat_pelatihan', [
            'pegawai_id' => $this->pegawai->id,
            'nama_kegiatan' => 'Pelatihan Pekerti Dosen Muda Angkatan 12',
        ]);
    }

    public function test_can_search_kompetensi_as_admin()
    {
        $master = MasterJenisTes::first();
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/kompetensi/tes', [
                'pegawai_id' => $this->pegawai->id,
                'jenis_tes_id' => $master->id,
                'nama_tes' => 'TKDA PLTI Dosen',
                'penyelenggara' => 'PLTI',
                'tahun' => 2025,
                'skor' => 620,
            ]);

        $resSearch = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/kompetensi/pencarian?kategori=tes&skor_min=500');

        $resSearch->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data',
                'meta',
            ]);
    }

    public function test_validation_error_when_invalid_master_id()
    {
        $res = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/kompetensi/sertifikasi', [
                'pegawai_id' => $this->pegawai->id,
                'jenis_sertifikasi_id' => 999999, // Invalid ID
                'nama_sertifikat' => 'Test',
                'bidang_studi' => 'Test',
                'tahun_sertifikasi' => 2025,
                'penyelenggara' => 'Test',
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['jenis_sertifikasi_id']);
    }
}
