<?php

namespace Tests\Feature;

use App\Models\Lms\Quiz;
use App\Models\Lms\QuizSoal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\BankSoalKategori;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Rps;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * LMS-02 — Bank Soal: import XLSX, template, bulk delete, bulk move, update RESTful.
 */
class SiakadBankSoalImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userTanpaIzin;
    protected Rps $rps;
    protected MataKuliah $mk;
    protected BankSoalKategori $kategori;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.import', 'email' => 'dosen.import@test.ac.id']);
        $this->userTanpaIzin = User::factory()->create(['username' => 'mhs.import', 'email' => 'mhs.import@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-IMP', 'nama' => 'Informatika Import', 'jenjang' => 'S1']);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-IMP',
            'nama' => 'Kurikulum Import', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $this->mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF901', 'nama' => 'MK Import Soal',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true,
        ]);
        $this->rps = Rps::create(['mata_kuliah_id' => $this->mk->id]);

        $ta = TahunAkademik::create([
            'kode' => '20263', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil',
            'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true,
        ]);
        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $this->mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-IMP-A',
            'nama_kelas' => 'Kelas Import A', 'kapasitas' => 30,
        ]);

        $this->kategori = BankSoalKategori::create(['nama' => 'UTS', 'mata_kuliah_id' => $this->mk->id]);

        $role = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $perm = Permission::firstOrCreate(
            ['slug' => 'siakad.nilai.manage'],
            ['name' => 'Input Nilai', 'module' => 'siakad', 'action' => 'update']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $this->userDosen->roles()->attach($role->id);
    }

    public function test_show_dan_update_soal_via_endpoint_restful(): void
    {
        Passport::actingAs($this->userDosen);

        $soalId = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'tipe_soal' => 'uraian', 'pertanyaan' => 'Jelaskanenkapsulasi',
        ])->assertStatus(201)->json('data.id');

        // GET detail
        $this->getJson("/api/v1/siakad/obe/soal/{$soalId}")
            ->assertStatus(200)
            ->assertJsonPath('data.pertanyaan', 'Jelaskanenkapsulasi');

        // PUT update
        $this->putJson("/api/v1/siakad/obe/soal/{$soalId}", [
            'rps_id' => $this->rps->id,
            'kategori_id' => $this->kategori->id,
            'tipe_soal' => 'isian_singkat',
            'tingkat_kesulitan' => 'sukar',
            'pertanyaan' => 'Jelaskan enkapsulasi',
            'bobot' => 25,
            'kunci_jawaban' => 'Enkapsulasi',
        ])->assertStatus(200)->assertJsonPath('data.pertanyaan', 'Jelaskan enkapsulasi');

        $this->assertDatabaseHas('siakad_bank_soal', [
            'id' => $soalId,
            'kategori_id' => $this->kategori->id,
            'tipe_soal' => 'isian_singkat',
            'tingkat_kesulitan' => 'sukar',
            'bobot' => 25,
        ]);
    }

    public function test_unduh_template_import_soal(): void
    {
        Passport::actingAs($this->userDosen);

        $res = $this->get('/api/v1/siakad/obe/soal/import/template');

        $res->assertStatus(200)
            ->assertDownload('template-import-bank-soal.xlsx');

        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $res->headers->get('content-type')
        );
    }

    public function test_import_soal_xlsx_membuat_soal_dan_opsi(): void
    {
        Passport::actingAs($this->userDosen);

        $content = $this->makeXlsx([
            ['tipe', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'poin', 'tingkat_kesulitan', 'pembahasan'],
            ['pg', 'Ibu kota Indonesia adalah …', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', '', 'A', 10, 'mudah', 'Jakarta'],
            ['isian', 'Sebutkan kepanjangan HTTP.', '', '', '', '', '', 'HyperText Transfer Protocol', 15, 'sedang', ''],
        ]);

        $res = $this->post('/api/v1/siakad/obe/soal/import', [
            'file' => UploadedFile::fake()->createWithContent('bank-soal.xlsx', $content),
            'rps_id' => $this->rps->id,
            'kategori_id' => $this->kategori->id,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_imported', 2)
            ->assertJsonPath('data.total_skipped', 0);

        $this->assertDatabaseHas('siakad_bank_soal', [
            'rps_id' => $this->rps->id,
            'kategori_id' => $this->kategori->id,
            'tipe_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'mudah',
            'bobot' => 10,
            'kunci_jawaban' => null,
        ]);
        $this->assertDatabaseHas('siakad_bank_soal', [
            'rps_id' => $this->rps->id,
            'tipe_soal' => 'isian_singkat',
            'kunci_jawaban' => 'HyperText Transfer Protocol',
            'bobot' => 15,
        ]);

        // Opsi jawaban tersimpan beserta penanda satu jawaban benar.
        $soalPg = \App\Models\Siakad\BankSoal::where('tipe_soal', 'pilihan_ganda')->firstOrFail();
        $this->assertDatabaseCount('siakad_bank_soal_opsi', 4);
        $this->assertDatabaseHas('siakad_bank_soal_opsi', [
            'bank_soal_id' => $soalPg->id, 'teks' => 'Jakarta', 'is_benar' => true, 'urutan' => 1,
        ]);
        $this->assertDatabaseHas('siakad_bank_soal_opsi', [
            'bank_soal_id' => $soalPg->id, 'teks' => 'Bandung', 'is_benar' => false, 'urutan' => 2,
        ]);
    }

    public function test_import_soal_melewati_baris_tidak_valid_dan_melaporkan_error(): void
    {
        Passport::actingAs($this->userDosen);

        $content = $this->makeXlsx([
            ['tipe', 'pertanyaan', 'opsi_a', 'opsi_b', 'kunci', 'poin'],
            ['pg', 'Soal valid', 'A1', 'A2', 'A', 10],
            ['pg', 'Kunci tidak cocok', 'B1', 'B2', 'C', 10],
            ['ngawur', 'Tipe tidak dikenal', 'C1', 'C2', 'A', 10],
        ]);

        $res = $this->post('/api/v1/siakad/obe/soal/import', [
            'file' => UploadedFile::fake()->createWithContent('bank-soal.xlsx', $content),
            'rps_id' => $this->rps->id,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201)
            ->assertJsonPath('data.total_imported', 1)
            ->assertJsonPath('data.total_skipped', 2);

        $errors = $res->json('data.errors');
        $this->assertCount(2, $errors);
        $this->assertDatabaseCount('siakad_bank_soal', 1);
    }

    public function test_import_soal_menolak_user_tanpa_izin(): void
    {
        Passport::actingAs($this->userTanpaIzin);

        $content = $this->makeXlsx([
            ['tipe', 'pertanyaan', 'kunci'],
            ['isian', 'Tanpa izin', 'Jawaban'],
        ]);

        $this->post('/api/v1/siakad/obe/soal/import', [
            'file' => UploadedFile::fake()->createWithContent('bank-soal.xlsx', $content),
            'rps_id' => $this->rps->id,
        ], ['Accept' => 'application/json'])->assertStatus(403);

        $this->assertDatabaseCount('siakad_bank_soal', 0);
    }

    public function test_import_soal_menolak_rps_tidak_ada(): void
    {
        Passport::actingAs($this->userDosen);

        $content = $this->makeXlsx([
            ['tipe', 'pertanyaan', 'kunci'],
            ['isian', 'Tanpa RPS', 'Jawaban'],
        ]);

        $this->post('/api/v1/siakad/obe/soal/import', [
            'file' => UploadedFile::fake()->createWithContent('bank-soal.xlsx', $content),
            'rps_id' => 999999,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.rps_id.0', 'RPS tujuan tidak valid.');
    }

    public function test_bulk_delete_soal_melewati_soal_yang_dipakai_quiz(): void
    {
        Passport::actingAs($this->userDosen);

        $soalA = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'pertanyaan' => 'Soal biasa',
        ])->assertStatus(201)->json('data.id');

        $soalB = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'pertanyaan' => 'Soal terpakai quiz',
        ])->assertStatus(201)->json('data.id');

        $quiz = Quiz::create([
            'kelas_id' => $this->kelas->id,
            'tipe' => Quiz::TIPE_KUIS,
            'judul' => 'Kuis Bank Soal',
            'durasi_menit' => 60,
            'is_published' => true,
            'dibuat_oleh' => $this->userDosen->id,
        ]);
        QuizSoal::create(['quiz_id' => $quiz->id, 'bank_soal_id' => $soalB, 'urutan' => 1, 'poin' => 10]);

        $res = $this->postJson('/api/v1/siakad/obe/soal/bulk-delete', ['ids' => [$soalA, $soalB]]);

        $res->assertStatus(200)
            ->assertJsonPath('data.total_deleted', 1)
            ->assertJsonPath('data.total_skipped', 1)
            ->assertJsonPath('data.skipped.0.id', $soalB);

        $this->assertDatabaseMissing('siakad_bank_soal', ['id' => $soalA]);
        $this->assertDatabaseHas('siakad_bank_soal', ['id' => $soalB]);
    }

    public function test_bulk_delete_soal_menghapus_opsi_jawaban(): void
    {
        Passport::actingAs($this->userDosen);

        $soalId = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'tipe_soal' => 'pilihan_ganda', 'pertanyaan' => 'Soal dengan opsi',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/siakad/obe/soal/{$soalId}/opsi", ['teks' => 'Opsi A', 'is_benar' => true, 'urutan' => 1])
            ->assertStatus(201);

        $this->postJson('/api/v1/siakad/obe/soal/bulk-delete', ['ids' => [$soalId]])
            ->assertStatus(200)
            ->assertJsonPath('data.total_deleted', 1);

        $this->assertDatabaseMissing('siakad_bank_soal', ['id' => $soalId]);
        $this->assertDatabaseMissing('siakad_bank_soal_opsi', ['bank_soal_id' => $soalId]);
    }

    public function test_bulk_move_soal_ke_kategori_lain(): void
    {
        Passport::actingAs($this->userDosen);

        $soalId = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'kategori_id' => $this->kategori->id, 'pertanyaan' => 'Soal pindah',
        ])->assertStatus(201)->json('data.id');

        $kategoriBaru = BankSoalKategori::create(['nama' => 'UAS', 'mata_kuliah_id' => $this->mk->id]);

        $this->postJson('/api/v1/siakad/obe/soal/bulk-move', [
            'ids' => [$soalId],
            'kategori_id' => $kategoriBaru->id,
        ])->assertStatus(200)->assertJsonPath('data.total_updated', 1);

        $this->assertDatabaseHas('siakad_bank_soal', ['id' => $soalId, 'kategori_id' => $kategoriBaru->id]);
    }

    public function test_bulk_move_soal_menolak_tanpa_tujuan(): void
    {
        Passport::actingAs($this->userDosen);

        $soalId = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'pertanyaan' => 'Soal tanpa tujuan',
        ])->assertStatus(201)->json('data.id');

        $this->postJson('/api/v1/siakad/obe/soal/bulk-move', ['ids' => [$soalId]])
            ->assertStatus(422)
            ->assertJsonPath('errors.ids.0', 'Pilih minimal satu tujuan: kategori, RPS mingguan, atau Sub-CPMK.');
    }

    /**
     * Buat file XLSX in-memory untuk simulasi upload import.
     */
    private function makeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'test-soal-');
        (new Xlsx($spreadsheet))->save($path);
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }
}
