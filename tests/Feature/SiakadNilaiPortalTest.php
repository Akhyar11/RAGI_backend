<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadNilaiPortalTest extends TestCase
{
    use RefreshDatabase;

    protected $userDosen;
    protected $dosen;
    protected $prodi;
    protected $ta;
    protected $kurikulum;
    protected $mk;
    protected $kelas;
    protected $mahasiswa;
    protected $krsDetail;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $roleDosen = Role::where('slug', 'dosen')->first();
        $this->userDosen = User::factory()->create(['is_active' => true]);
        $this->userDosen->roles()->attach($roleDosen->id);

        $this->prodi = ProgramStudi::create([
            'kode' => 'TI',
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $this->dosen = Dosen::create([
            'user_id' => $this->userDosen->id,
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom.',
            'nik' => '198001012005011001',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $this->ta = TahunAkademik::create([
            'kode' => '2026/2027-1',
            'nama' => '2026/2027 Ganjil',
            'semester' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
            'mode_penilaian' => 'semi_obe',
        ]);

        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'K26-TI',
            'nama' => 'Kurikulum TI 2026',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);

        $this->mk = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'TIS101',
            'nama' => 'Pemrograman Web Modern',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester' => 3,
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'mata_kuliah_id' => $this->mk->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'kode_kelas' => 'TIS101-A',
            'nama_kelas' => 'Kelas A - Pemrograman Web',
            'kapasitas' => 40,
            'kuota_krs' => 40,
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
            'status' => 'aktif',
            'tipe_sesi' => 'gabungan',
        ]);

        \App\Models\Siakad\DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $this->dosen->id,
            'is_koordinator' => true,
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'nim' => '2026001',
            'nama_lengkap' => 'Ahmad Siswa',
            'program_studi_id' => $this->prodi->id,
            'angkatan' => 2026,
            'status' => 'aktif',
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'status' => 'approved',
            'total_sks' => 3,
        ]);

        $this->krsDetail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->userDosen);
    }

    public function test_dosen_bisa_list_kelas_mengajar_dengan_krs_details_count(): void
    {
        $res = $this->getJson("/api/v1/siakad/perkuliahan/kelas?my_teaching_only=1&tahun_akademik_id={$this->ta->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals(1, $res->json('data.0.krs_details_count'));
        $this->assertEquals(1, $res->json('data.0.krs_details_aktif_count'));
    }

    public function test_dosen_bisa_membuka_matriks_nilai_obe_kelas(): void
    {
        $res = $this->getJson("/api/v1/siakad/obe/kelas/{$this->kelas->id}/nilai");

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $res->assertJsonStructure([
            'status',
            'data' => [
                'kelas',
                'peserta',
                'komponen',
                'mode_penilaian',
                'total_bobot_komponen',
                'is_valid_100',
                'kelayakan',
            ],
        ]);
    }
}
