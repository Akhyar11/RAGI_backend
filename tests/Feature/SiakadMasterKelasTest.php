<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\ProgramStudi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadMasterKelasTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $prodiA;
    protected $prodiB;
    protected $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $role = Role::where('slug', 'superadmin')->first();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->roles()->attach($role->id);

        $this->prodiA = ProgramStudi::create(['kode' => 'TI01', 'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]);
        $this->prodiB = ProgramStudi::create(['kode' => 'SI01', 'kode_prodi' => 'SI01', 'nama' => 'Sistem Informasi', 'jenjang' => 'S1', 'is_active' => true]);

        // Mapping user ke Prodi A
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $this->user->id,
            'program_studi_id' => $this->prodiA->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->dosen = Dosen::create([
            'nama_lengkap' => 'Dosen PA Penguji',
            'nik' => '999888777',
            'program_studi_id' => $this->prodiA->id,
            'is_active' => true,
        ]);

        Passport::actingAs($this->user);
    }

    public function test_crud_master_kelas(): void
    {
        // 1. Create Master Kelas
        $createRes = $this->postJson('/api/v1/siakad/obe/master-kelas', [
            'program_studi_id' => $this->prodiA->id,
            'nama_kelas' => '25A',
            'tahun_angkatan' => 2025,
            'dosen_pa_id' => $this->dosen->id,
            'keterangan' => 'Kelas Reguler Pagi',
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.nama_kelas', '25A')
            ->assertJsonPath('data.program_studi_id', $this->prodiA->id);

        $kelasId = $createRes->json('data.id');

        // 2. List Master Kelas (dengan paginasi)
        $listRes = $this->getJson('/api/v1/siakad/obe/master-kelas?page=1&per_page=10');
        $listRes->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta']);

        // 3. Update Master Kelas
        $updateRes = $this->putJson("/api/v1/siakad/obe/master-kelas/{$kelasId}", [
            'program_studi_id' => $this->prodiA->id,
            'nama_kelas' => '25A-Pagi',
            'tahun_angkatan' => 2025,
            'dosen_pa_id' => $this->dosen->id,
            'keterangan' => 'Kelas Diperbarui',
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama_kelas', '25A-Pagi');

        // 4. Delete Master Kelas
        $delRes = $this->deleteJson("/api/v1/siakad/obe/master-kelas/{$kelasId}");
        $delRes->assertStatus(200);
    }

    public function test_pemetaan_mahasiswa_ke_kelas_terkunci_ke_prodi_aktif(): void
    {
        // Mahasiswa di Prodi A (aktif user)
        $mhsA1 = Mahasiswa::create([
            'program_studi_id' => $this->prodiA->id,
            'nim' => '2501001',
            'nama_lengkap' => 'Mahasiswa Prodi A-1',
            'angkatan' => 2025,
            'status' => 'aktif',
        ]);
        $mhsA2 = Mahasiswa::create([
            'program_studi_id' => $this->prodiA->id,
            'nim' => '2501002',
            'nama_lengkap' => 'Mahasiswa Prodi A-2',
            'angkatan' => 2025,
            'status' => 'aktif',
        ]);

        // Mahasiswa di Prodi B
        $mhsB = Mahasiswa::create([
            'program_studi_id' => $this->prodiB->id,
            'nim' => '2502001',
            'nama_lengkap' => 'Mahasiswa Prodi B-1',
            'angkatan' => 2025,
            'status' => 'aktif',
        ]);

        $kelas = MasterKelas::create([
            'program_studi_id' => $this->prodiA->id,
            'nama_kelas' => '25A',
            'tahun_angkatan' => 2025,
            'dosen_pa_id' => $this->dosen->id,
            'is_active' => true,
        ]);

        // 1. Ambil list mahasiswa pemetaan
        $listMhs = $this->getJson("/api/v1/siakad/obe/master-kelas/mahasiswa-pemetaan?program_studi_id={$this->prodiA->id}");
        $listMhs->assertStatus(200);
        $nims = collect($listMhs->json('data'))->pluck('nim');
        $this->assertTrue($nims->contains('2501001'));
        $this->assertFalse($nims->contains('2502001')); // Mahasiswa prodi B tidak muncul

        // 2. Assign mahasiswa ke kelas
        $assignRes = $this->postJson('/api/v1/siakad/obe/master-kelas/assign-mahasiswa', [
            'master_kelas_id' => $kelas->id,
            'mahasiswa_ids' => [$mhsA1->id, $mhsA2->id],
            'sync_dosen_pa' => true,
        ]);

        $assignRes->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);

        $this->assertEquals('25A', $mhsA1->fresh()->kelas);
        $this->assertEquals($this->dosen->id, $mhsA1->fresh()->dosen_wali_id);
    }
}

