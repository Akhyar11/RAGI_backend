<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\Siakad\TugasAkhir;
use App\Models\Siakad\TugasAkhirBimbingan;
use App\Models\Siakad\TugasAkhirPenguji;
use App\Models\Siakad\TugasAkhirSidang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadTugasAkhirTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $mhsUser;
    protected $dosen1User;
    protected $dosen2User;
    protected $mahasiswa;
    protected $dosen1;
    protected $dosen2;
    protected $prodi;
    protected $ta;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);
        \App\Models\Siakad\SkalaNilai::create([
            'nilai_huruf' => 'A',
            'bobot_indeks' => 4.00,
            'batas_bawah' => 85.00,
            'batas_atas' => 100.00,
            'is_lulus' => true,
            'is_active' => true,
        ]);

        $superRole = Role::where('slug', 'superadmin')->first();
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($superRole->id);

        $mhsRole = Role::where('slug', 'mahasiswa')->first();
        $this->mhsUser = User::factory()->create(['is_active' => true]);
        $this->mhsUser->roles()->attach($mhsRole->id);

        $dosenRole = Role::where('slug', 'dosen')->first();
        $this->dosen1User = User::factory()->create(['is_active' => true]);
        $this->dosen1User->roles()->attach($dosenRole->id);

        $this->dosen2User = User::factory()->create(['is_active' => true]);
        $this->dosen2User->roles()->attach($dosenRole->id);

        $this->prodi = ProgramStudi::create([
            'kode' => 'TI',
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $this->ta = TahunAkademik::create([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'semester' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_aktif' => true,
            'is_active' => true,
        ]);

        $this->dosen1 = Dosen::create([
            'user_id' => $this->dosen1User->id,
            'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'Dr. Ir. Ahmad Dahlan, M.Kom',
            'nidn' => '0012345671',
            'is_active' => true,
        ]);

        $this->dosen2 = Dosen::create([
            'user_id' => $this->dosen2User->id,
            'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'Prof. Dr. Sri Mulyani, M.Sc',
            'nidn' => '0012345672',
            'is_active' => true,
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $this->mhsUser->id,
            'program_studi_id' => $this->prodi->id,
            'nim' => '2023010045',
            'nama_lengkap' => 'Nurul Fadillah',
            'angkatan' => 2023,
            'status' => 'aktif',
        ]);
    }

    public function test_mahasiswa_can_submit_ta_proposal(): void
    {
        Passport::actingAs($this->mhsUser);

        $payload = [
            'tahun_akademik_id' => $this->ta->id,
            'judul_utama' => 'Perancangan High Availability Microservices Architecture',
            'judul_inggris' => 'Design of High Availability Microservices Architecture',
            'bidang_kajian' => 'Software Engineering',
            'abstrak' => 'Penelitian ini menganalisis efisiensi arsitektur berbasis event driven pada transaksi tinggi.',
            'usulan_pembimbing_1_id' => $this->dosen1->id,
            'usulan_pembimbing_2_id' => $this->dosen2->id,
        ];

        $response = $this->postJson('/api/v1/siakad/ta', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'diajukan',
                    'judul_utama' => 'Perancangan High Availability Microservices Architecture',
                ],
            ]);

        $this->assertDatabaseHas('siakad_tugas_akhir', [
            'mahasiswa_id' => $this->mahasiswa->id,
            'judul_utama' => 'Perancangan High Availability Microservices Architecture',
            'status' => 'diajukan',
        ]);
    }

    public function test_admin_can_approve_ta_proposal_and_assign_pembimbing(): void
    {
        $ta = TugasAkhir::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'judul_utama' => 'Perancangan High Availability Microservices Architecture',
            'tahap' => 'proposal',
            'status' => 'diajukan',
        ]);

        Passport::actingAs($this->adminUser);

        $response = $this->patchJson("/api/v1/siakad/ta/{$ta->id}/approve-proposal", [
            'status' => 'disetujui_prodi',
            'dosen_pembimbing_1_id' => $this->dosen1->id,
            'dosen_pembimbing_2_id' => $this->dosen2->id,
            'nomor_sk_pembimbing' => 'SK-TA/TI/2026/X/001',
            'tanggal_sk_pembimbing' => '2026-10-24',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $ta->id,
                    'status' => 'disetujui_prodi',
                    'dosen_pembimbing_1_id' => $this->dosen1->id,
                    'dosen_pembimbing_2_id' => $this->dosen2->id,
                ],
            ]);

        $this->assertDatabaseHas('siakad_tugas_akhir', [
            'id' => $ta->id,
            'status' => 'disetujui_prodi',
            'dosen_pembimbing_1_id' => $this->dosen1->id,
            'dosen_pembimbing_2_id' => $this->dosen2->id,
        ]);
    }

    public function test_bimbingan_lifecycle_and_siap_sidang_status_transition(): void
    {
        $ta = TugasAkhir::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'judul_utama' => 'Perancangan High Availability Microservices Architecture',
            'dosen_pembimbing_1_id' => $this->dosen1->id,
            'tahap' => 'proposal',
            'status' => 'disetujui_prodi',
        ]);

        // 1. Mahasiswa input bimbingan
        Passport::actingAs($this->mhsUser);
        $bimbRes = $this->postJson("/api/v1/siakad/ta/{$ta->id}/bimbingan", [
            'dosen_id' => $this->dosen1->id,
            'tanggal' => '2026-11-01',
            'bab_pembahasan' => 'BAB I Pendahuluan',
            'uraian_konsultasi' => 'Diskusi latar belakang masalah dan rumusan masalah microservices.',
        ]);

        $bimbRes->assertStatus(201);
        $bimbinganId = $bimbRes->json('data.id');

        // 2. Dosen verifikasi ACC
        Passport::actingAs($this->dosen1User);
        $verifyRes = $this->patchJson("/api/v1/siakad/ta/{$ta->id}/bimbingan/{$bimbinganId}/verify", [
            'status_validasi' => 'acc',
            'arahan_revisi' => 'Lanjutkan ke bab 2 tinjauan pustaka.',
        ]);
        $verifyRes->assertStatus(200);

        // Buat 7 bimbingan acc lagi agar total 8 (threshold siap sidang)
        for ($i = 2; $i <= 8; $i++) {
            $b = TugasAkhirBimbingan::create([
                'tugas_akhir_id' => $ta->id,
                'dosen_id' => $this->dosen1->id,
                'tanggal' => "2026-11-0{$i}",
                'bab_pembahasan' => "BAB {$i} Pembahasan",
                'uraian_konsultasi' => 'Konsultasi berkala per bab skripsi.',
                'status_validasi' => 'menunggu',
            ]);

            $this->patchJson("/api/v1/siakad/ta/{$ta->id}/bimbingan/{$b->id}/verify", [
                'status_validasi' => 'acc',
            ]);
        }

        $this->assertEquals('siap_sidang', $ta->fresh()->status);
    }

    public function test_penjadwalan_sidang_scoring_and_finalization(): void
    {
        $ta = TugasAkhir::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'judul_utama' => 'Perancangan High Availability Microservices Architecture',
            'dosen_pembimbing_1_id' => $this->dosen1->id,
            'dosen_pembimbing_2_id' => $this->dosen2->id,
            'tahap' => 'proposal',
            'status' => 'siap_sidang',
        ]);

        // 1. Admin menjadwalkan sidang & plotting penguji
        Passport::actingAs($this->adminUser);
        $sidangRes = $this->postJson("/api/v1/siakad/ta/{$ta->id}/sidang", [
            'jenis_sidang' => 'sidang_skripsi',
            'tanggal_sidang' => '2027-02-20',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'ruangan_nama_manual' => 'Ruang Sidang Utama',
            'penguji' => [
                ['dosen_id' => $this->dosen1->id, 'peran_penguji' => 'penguji_1'],
                ['dosen_id' => $this->dosen2->id, 'peran_penguji' => 'penguji_2'],
            ],
        ]);

        $sidangRes->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'jenis_sidang' => 'sidang_skripsi',
                    'status_kelulusan' => 'belum_sidang',
                ],
            ]);

        $sidangId = $sidangRes->json('data.id');

        // 2. Dosen 1 input nilai sidang
        Passport::actingAs($this->dosen1User);
        $nilai1Res = $this->postJson("/api/v1/siakad/ta/sidang/{$sidangId}/nilai", [
            'dosen_id' => $this->dosen1->id,
            'skor_metodologi' => 88.00,
            'skor_materi_skripsi' => 90.00,
            'skor_presentasi' => 86.00,
            'skor_penguasaan_materi' => 88.00,
            'catatan_revisi' => 'Perbaiki penulisan daftar pustaka.',
        ]);

        $nilai1Res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'skor_total' => '88.00',
                    'is_submitted' => true,
                ],
            ]);

        // 3. Dosen 2 input nilai sidang
        Passport::actingAs($this->dosen2User);
        $nilai2Res = $this->postJson("/api/v1/siakad/ta/sidang/{$sidangId}/nilai", [
            'dosen_id' => $this->dosen2->id,
            'skor_metodologi' => 90.00,
            'skor_materi_skripsi' => 92.00,
            'skor_presentasi' => 88.00,
            'skor_penguasaan_materi' => 90.00,
            'catatan_revisi' => 'Mantap dan komprehensif.',
        ]);

        $nilai2Res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'skor_total' => '90.00',
                    'is_submitted' => true,
                ],
            ]);

        // 4. Admin finalisasi sidang & konversi komposit nilai
        Passport::actingAs($this->adminUser);
        $finalizeRes = $this->postJson("/api/v1/siakad/ta/sidang/{$sidangId}/finalize", [
            'status_kelulusan' => 'lulus_revisi_ringan',
            'batas_waktu_revisi' => '2027-03-05',
            'catatan_sidang' => 'Lulus dengan revisi minor daftar pustaka.',
        ]);

        $finalizeRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'sidang' => [
                        'status_kelulusan' => 'lulus_revisi_ringan',
                        'is_locked' => true,
                    ],
                    'tugas_akhir' => [
                        'status' => 'revisi',
                        'nilai_angka_akhir' => '89.00',
                        'nilai_huruf_akhir' => 'A',
                        'bobot_mutu_akhir' => '4.00',
                    ],
                ],
            ]);

        $this->assertEquals(89.00, (float) $ta->fresh()->nilai_angka_akhir);
        $this->assertEquals('A', $ta->fresh()->nilai_huruf_akhir);
    }
}
