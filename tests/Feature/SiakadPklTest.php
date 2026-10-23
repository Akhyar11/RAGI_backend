<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Pkl;
use App\Models\Siakad\PklBimbingan;
use App\Models\Siakad\PklPenilaian;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadPklTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $mhsUser;
    protected $dosenUser;
    protected $mahasiswa;
    protected $dosen;
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
        $this->dosenUser = User::factory()->create(['is_active' => true]);
        $this->dosenUser->roles()->attach($dosenRole->id);

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

        $this->dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'nidn' => '0012345678',
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

    public function test_mahasiswa_can_submit_pkl(): void
    {
        Passport::actingAs($this->mhsUser);

        $payload = [
            'tahun_akademik_id' => $this->ta->id,
            'jenis' => 'pkl_reguler',
            'judul_laporan' => 'Pengembangan Sistem Enterprise',
            'instansi_nama' => 'PT Solusi Bangun Digital',
            'instansi_alamat' => 'Jl TB Simatupang No 10',
            'instansi_kota' => 'Jakarta Selatan',
            'pembimbing_lapangan_nama' => 'Irwan Santoso',
            'pembimbing_lapangan_kontak' => '081234567890',
            'pembimbing_lapangan_jabatan' => 'Lead Developer',
            'tanggal_mulai' => '2027-01-10',
            'tanggal_selesai' => '2027-04-10',
        ];

        $response = $this->postJson('/api/v1/siakad/pkl', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'diajukan',
                    'instansi_nama' => 'PT Solusi Bangun Digital',
                ],
            ]);

        $this->assertDatabaseHas('siakad_pkl', [
            'mahasiswa_id' => $this->mahasiswa->id,
            'instansi_nama' => 'PT Solusi Bangun Digital',
            'status' => 'diajukan',
        ]);
    }

    public function test_admin_can_verify_and_assign_dosen_to_pkl(): void
    {
        $pkl = Pkl::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'jenis' => 'pkl_reguler',
            'instansi_nama' => 'PT Solusi Bangun Digital',
            'tanggal_mulai' => '2027-01-10',
            'tanggal_selesai' => '2027-04-10',
            'status' => 'diajukan',
        ]);

        Passport::actingAs($this->adminUser);

        $response = $this->patchJson("/api/v1/siakad/pkl/{$pkl->id}/verify", [
            'status' => 'disetujui',
            'dosen_pembimbing_id' => $this->dosen->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $pkl->id,
                    'status' => 'disetujui',
                    'dosen_pembimbing_id' => $this->dosen->id,
                ],
            ]);

        $this->assertDatabaseHas('siakad_pkl', [
            'id' => $pkl->id,
            'status' => 'disetujui',
            'dosen_pembimbing_id' => $this->dosen->id,
        ]);
    }

    public function test_mahasiswa_can_create_pkl_bimbingan_logbook(): void
    {
        $pkl = Pkl::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'dosen_pembimbing_id' => $this->dosen->id,
            'jenis' => 'pkl_reguler',
            'instansi_nama' => 'PT Solusi Bangun Digital',
            'tanggal_mulai' => '2027-01-10',
            'tanggal_selesai' => '2027-04-10',
            'status' => 'disetujui',
        ]);

        Passport::actingAs($this->mhsUser);

        $response = $this->postJson("/api/v1/siakad/pkl/{$pkl->id}/bimbingan", [
            'tanggal' => '2027-01-15',
            'aktivitas' => 'Penyusunan modul backend microservices dan testing CI/CD',
            'target_capaian' => 'Endpoint API berfungsi normal',
            'kendala' => 'Koneksi jaringan AWS',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'pkl_id' => $pkl->id,
                    'aktivitas' => 'Penyusunan modul backend microservices dan testing CI/CD',
                    'status_verifikasi' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('siakad_pkl_bimbingan', [
            'pkl_id' => $pkl->id,
            'status_verifikasi' => 'pending',
        ]);
    }

    public function test_dosen_can_verify_bimbingan_and_input_pkl_nilai(): void
    {
        $pkl = Pkl::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->ta->id,
            'program_studi_id' => $this->prodi->id,
            'dosen_pembimbing_id' => $this->dosen->id,
            'jenis' => 'pkl_reguler',
            'instansi_nama' => 'PT Solusi Bangun Digital',
            'tanggal_mulai' => '2027-01-10',
            'tanggal_selesai' => '2027-04-10',
            'status' => 'disetujui',
        ]);

        $bimbingan = PklBimbingan::create([
            'pkl_id' => $pkl->id,
            'tanggal' => '2027-01-15',
            'aktivitas' => 'Penyusunan modul backend microservices dan testing CI/CD',
            'status_verifikasi' => 'pending',
        ]);

        Passport::actingAs($this->dosenUser);

        // 1. Verifikasi bimbingan
        $verifyRes = $this->patchJson("/api/v1/siakad/pkl/{$pkl->id}/bimbingan/{$bimbingan->id}/verify", [
            'status_verifikasi' => 'disetujui',
            'catatan_dosen' => 'Bagus, lanjutkan ke tahap berikutnya.',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status_verifikasi' => 'disetujui',
                ],
            ]);

        // 2. Input nilai komprehensif
        $nilaiRes = $this->postJson("/api/v1/siakad/pkl/{$pkl->id}/nilai", [
            'nilai_lapangan_total' => 90.00,
            'nilai_dosen_bimbingan' => 88.00,
            'nilai_dosen_laporan' => 86.00,
            'nilai_dosen_presentasi' => 90.00,
            'catatan_dosen' => 'Sangat memuaskan.',
            'is_finalized' => true,
        ]);

        $nilaiRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_finalized' => true,
                    'nilai_huruf_akhir' => 'A',
                ],
            ]);

        $this->assertDatabaseHas('siakad_pkl_penilaian', [
            'pkl_id' => $pkl->id,
            'is_finalized' => true,
        ]);

        $this->assertEquals('dinilai', $pkl->fresh()->status);
    }
}
