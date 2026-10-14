<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\KartuUjianToken;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadKartuUjianTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $prodi;
    protected $ta;
    protected $mhs;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $role = Role::where('slug', 'superadmin')->first();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->roles()->attach($role->id);

        $this->prodi = ProgramStudi::create(['kode' => 'TI01', 'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]);
        $this->ta = TahunAkademik::create(['kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true]);
        $this->mhs = Mahasiswa::create([
            'program_studi_id' => $this->prodi->id,
            'nim' => '2401001',
            'nama_lengkap' => 'Mahasiswa Ujian',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);
    }

    public function test_cetak_kartu_menghasilkan_token_dan_qr(): void
    {
        $res = $this->getJson("/api/v1/siakad/perkuliahan/kartu-ujian?tipe_ujian=UTS&mahasiswa_id={$this->mhs->id}&tahun_akademik_id={$this->ta->id}");
        $res->assertStatus(200);
        $res->assertJsonStructure(['status', 'data' => ['mahasiswa', 'tahun_akademik', 'tipe_ujian', 'jadwal', 'token', 'validasi_url', 'qr_code_svg']]);
        $this->assertDatabaseHas('siakad_kartu_ujian_tokens', ['mahasiswa_id' => $this->mhs->id, 'tipe_ujian' => 'UTS', 'is_active' => true]);
    }

    public function test_validasi_publik_tanpa_login(): void
    {
        $kartu = KartuUjianToken::create([
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
            'tipe_ujian' => 'UAS',
            'token' => str_repeat('a', 48),
            'tanggal_cetak' => now(),
            'is_active' => true,
        ]);

        // Route validasi bersifat publik (hanya throttle): actingAs di atas tidak berpengaruh.
        $res = $this->getJson("/api/v1/siakad/kartu-ujian/validasi/{$kartu->token}");
        $res->assertStatus(200);
        $res->assertJsonPath('data.is_valid', true);
        $res->assertJsonPath('data.mahasiswa.nim', '2401001');
    }

    public function test_validasi_token_asing_404(): void
    {
        $res = $this->getJson('/api/v1/siakad/kartu-ujian/validasi/' . str_repeat('z', 48));
        $res->assertStatus(404);
    }
}
