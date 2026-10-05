<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Rps;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadBankSoalTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected Rps $rps;
    protected MataKuliah $mk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.soal', 'email' => 'dosen.soal@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-SOAL', 'nama' => 'Informatika Soal', 'jenjang' => 'S1']);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-SOAL',
            'nama' => 'Kurikulum Soal', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $this->mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF801', 'nama' => 'MK Bank Soal',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true,
        ]);
        $this->rps = Rps::create(['mata_kuliah_id' => $this->mk->id]);

        $role = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $perm = Permission::firstOrCreate(
            ['slug' => 'siakad.nilai.manage'],
            ['name' => 'Input Nilai', 'module' => 'siakad', 'action' => 'update']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $this->userDosen->roles()->attach($role->id);
    }

    public function test_kategori_crud_dan_soal_pilihan_ganda_dengan_opsi(): void
    {
        Passport::actingAs($this->userDosen);

        // 1. Buat kategori
        $kat = $this->postJson('/api/v1/siakad/obe/soal-kategori', [
            'nama' => 'Kuis Tengah Semester',
            'mata_kuliah_id' => $this->mk->id,
        ]);
        $kat->assertStatus(201)->assertJsonPath('data.nama', 'Kuis Tengah Semester');
        $kategoriId = $kat->json('data.id');

        // 2. Buat soal pilihan ganda terhubung kategori
        $soal = $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id,
            'kategori_id' => $kategoriId,
            'tipe_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'pertanyaan' => 'Apa kepanjangan dari OOP?',
            'bobot' => 10,
            'pembahasan' => 'Object-Oriented Programming.',
        ]);
        $soal->assertStatus(201)->assertJsonPath('data.tipe_soal', 'pilihan_ganda');
        $soalId = $soal->json('data.id');

        // 3. Tambah 2 opsi, satu benar
        $this->postJson("/api/v1/siakad/obe/soal/{$soalId}/opsi", ['teks' => 'Object-Oriented Programming', 'is_benar' => true, 'urutan' => 1])
            ->assertStatus(201);
        $this->postJson("/api/v1/siakad/obe/soal/{$soalId}/opsi", ['teks' => 'Open-Office Protocol', 'is_benar' => false, 'urutan' => 2])
            ->assertStatus(201);

        // 4. Opsi benar kedua menggeser yang pertama (tunggal)
        $this->postJson("/api/v1/siakad/obe/soal/{$soalId}/opsi", ['teks' => 'Opsi ketiga', 'is_benar' => true, 'urutan' => 3])
            ->assertStatus(201);
        $this->assertDatabaseHas('siakad_bank_soal_opsi', ['bank_soal_id' => $soalId, 'teks' => 'Opsi ketiga', 'is_benar' => true]);
        $this->assertDatabaseMissing('siakad_bank_soal_opsi', ['bank_soal_id' => $soalId, 'teks' => 'Object-Oriented Programming', 'is_benar' => true]);

        $this->assertDatabaseHas('siakad_bank_soal', ['id' => $soalId, 'kategori_id' => $kategoriId]);

        // 5. Hapus kategori melepas relasi tanpa menghapus soal
        $this->deleteJson("/api/v1/siakad/obe/soal-kategori/{$kategoriId}")->assertStatus(200);
        $this->assertDatabaseHas('siakad_bank_soal', ['id' => $soalId, 'kategori_id' => null]);
    }

    public function test_list_soal_mendukung_filter_dan_pagination(): void
    {
        Passport::actingAs($this->userDosen);

        $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'tipe_soal' => 'uraian', 'pertanyaan' => 'Jelaskan enkapsulasi OOP',
        ])->assertStatus(201);
        $this->postJson('/api/v1/siakad/obe/soal', [
            'rps_id' => $this->rps->id, 'tipe_soal' => 'pilihan_ganda', 'pertanyaan' => 'Manakah prinsip OOP?',
        ])->assertStatus(201);

        $res = $this->getJson('/api/v1/siakad/obe/soal?search=OOP&tipe_soal=pilihan_ganda&per_page=10&sort_by=id&sort_order=asc');
        $res->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tipe_soal', 'pilihan_ganda');
    }
}
