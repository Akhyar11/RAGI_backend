<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\DistribusiMengajar;
use App\Models\Siakad\Dosen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class SiakadObeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected ProgramStudi $prodi;
    protected User $user;
    protected TahunAkademik $ta1;
    protected TahunAkademik $ta2;
    protected Kurikulum $kurikulum;
    protected MataKuliah $mataKuliah;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $this->ta1 = TahunAkademik::create([
            'kode' => '20251',
            'nama' => '2025/2026 Ganjil',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'is_active' => false,
        ]);

        $this->ta2 = TahunAkademik::create([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode' => 'K23',
            'nama' => 'Kurikulum Merdeka 2023',
            'tahun_berlaku' => 2023,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $this->mataKuliah = MataKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mk' => 'TIS101',
            'nama' => 'Pemrograman Web',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester' => 1,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['slug' => 'admin_obe'], ['name' => 'Admin OBE']);
        $permRead = Permission::where('slug', 'siakad.kurikulum.read')->first();
        $permManage = Permission::where('slug', 'siakad.kurikulum.manage')->first();
        if ($permRead && $permManage) {
            $role->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        }
        $this->user->roles()->attach($role->id);

        Dosen::create([
            'user_id' => $this->user->id,
            'nidn' => '12345678',
            'nama_lengkap' => 'Dr. Dosen OBE',
            'program_studi_id' => $this->prodi->id,
        ]);

        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $this->user->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE',
            'is_active' => true,
        ]);
    }

    public function test_get_dan_update_obe_settings(): void
    {
        Passport::actingAs($this->user);

        // 1. Get default settings
        $resGet = $this->getJson('/api/v1/siakad/obe/settings');
        $resGet->assertStatus(200);
        $resGet->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'tahun_akademik_id',
                'tahun_akademik_label',
                'semester',
                'kurikulum_id',
            ],
        ]);

        // Default harus mengarah ke TA aktif (ta2 = 2026/2027)
        $this->assertEquals($this->ta2->id, $resGet->json('data.tahun_akademik_id'));

        // 2. Update settings ke TA1 (2025/2026)
        $resUpdate = $this->postJson('/api/v1/siakad/obe/settings', [
            'tahun_akademik_id' => $this->ta1->id,
            'semester' => 'Ganjil',
            'kurikulum_id' => $this->kurikulum->id,
        ]);

        $resUpdate->assertStatus(200);
        $resUpdate->assertJson([
            'status' => 'success',
            'data' => [
                'tahun_akademik_id' => $this->ta1->id,
                'semester' => 'Ganjil',
                'kurikulum_id' => $this->kurikulum->id,
            ],
        ]);

        // 3. Verifikasi tersimpan
        $resGetAfter = $this->getJson('/api/v1/siakad/obe/settings');
        $resGetAfter->assertStatus(200);
        $this->assertEquals($this->ta1->id, $resGetAfter->json('data.tahun_akademik_id'));
    }

    public function test_filter_tahun_akademik_pada_distribusi_mengajar_dan_kelola_rps(): void
    {
        Passport::actingAs($this->user);

        // Buat 1 distribusi mengajar di TA1 dan 1 di TA2
        $dist1 = DistribusiMengajar::create([
            'program_studi_id' => $this->prodi->id,
            'tahun_akademik_id' => $this->ta1->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_kuliah_id' => $this->mataKuliah->id,
            'semester' => 1,
            'is_active' => true,
        ]);

        $dist2 = DistribusiMengajar::create([
            'program_studi_id' => $this->prodi->id,
            'tahun_akademik_id' => $this->ta2->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_kuliah_id' => $this->mataKuliah->id,
            'semester' => 1,
            'is_active' => true,
        ]);

        // 1. Cek filter Distribusi Mengajar untuk TA1
        $resDistTa1 = $this->getJson("/api/v1/siakad/obe/distribusi-mengajar?tahun_akademik_id={$this->ta1->id}");
        $resDistTa1->assertStatus(200);
        $items1 = $resDistTa1->json('data');
        $this->assertCount(1, $items1);
        $this->assertEquals($dist1->id, $items1[0]['id']);

        // 2. Cek filter Distribusi Mengajar untuk TA2
        $resDistTa2 = $this->getJson("/api/v1/siakad/obe/distribusi-mengajar?tahun_akademik_id={$this->ta2->id}");
        $resDistTa2->assertStatus(200);
        $items2 = $resDistTa2->json('data');
        $this->assertCount(1, $items2);
        $this->assertEquals($dist2->id, $items2[0]['id']);

        // 3. Cek filter Kelola RPS untuk TA1
        $resRpsTa1 = $this->getJson("/api/v1/siakad/obe/rps?tahun_akademik_id={$this->ta1->id}");
        $resRpsTa1->assertStatus(200);
        $rpsItems1 = $resRpsTa1->json('data');
        $this->assertCount(1, $rpsItems1);
        $this->assertEquals($dist1->id, $rpsItems1[0]['distribusi_id']);

        // 4. Cek filter Kelola RPS untuk TA2
        $resRpsTa2 = $this->getJson("/api/v1/siakad/obe/rps?tahun_akademik_id={$this->ta2->id}");
        $resRpsTa2->assertStatus(200);
        $rpsItems2 = $resRpsTa2->json('data');
        $this->assertCount(1, $rpsItems2);
        $this->assertEquals($dist2->id, $rpsItems2[0]['distribusi_id']);
    }
}
