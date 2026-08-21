<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Gedung;
use App\Models\Ruangan;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Rps;
use App\Models\Siakad\Cpmk;

use Laravel\Passport\Passport;

class SiakadPerkuliahanSinapraIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected MasterProgramStudi $prodi;
    protected MasterTahunAkademik $ta;
    protected Gedung $gedung;
    protected Ruangan $ruangan;
    protected MataKuliah $mk;
    protected Dosen $dosen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'email' => 'admin@kampus.ac.id',
            'is_active' => true,
        ]);

        $this->ta = MasterTahunAkademik::create([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'semester' => 'ganjil',
            'is_active' => true,
        ]);

        $this->prodi = MasterProgramStudi::create([
            'kode_prodi' => 'IF',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $this->gedung = Gedung::create([
            'kode' => 'GD-A',
            'nama' => 'Gedung Rektorat & FTIK',
            'alamat' => 'Kampus Utama',
            'jumlah_lantai' => 4,
            'status' => 'aktif',
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $this->gedung->id,
            'kode' => 'LAB-201',
            'nama' => 'Laboratorium Rekayasa Perangkat Lunak',
            'lantai' => 2,
            'tipe' => 'lab',
            'kapasitas' => 45,
            'ada_ac' => true,
            'ada_proyektor' => true,
            'ada_wifi' => true,
            'status' => 'aktif',
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'KUR-2026-IF',
            'nama' => 'Kurikulum OBE 2026',
            'tahun_berlaku' => '2026',
            'is_active' => true,
        ]);

        $this->mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF2101',
            'nama' => 'Pemrograman Web Lanjut',
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'status' => 'aktif',
        ]);

        $this->dosen = Dosen::create([
            'program_studi_id' => $this->prodi->id,
            'nidn' => '0412058001',
            'nama_lengkap' => 'Dr. Budi Utomo, M.Kom',
            'email' => 'budi@kampus.ac.id',
            'status_ikatan_kerja' => 'tetap',
            'status_aktif' => 'aktif',
        ]);
    }

    public function test_it_can_fetch_sinapra_rooms_for_siakad_classes()
    {
        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/v1/siakad/perkuliahan/ref/ruangan');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'gedung_id', 'kode', 'nama', 'kapasitas', 'gedung']
                ],
                'message'
            ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_it_can_create_siakad_class_with_sinapra_room_allocation()
    {
        Passport::actingAs($this->admin);

        $payload = [
            'mata_kuliah_id' => $this->mk->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'ruangan_id' => $this->ruangan->id,
            'dosen_id' => $this->dosen->id,
            'kode_kelas' => 'IF2101-A',
            'nama_kelas' => 'Pemrograman Web Lanjut (Kelas A)',
            'kapasitas' => 45,
            'kuota_krs' => 45,
            'hari' => 'senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
        ];

        $response = $this->postJson('/api/v1/siakad/perkuliahan/kelas', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['id', 'kode_kelas', 'nama_kelas', 'ruangan']
            ]);

        $this->assertDatabaseHas('siakad_kelas', [
            'kode_kelas' => 'IF2101-A',
            'ruangan_id' => $this->ruangan->id,
            'kapasitas' => 45,
        ]);
    }

    public function test_it_can_store_and_update_obe_rps_and_weekly_plan()
    {
        Passport::actingAs($this->admin);

        $rpsPayload = [
            'mata_kuliah_id' => $this->mk->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 3,
            'deskripsi_singkat' => 'Mata kuliah pengembangan web terintegrasi standar OBE modern.',
            'pustaka_utama' => '1. Modern Web Architecture (2026)',
            'pustaka_pendukung' => '1. RESTful API Best Practices',
            'dosen_pengembang_id' => $this->dosen->id,
            'mingguan' => [
                [
                    'minggu_ke' => 1,
                    'kemampuan_akhir' => 'Mahasiswa memahami arsitektur microservices',
                    'bahan_kajian' => 'Pengenalan REST API dan SSO',
                    'bentuk_metode' => 'Kuliah & PBL',
                    'bobot_penilaian' => 5,
                ],
                [
                    'minggu_ke' => 8,
                    'kemampuan_akhir' => 'Evaluasi Tengah Semester (UTS)',
                    'bahan_kajian' => 'Ujian Tengah Semester Proyek',
                    'bentuk_metode' => 'Ujian Praktik',
                    'bobot_penilaian' => 25,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/siakad/obe/rps', $rpsPayload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);

        $this->assertDatabaseHas('siakad_rps', [
            'mata_kuliah_id' => $this->mk->id,
            'tahun_ajaran' => '2026/2027',
        ]);
    }

    public function test_authenticated_student_can_fetch_own_obe_portfolio()
    {
        $mhsUser = User::factory()->create([
            'email' => 'mhs.test@kampus.ac.id',
            'is_active' => true,
        ]);

        $mahasiswa = \App\Models\Siakad\Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'program_studi_id' => $this->prodi->id,
            'nim' => '2026001001',
            'nama_lengkap' => 'Mahasiswa OBE Test',
            'status' => 'aktif',
            'angkatan' => 2026,
        ]);

        Passport::actingAs($mhsUser);

        $response = $this->getJson('/api/v1/siakad/obe/mahasiswa/portofolio');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'mahasiswa',
                    'cpl_summary',
                    'radar_kategori',
                    'total_cpl',
                    'total_cpl_tercapai'
                ]
            ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertEquals('2026001001', $response->json('data.mahasiswa.nim'));
    }
}
