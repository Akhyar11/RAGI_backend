<?php

namespace Tests\Feature;

use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Database\Seeders\SimpegSuratTugasMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpegSuratTugasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $ketua;
    protected Pegawai $anggota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->seed(SimpegSuratTugasMasterSeeder::class);

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

        $this->ketua = Pegawai::create([
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

        $userAnggota = User::factory()->create([
            'username' => 'dosen.anggota',
            'email' => 'dosen.anggota@campus.ac.id',
        ]);

        $this->anggota = Pegawai::create([
            'user_id' => $userAnggota->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '199002202015042002',
            'nik' => '3271012002900003',
            'nidn' => '0420029002',
            'nama_lengkap' => 'Siti Nurhaliza, M.Cs.',
            'tanggal_lahir' => '1990-02-20',
            'tempat_lahir' => 'Jakarta',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'tanggal_masuk' => '2015-04-01',
            'status' => 'aktif',
        ]);
    }

    public function test_can_get_surat_tugas_masters()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/surat-tugas/masters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'kategori_kegiatan',
                    'jenis_transportasi',
                ],
            ]);
    }

    public function test_can_create_surat_tugas_with_anggota_and_file()
    {
        Storage::fake('public');

        $kategori = MasterKategoriKegiatanTugas::first();
        $transportasi = MasterJenisTransportasi::first();

        $file = UploadedFile::fake()->create('undangan_dinas.pdf', 300, 'application/pdf');

        $payload = [
            'pegawai_id' => $this->ketua->id,
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Workshop Kurikulum Berbasis OBE LLDIKTI IV',
            'tempat_berangkat' => 'Kampus Utama',
            'lokasi_tujuan' => 'Grand Mercure Bandung',
            'tanggal_berangkat' => '2026-10-01',
            'tanggal_kembali' => '2026-10-03',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-03',
            'maksud_tujuan' => 'Penyelarasan kurikulum prodi informatika dengan standar akreditasi internasional',
            'kendaraan_dinas' => 'Toyota Innova D 1234 ABC',
            'nama_driver' => 'Pak Joko',
            'kontak_driver' => '08123456789',
            'anggota' => [
                [
                    'pegawai_id' => $this->anggota->id,
                    'peran' => 'Anggota Tim Penyusun',
                ],
            ],
            'file_surat_tugas' => $file,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/surat-tugas', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama_kegiatan', 'Workshop Kurikulum Berbasis OBE LLDIKTI IV')
            ->assertJsonPath('data.status', 'diajukan');

        $this->assertDatabaseHas('simpeg_surat_tugas', [
            'nama_kegiatan' => 'Workshop Kurikulum Berbasis OBE LLDIKTI IV',
            'pegawai_id' => $this->ketua->id,
            'kendaraan_dinas' => 'Toyota Innova D 1234 ABC',
        ]);

        $this->assertDatabaseHas('simpeg_surat_tugas_anggota', [
            'pegawai_id' => $this->anggota->id,
            'peran' => 'Anggota Tim Penyusun',
        ]);
    }

    public function test_can_approve_surat_tugas_and_triggers_auto_presensi_dinas_luar()
    {
        $kategori = MasterKategoriKegiatanTugas::first();
        $transportasi = MasterJenisTransportasi::first();

        $suratTugas = SuratTugas::create([
            'pegawai_id' => $this->ketua->id,
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Akreditasi Lapangan LAM INFOKOM',
            'tempat_berangkat' => 'Kampus Utama',
            'lokasi_tujuan' => 'Universitas Indonesia Depok',
            'tanggal_berangkat' => '2026-10-05',
            'tanggal_kembali' => '2026-10-06',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-06',
            'maksud_tujuan' => 'Studi banding akreditasi',
            'status' => 'diajukan',
        ]);

        $suratTugas->anggota()->create([
            'pegawai_id' => $this->anggota->id,
            'peran' => 'Anggota Tim',
        ]);

        $approvePayload = [
            'status' => 'disetujui',
            'nomor_surat' => 'ST/089/REK/X/2026',
            'catatan_approval' => 'Disetujui. Harap menjaga nama baik almamater.',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/surat-tugas/{$suratTugas->id}/approve", $approvePayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.nomor_surat', 'ST/089/REK/X/2026');

        // Verifikasi otomatisasi presensi dinas luar untuk Ketua & Anggota (2 hari: 05 & 06 Okt 2026)
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->ketua->id,
            'tanggal' => '2026-10-05',
            'status_kehadiran' => 'dinas',
        ]);
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->ketua->id,
            'tanggal' => '2026-10-06',
            'status_kehadiran' => 'dinas',
        ]);
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->anggota->id,
            'tanggal' => '2026-10-05',
            'status_kehadiran' => 'dinas',
        ]);
        $this->assertDatabaseHas('simpeg_presensi_pegawai', [
            'pegawai_id' => $this->anggota->id,
            'tanggal' => '2026-10-06',
            'status_kehadiran' => 'dinas',
        ]);
    }

    public function test_can_upload_lpj_and_finish_surat_tugas()
    {
        Storage::fake('public');

        $kategori = MasterKategoriKegiatanTugas::first();
        $transportasi = MasterJenisTransportasi::first();

        $suratTugas = SuratTugas::create([
            'nomor_surat' => 'ST/001/REK/X/2026',
            'pegawai_id' => $this->ketua->id,
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Seminar Internasional',
            'tempat_berangkat' => 'Bandung',
            'lokasi_tujuan' => 'Yogyakarta',
            'tanggal_berangkat' => '2026-09-01',
            'tanggal_kembali' => '2026-09-02',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-02',
            'maksud_tujuan' => 'Publikasi paper scopus',
            'status' => 'disetujui',
        ]);

        $fileLpj = UploadedFile::fake()->create('laporan_lpj.pdf', 800, 'application/pdf');

        $payload = [
            'file_lpj' => $fileLpj,
            'laporan_kegiatan' => 'Paper berhasil dipresentasikan dan mendapat predikat Best Presentation',
            'biaya_realisasi' => 4500000,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/surat-tugas/{$suratTugas->id}/lpj", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.biaya_realisasi', '4500000.00');

        $this->assertDatabaseHas('simpeg_surat_tugas', [
            'id' => $suratTugas->id,
            'status' => 'selesai',
            'biaya_realisasi' => 4500000,
        ]);
    }

    public function test_can_delete_draft_surat_tugas()
    {
        $kategori = MasterKategoriKegiatanTugas::first();
        $transportasi = MasterJenisTransportasi::first();

        $suratTugas = SuratTugas::create([
            'pegawai_id' => $this->ketua->id,
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Rencana Perjalanan Batal',
            'tempat_berangkat' => 'Kampus',
            'lokasi_tujuan' => 'Jakarta',
            'tanggal_berangkat' => '2026-11-01',
            'tanggal_kembali' => '2026-11-02',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-02',
            'maksud_tujuan' => 'Testing draft',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/surat-tugas/{$suratTugas->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('simpeg_surat_tugas', [
            'id' => $suratTugas->id,
        ]);
    }
}
