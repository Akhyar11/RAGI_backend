<?php

namespace Tests\Feature;

use App\Models\Simpeg\MasterKategoriSkp;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PenilaianKinerja;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Database\Seeders\SimpegSkpMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpegSkpTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userDosen;
    protected User $userAtasan;
    protected Pegawai $pegawai;
    protected Pegawai $pejabatPenilai;
    protected MasterKategoriSkp $kategoriPendidikan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->seed(SimpegSkpMasterSeeder::class);

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
            'status_pegawai' => 'tetap',
            'status_aktif' => true,
            'tanggal_masuk' => '2015-04-01',
        ]);

        $this->userAtasan = User::factory()->create([
            'username' => 'dekan.fasilkom',
            'email' => 'dekan@campus.ac.id',
        ]);

        $this->pejabatPenilai = Pegawai::create([
            'user_id' => $this->userAtasan->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '197501012000031002',
            'nik' => '3271010101750002',
            'nidn' => '0401017501',
            'nama_lengkap' => 'Prof. Dr. Ir. H. Budi Mulyono, M.Sc.',
            'tanggal_lahir' => '1975-01-01',
            'tempat_lahir' => 'Bandung',
            'jenis_kelamin' => 'L',
            'status_pegawai' => 'tetap',
            'status_aktif' => true,
            'tanggal_masuk' => '2000-03-01',
        ]);

        $this->kategoriPendidikan = MasterKategoriSkp::first();
    }

    public function test_masters_returns_categories_and_evaluators(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/penilaian-kinerja/masters');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'kategori_skp',
                    'pejabat_penilai',
                ],
            ]);
    }

    public function test_store_creates_skp_with_items_in_draft(): void
    {
        $payload = [
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'items' => [
                [
                    'kategori_skp_id' => $this->kategoriPendidikan->id,
                    'uraian_tugas' => 'Mengajar Rekayasa Perangkat Lunak (3 SKS)',
                    'target_output' => '1 Berkas Nilai & RPS',
                    'target_mutu' => 100,
                    'target_waktu' => '6 Bulan',
                    'target_biaya' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/penilaian-kinerja', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.tahun', 2026)
            ->assertJsonPath('data.items.0.uraian_tugas', 'Mengajar Rekayasa Perangkat Lunak (3 SKS)');

        $this->assertDatabaseHas('simpeg_penilaian_kinerja', [
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('simpeg_skp_item', [
            'kategori_skp_id' => $this->kategoriPendidikan->id,
            'target_output' => '1 Berkas Nilai & RPS',
        ]);
    }

    public function test_submit_target_moves_skp_to_diajukan(): void
    {
        $skp = PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'status' => 'draft',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'nilai_skp' => 0,
            'predikat' => 'baik',
        ]);

        $skp->items()->create([
            'kategori_skp_id' => $this->kategoriPendidikan->id,
            'uraian_tugas' => 'Mengajar Pemrograman Web',
            'target_output' => '1 Berkas Nilai',
            'target_mutu' => 100,
            'target_waktu' => '6 Bulan',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/penilaian-kinerja/{$skp->id}/submit-target");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'diajukan');

        $this->assertDatabaseHas('simpeg_penilaian_kinerja', [
            'id' => $skp->id,
            'status' => 'diajukan',
        ]);
    }

    public function test_approve_target_moves_skp_to_disetujui(): void
    {
        $skp = PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'status' => 'diajukan',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'tanggal_pengajuan' => now(),
            'nilai_skp' => 0,
            'predikat' => 'baik',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/penilaian-kinerja/{$skp->id}/approve-target");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('simpeg_penilaian_kinerja', [
            'id' => $skp->id,
            'status' => 'disetujui',
        ]);
    }

    public function test_submit_realisasi_saves_actual_outputs_and_files(): void
    {
        Storage::fake('public');

        $skp = PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'status' => 'disetujui',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'tanggal_persetujuan' => now(),
            'nilai_skp' => 0,
            'predikat' => 'baik',
        ]);

        $item = $skp->items()->create([
            'kategori_skp_id' => $this->kategoriPendidikan->id,
            'uraian_tugas' => 'Mengajar Pemrograman Web',
            'target_output' => '1 Berkas Nilai',
            'target_mutu' => 100,
            'target_waktu' => '6 Bulan',
        ]);

        $dummyFile = UploadedFile::fake()->create('bukti_laporan.pdf', 500, 'application/pdf');

        $payload = [
            'items' => [
                [
                    'id' => $item->id,
                    'realisasi_output' => '1 Dokumen Lengkap Nilai & Portofolio Kelas',
                    'realisasi_mutu' => 95,
                    'realisasi_waktu' => '6 Bulan',
                    'realisasi_biaya' => 0,
                    'keterangan' => 'Tuntas tepat waktu',
                    'berkas_bukti' => $dummyFile,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/penilaian-kinerja/{$skp->id}/submit-realisasi", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('simpeg_skp_item', [
            'id' => $item->id,
            'realisasi_mutu' => 95,
            'realisasi_output' => '1 Dokumen Lengkap Nilai & Portofolio Kelas',
        ]);
    }

    public function test_evaluate_skp_calculates_final_score_and_predicate(): void
    {
        $skp = PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'status' => 'disetujui',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'nilai_skp' => 0,
            'predikat' => 'baik',
        ]);

        $item = $skp->items()->create([
            'kategori_skp_id' => $this->kategoriPendidikan->id,
            'uraian_tugas' => 'Pengajaran Kuliah Semester Ganjil',
            'target_output' => '1 Berkas Nilai',
            'target_mutu' => 100,
            'target_waktu' => '6 Bulan',
            'realisasi_output' => '1 Berkas Nilai Tuntas',
            'realisasi_mutu' => 96,
            'realisasi_waktu' => '6 Bulan',
        ]);

        $evalPayload = [
            'nilai_skp' => 94.50,
            'nilai_bkd' => 14.50,
            'predikat' => 'sangat_baik',
            'catatan_evaluator' => 'Sangat memuaskan, disiplin mengajar dan bukti lengkap.',
            'items' => [
                [
                    'id' => $item->id,
                    'nilai_capaian' => 96.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/penilaian-kinerja/{$skp->id}/evaluate", $evalPayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'dinilai')
            ->assertJsonPath('data.predikat', 'sangat_baik');

        $this->assertDatabaseHas('simpeg_penilaian_kinerja', [
            'id' => $skp->id,
            'status' => 'dinilai',
            'nilai_skp' => 94.50,
            'predikat' => 'sangat_baik',
        ]);
    }

    public function test_destroy_deletes_draft_skp(): void
    {
        $skp = PenilaianKinerja::create([
            'pegawai_id' => $this->pegawai->id,
            'tahun' => 2026,
            'semester' => 'ganjil',
            'status' => 'draft',
            'pejabat_penilai_id' => $this->pejabatPenilai->id,
            'nilai_skp' => 0,
            'predikat' => 'baik',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/penilaian-kinerja/{$skp->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('simpeg_penilaian_kinerja', [
            'id' => $skp->id,
        ]);
    }
}
