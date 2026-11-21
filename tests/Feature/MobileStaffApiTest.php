<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileStaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $dosenUser;
    private Dosen $dosen;
    private TahunAkademik $taAktif;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->taAktif = TahunAkademik::create([
            'kode' => '20261',
            'nama' => 'Ganjil 2026/2027',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $this->dosenUser = User::create([
            'username' => 'dosen.test',
            'email' => 'dosen.test@kampus.ac.id',
            'name' => 'Dr. Budi Santoso, M.Kom',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Dosen Pengajar',
            'slug' => 'dosen',
            'description' => 'Role dosen pengajar',
        ]);
        $permissionKelas = Permission::firstOrCreate([
            'slug' => 'siakad.kelas.read',
        ], [
            'name' => 'Lihat Jadwal Kuliah & RPS',
            'module' => 'siakad',
            'action' => 'read',
        ]);
        $role->permissions()->attach($permissionKelas->id);
        $this->dosenUser->roles()->attach($role->id);
        $this->dosenUser->refresh();

        $this->dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'nidn' => '0620018801',
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'program_studi_id' => $prodi->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/mobile/staff/jadwal-mengajar')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/staff/home')->assertUnauthorized();
        $this->postJson('/api/v1/mobile/staff/avatar', [])->assertUnauthorized();
    }

    public function test_jadwal_mengajar_authenticated_staff_empty_classes(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/mobile/staff/jadwal-mengajar');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.dosen.id', $this->dosen->id)
            ->assertJsonPath('data.total_kelas', 0)
            ->assertJsonPath('data.total_sks', 0)
            ->assertJsonPath('data.today_classes', [])
            ->assertJsonPath('data.all_classes', []);
    }

    public function test_jadwal_mengajar_with_real_kelas_and_pengampu(): void
    {
        Passport::actingAs($this->dosenUser);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-TI-2026',
            'nama' => 'Kurikulum TI 2026',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'TI301',
            'nama' => 'Pemrograman Web',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $gedung = Gedung::create([
            'kode' => 'G-A',
            'nama' => 'Gedung Rektorat',
            'is_active' => true,
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-101',
            'nama' => 'Ruang 101',
            'kapasitas' => 40,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'ruangan_id' => $ruangan->id,
            'kode_kelas' => 'TI-3A',
            'nama_kelas' => 'TI-3A',
            'hari' => 'senin',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:30:00',
            'kapasitas' => 35,
            'status' => 'aktif',
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
            'rencana_tatap_muka' => 16,
        ]);

        $response = $this->getJson('/api/v1/mobile/staff/jadwal-mengajar?hari=senin');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_kelas', 1)
            ->assertJsonPath('data.all_classes.0.course_code', 'TI301');
    }

    public function test_home_summary_authenticated_staff(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/mobile/staff/home');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $this->dosenUser->id)
            ->assertJsonPath('data.user.role', 'Dosen Pengajar')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'role'],
                    'jadwal',
                ],
            ]);
    }

    public function test_update_avatar_base64_and_audit_log(): void
    {
        Passport::actingAs($this->dosenUser);

        // 1x1 transparent PNG valid base64
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $response = $this->postJson('/api/v1/mobile/staff/avatar', [
            'avatar_base64' => $base64Image,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->dosenUser->id);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'IAM',
            'action' => 'update_avatar',
            'record_id' => (string) $this->dosenUser->id,
        ]);

        $this->dosenUser->refresh();
        $this->assertNotNull($this->dosenUser->avatar_path);
    }

    public function test_update_avatar_multipart(): void
    {
        Passport::actingAs($this->dosenUser);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/v1/mobile/staff/avatar', [
            'avatar' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->dosenUser->id);
    }

    public function test_siakad_perkuliahan_jadwal_mengajar(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/siakad/perkuliahan/jadwal-mengajar');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.dosen.id', $this->dosen->id);
    }
}
