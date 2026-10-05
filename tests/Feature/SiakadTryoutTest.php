<?php

namespace Tests\Feature;

use App\Models\Lms\QuizSoal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\BankSoal;
use App\Models\Siakad\BankSoalOpsi;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Rps;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadTryoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected User $userLuar;
    protected Mahasiswa $mahasiswa;
    protected Mahasiswa $mahasiswaLuar;
    protected Kelas $kelas;
    protected Rps $rps;
    protected KomponenPenilaian $komponen;
    protected int $bankBenar;
    protected int $bankSalah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.to', 'email' => 'dosen.to@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.to', 'email' => 'mhs.to@test.ac.id']);
        $this->userLuar = User::factory()->create(['username' => 'mhs.to.luar', 'email' => 'mhs.to.luar@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-TO', 'nama' => 'Informatika Tryout', 'jenjang' => 'S1']);
        $ta = TahunAkademik::create([
            'kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil',
            'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-TO',
            'nama' => 'Kurikulum Tryout', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF701', 'nama' => 'MK Tryout',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true,
        ]);
        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0077007700', 'nama_lengkap' => 'Dosen Tryout', 'status_aktif' => 'aktif',
        ]);
        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026007001', 'nama_lengkap' => 'Mahasiswa Tryout', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);
        $this->mahasiswaLuar = Mahasiswa::create([
            'user_id' => $this->userLuar->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026007002', 'nama_lengkap' => 'Mahasiswa Luar', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-TO-A',
            'nama_kelas' => 'Kelas Tryout A', 'kapasitas' => 30,
        ]);
        DosenPengampu::create(['kelas_id' => $this->kelas->id, 'dosen_id' => $dosen->id, 'peran' => 'pengampu_utama']);

        $krs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id, 'tahun_akademik_id' => $ta->id,
            'status' => 'disetujui', 'total_sks' => 3,
        ]);
        $krsDetail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_id' => $this->kelas->id, 'status' => 'aktif']);

        $this->rps = Rps::create(['mata_kuliah_id' => $mk->id]);
        $this->komponen = KomponenPenilaian::create([
            'kelas_id' => $this->kelas->id,
            'nama_komponen' => 'Tryout UTS',
            'teknik_penilaian' => 'tes_tulis',
            'bobot' => 20,
            'urutan' => 1,
            'is_aktif' => true,
        ]);

        // 2 soal PG: satu akan dijawab benar, satu salah.
        $b1 = BankSoal::create([
            'rps_id' => $this->rps->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Ibukota Indonesia?', 'bobot' => 10,
            'dibuat_oleh' => $this->userDosen->id,
        ]);
        $oBenar = BankSoalOpsi::create(['bank_soal_id' => $b1->id, 'teks' => 'Jakarta', 'is_benar' => true, 'urutan' => 1]);
        BankSoalOpsi::create(['bank_soal_id' => $b1->id, 'teks' => 'Bandung', 'is_benar' => false, 'urutan' => 2]);

        $b2 = BankSoal::create([
            'rps_id' => $this->rps->id, 'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => '2 + 2?', 'bobot' => 10,
            'dibuat_oleh' => $this->userDosen->id,
        ]);
        BankSoalOpsi::create(['bank_soal_id' => $b2->id, 'teks' => '4', 'is_benar' => true, 'urutan' => 1]);
        $oSalah = BankSoalOpsi::create(['bank_soal_id' => $b2->id, 'teks' => '5', 'is_benar' => false, 'urutan' => 2]);

        $this->bankBenar = $b1->id;
        $this->bankSalah = $b2->id;
        $this->opsiBenarId = $oBenar->id;
        $this->opsiSalahId = $oSalah->id;

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $perms = [];
        foreach (['siakad.kelas.read' => 'read', 'siakad.kelas.manage' => 'update', 'siakad.nilai.manage' => 'update', 'siakad.krs.read' => 'read'] as $slug => $act) {
            $perms[$slug] = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'siakad', 'action' => $act]);
        }
        $roleDosen->permissions()->syncWithoutDetaching([$perms['siakad.kelas.read']->id, $perms['siakad.kelas.manage']->id, $perms['siakad.nilai.manage']->id]);
        $roleMhs->permissions()->syncWithoutDetaching([$perms['siakad.kelas.read']->id, $perms['siakad.krs.read']->id]);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
        $this->userLuar->roles()->attach($roleMhs->id);
    }

    protected int $opsiBenarId;
    protected int $opsiSalahId;

    protected function buatTryoutPublished(array $over = []): int
    {
        Passport::actingAs($this->userDosen);
        $res = $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/tryout", array_merge([
            'judul' => 'Tryout UTS',
            'deskripsi' => 'Latihan UTS',
            'durasi_menit' => 60,
            'max_attempt' => 1,
            'komponen_penilaian_id' => $this->komponen->id,
            'is_published' => true,
        ], $over));
        $res->assertStatus(201);
        $quizId = $res->json('data.id');

        $this->postJson("/api/v1/lms/quiz/{$quizId}/soal", ['bank_soal_id' => $this->bankBenar, 'poin' => 50])
            ->assertStatus(201);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/soal", ['bank_soal_id' => $this->bankSalah, 'poin' => 50])
            ->assertStatus(201);

        return $quizId;
    }

    public function test_tryout_engine_batch_autosave_submit_dan_obe_sync(): void
    {
        $quizId = $this->buatTryoutPublished();

        Passport::actingAs($this->userMhs);

        // Detail tanpa kunci
        $detail = $this->getJson("/api/v1/lms/quiz/{$quizId}");
        $detail->assertStatus(200)->assertJsonPath('data.total_soal', 2);

        // Start
        $start = $this->postJson("/api/v1/lms/quiz/{$quizId}/start");
        $start->assertStatus(201);
        $attemptId = $start->json('data.id');

        // Batch: kunci TIDAK boleh bocor
        $batch = $this->getJson("/api/v1/lms/quiz/{$quizId}/soal?page=1");
        $batch->assertStatus(200);
        $payload = json_encode($batch->json('data'));
        $this->assertStringNotContainsStringIgnoringCase('is_benar', $payload);
        $this->assertStringNotContainsStringIgnoringCase('kunci_jawaban', $payload);
        $this->assertStringNotContainsStringIgnoringCase('pembahasan', $payload);
        $this->assertCount(2, $batch->json('data.data'));

        $qsIds = collect($batch->json('data.data'))->pluck('quiz_soal_id', 'pertanyaan')->all();

        // Autosave: 1 benar + 1 salah
        $this->postJson("/api/v1/lms/attempt/{$attemptId}/autosave", ['answers' => [
            ['quiz_soal_id' => $qsIds['Ibukota Indonesia?'], 'bank_opsi_id' => $this->opsiBenarId],
            ['quiz_soal_id' => $qsIds['2 + 2?'], 'bank_opsi_id' => $this->opsiSalahId],
        ]])->assertStatus(200);

        // Submit → nilai 50 (1 dari 2 soal @50 poin)
        $submit = $this->postJson("/api/v1/lms/attempt/{$attemptId}/submit");
        $submit->assertStatus(200)->assertJsonPath('data.nilai_akhir', '50.00');

        // OBE sync ke komponen tryout
        $krsDetailId = KrsDetail::where('kelas_id', $this->kelas->id)->first()->id;
        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'krs_detail_id' => $krsDetailId,
            'komponen_penilaian_id' => $this->komponen->id,
            'nilai_angka' => '50.00',
        ]);

        // Attempt kedua diblokir (max_attempt=1)
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start")->assertStatus(422);

        // Struktur terkunci setelah attempt ada
        Passport::actingAs($this->userDosen);
        $qsId = QuizSoal::where('quiz_id', $quizId)->first()->id;
        $this->deleteJson("/api/v1/lms/quiz-soal/{$qsId}")->assertStatus(422);
    }

    public function test_tryout_kode_akses_dan_peserta_eksplisit(): void
    {
        $quizId = $this->buatTryoutPublished(['kode_akses' => 'TO123']);

        // Tanpa kode / kode salah → 422
        Passport::actingAs($this->userMhs);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start")->assertStatus(422);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start", ['kode_akses' => 'SALAH'])->assertStatus(422);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start", ['kode_akses' => 'TO123'])->assertStatus(201);

        // Mahasiswa luar kelas tidak bisa mulai walau tahu kode
        Passport::actingAs($this->userLuar);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start", ['kode_akses' => 'TO123'])->assertStatus(422);

        // Dosen daftarkan eksplisit → bisa mulai
        Passport::actingAs($this->userDosen);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/peserta", ['mahasiswa_id' => $this->mahasiswaLuar->id])
            ->assertStatus(201);

        Passport::actingAs($this->userLuar);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start", ['kode_akses' => 'TO123'])->assertStatus(201);
    }

    public function test_tryout_diarsip_tidak_bisa_dikerjakan(): void
    {
        $quizId = $this->buatTryoutPublished();

        Passport::actingAs($this->userDosen);
        $this->putJson("/api/v1/lms/quiz/{$quizId}", ['is_archived' => true])->assertStatus(200);

        Passport::actingAs($this->userMhs);
        $this->postJson("/api/v1/lms/quiz/{$quizId}/start")->assertStatus(422);
        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/tryout")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
