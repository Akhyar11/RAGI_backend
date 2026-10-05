<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadLmsPresensiTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected User $userLuar;
    protected Mahasiswa $mahasiswa;
    protected Mahasiswa $mahasiswaLuar;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.pres', 'email' => 'dosen.pres@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.pres', 'email' => 'mhs.pres@test.ac.id']);
        $this->userLuar = User::factory()->create(['username' => 'mhs.luar', 'email' => 'mhs.luar@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-PRES', 'nama' => 'Informatika Presensi', 'jenjang' => 'S1']);
        $ta = TahunAkademik::create([
            'kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil',
            'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-PRES',
            'nama' => 'Kurikulum Presensi', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF901', 'nama' => 'MK Presensi',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true,
        ]);
        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0099887766', 'nama_lengkap' => 'Dosen Presensi', 'status_aktif' => 'aktif',
        ]);
        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026009001', 'nama_lengkap' => 'Mahasiswa Presensi', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);
        $this->mahasiswaLuar = Mahasiswa::create([
            'user_id' => $this->userLuar->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026009002', 'nama_lengkap' => 'Mahasiswa Luar Kelas', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-PRES-A',
            'nama_kelas' => 'Kelas Presensi A', 'kapasitas' => 30,
        ]);
        DosenPengampu::create(['kelas_id' => $this->kelas->id, 'dosen_id' => $dosen->id, 'peran' => 'pengampu_utama']);

        $this->pertemuan = Pertemuan::create([
            'kelas_id' => $this->kelas->id, 'pertemuan_ke' => 1,
            'tanggal' => now()->toDateString(), 'materi' => 'Presensi hardening',
            'status_pertemuan' => 'berlangsung',
        ]);

        // Hanya mahasiswa utama yang punya KRS di kelas ini
        $krs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id, 'tahun_akademik_id' => $ta->id,
            'status' => 'disetujui', 'total_sks' => 3,
        ]);
        KrsDetail::create(['krs_id' => $krs->id, 'kelas_id' => $this->kelas->id, 'status' => 'aktif']);

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $perms = [];
        foreach (['siakad.kelas.read' => 'read', 'siakad.kelas.manage' => 'update', 'siakad.krs.read' => 'read'] as $slug => $act) {
            $perms[$slug] = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'siakad', 'action' => $act]);
        }
        $roleDosen->permissions()->syncWithoutDetaching([$perms['siakad.kelas.read']->id, $perms['siakad.kelas.manage']->id]);
        $roleMhs->permissions()->syncWithoutDetaching([$perms['siakad.kelas.read']->id, $perms['siakad.krs.read']->id]);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
        $this->userLuar->roles()->attach($roleMhs->id);
    }

    public function test_generate_token_menyimpan_window_dan_membuka_sesi(): void
    {
        Passport::actingAs($this->userDosen);

        $res = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token", ['window_menit' => 45]);
        $res->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data' => ['pertemuan_id', 'token', 'ttl_menit', 'window_menit', 'expired_at', 'sisa_detik']])
            ->assertJsonPath('data.window_menit', 45);

        $this->assertDatabaseHas('siakad_pertemuan', [
            'id' => $this->pertemuan->id,
            'window_menit' => 45,
            'presensi_closed_at' => null,
        ]);
    }

    public function test_rotate_token_menggugurkan_token_lama(): void
    {
        Passport::actingAs($this->userDosen);
        $old = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token")->json('data.token');

        $rot = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token/rotate");
        $rot->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['pertemuan_id', 'token', 'ttl_detik', 'expired_at']]);
        $new = $rot->json('data.token');
        $this->assertNotEquals($old, $new);

        // Token lama wajib ditolak mahasiswa
        Passport::actingAs($this->userMhs);
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/input-token", ['token' => $old])
            ->assertStatus(422);

        // Token baru diterima
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/input-token", ['token' => $new])
            ->assertStatus(200);
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status' => 'hadir',
        ]);
    }

    public function test_mahasiswa_luar_kelas_ditolak_input_token(): void
    {
        Passport::actingAs($this->userDosen);
        $token = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token")->json('data.token');

        Passport::actingAs($this->userLuar);
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/input-token", ['token' => $token])
            ->assertStatus(422);
        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswaLuar->id,
        ]);
    }

    public function test_tutup_presensi_menolak_input_token(): void
    {
        Passport::actingAs($this->userDosen);
        $token = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token")->json('data.token');

        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/tutup-presensi")
            ->assertStatus(200)
            ->assertJsonPath('data.status_pertemuan', 'selesai');

        Passport::actingAs($this->userMhs);
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/input-token", ['token' => $token])
            ->assertStatus(422);

        // Generate ulang setelah tutup juga ditolak — sesi sudah selesai
        Passport::actingAs($this->userDosen);
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token")
            ->assertStatus(422);
        $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/token/rotate")
            ->assertStatus(422);
    }
}
