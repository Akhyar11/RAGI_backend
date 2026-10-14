<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\DokumenToken;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadDokumenKhsTest extends TestCase
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
            'nama_lengkap' => 'Mahasiswa Dokumen',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);
    }

    public function test_bulk_lock_mengesahkan_khs_periode(): void
    {
        Khs::create(['mahasiswa_id' => $this->mhs->id, 'tahun_akademik_id' => $this->ta->id, 'ips' => 3.50, 'total_sks_semester' => 20, 'sks_kumulatif' => 60, 'ipk' => 3.40]);

        $res = $this->postJson('/api/v1/siakad/perkuliahan/khs/bulk-lock', [
            'tahun_akademik_id' => $this->ta->id,
            'is_locked' => true,
        ]);
        $res->assertStatus(200);
        $res->assertJsonPath('data.affected', 1);
        $this->assertDatabaseHas('siakad_khs', ['mahasiswa_id' => $this->mhs->id, 'is_locked' => true]);
    }

    public function test_terbitkan_khs_resmi_dengan_snapshot(): void
    {
        Khs::create(['mahasiswa_id' => $this->mhs->id, 'tahun_akademik_id' => $this->ta->id, 'ips' => 3.50, 'total_sks_semester' => 20, 'sks_kumulatif' => 60, 'ipk' => 3.40]);

        $res = $this->postJson('/api/v1/siakad/perkuliahan/dokumen/terbitkan', [
            'jenis' => 'KHS',
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
        ]);
        $res->assertStatus(201);
        $res->assertJsonStructure(['status', 'data' => ['nomor_dokumen', 'snapshot', 'token', 'validasi_url', 'qr_code_svg']]);
        $this->assertEquals(3.50, $res->json('data.snapshot.ips'));
        $this->assertDatabaseHas('siakad_dokumen_tokens', ['jenis' => 'KHS', 'mahasiswa_id' => $this->mhs->id, 'is_active' => true]);
    }

    public function test_terbitkan_khs_tanpa_data_422(): void
    {
        $res = $this->postJson('/api/v1/siakad/perkuliahan/dokumen/terbitkan', [
            'jenis' => 'KHS',
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
        ]);
        $res->assertStatus(422);
    }

    public function test_validasi_dokumen_publik(): void
    {
        $dok = DokumenToken::create([
            'jenis' => 'TRANSKRIP',
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => null,
            'nomor_dokumen' => 'TRX/2401001/KUM/2026',
            'token' => str_repeat('b', 48),
            'snapshot' => ['ipk' => 3.40],
            'tanggal_terbit' => now(),
            'is_active' => true,
        ]);

        $res = $this->getJson("/api/v1/siakad/dokumen/validasi/{$dok->token}");
        $res->assertStatus(200);
        $res->assertJsonPath('data.is_valid', true);
        $res->assertJsonPath('data.nomor_dokumen', 'TRX/2401001/KUM/2026');
    }
}
