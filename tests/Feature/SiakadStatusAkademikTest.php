<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadStatusAkademikTest extends TestCase
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
            'nama_lengkap' => 'Mahasiswa Status',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);
    }

    public function test_alur_cuti_disetujui_mengubah_status_mahasiswa(): void
    {
        $create = $this->postJson('/api/v1/siakad/status/cuti', [
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
            'alasan' => 'Alasan cuti akademik yang cukup panjang',
        ]);
        $create->assertStatus(201);
        $this->assertDatabaseHas('siakad_cuti_mahasiswa', ['mahasiswa_id' => $this->mhs->id, 'status' => 'pending']);

        $id = $create->json('data.id');
        $proses = $this->patchJson("/api/v1/siakad/status/cuti/{$id}/proses", ['status' => 'disetujui']);
        $proses->assertStatus(200);
        $this->assertDatabaseHas('siakad_cuti_mahasiswa', ['id' => $id, 'status' => 'disetujui']);
        $this->assertDatabaseHas('siakad_mahasiswa', ['id' => $this->mhs->id, 'status' => 'cuti']);

        $list = $this->getJson('/api/v1/siakad/status/cuti?status=disetujui');
        $list->assertStatus(200);
        $list->assertJsonStructure(['status', 'data', 'meta']);
    }

    public function test_yudisium_ditolak_bila_syarat_tidak_terpenuhi(): void
    {
        $res = $this->postJson('/api/v1/siakad/kelulusan', [
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
            'ipk_akhir' => 1.50,
            'total_sks' => 120,
            'masa_studi_semester' => 8,
        ]);
        $res->assertStatus(422);
        $this->assertDatabaseMissing('siakad_kelulusan', ['mahasiswa_id' => $this->mhs->id]);
    }

    public function test_khs_lock_dan_buka_kunci(): void
    {
        $khs = Khs::create([
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
            'ips' => 3.50,
            'total_sks_semester' => 20,
            'sks_kumulatif' => 60,
            'ipk' => 3.40,
        ]);

        $list = $this->getJson("/api/v1/siakad/perkuliahan/khs?mahasiswa_id={$this->mhs->id}");
        $list->assertStatus(200);

        $lock = $this->patchJson("/api/v1/siakad/perkuliahan/khs/{$khs->id}/lock", ['is_locked' => true]);
        $lock->assertStatus(200);
        $this->assertDatabaseHas('siakad_khs', ['id' => $khs->id, 'is_locked' => true]);

        $unlock = $this->patchJson("/api/v1/siakad/perkuliahan/khs/{$khs->id}/lock", ['is_locked' => false]);
        $unlock->assertStatus(200);
        $this->assertDatabaseHas('siakad_khs', ['id' => $khs->id, 'is_locked' => false]);
    }
}
