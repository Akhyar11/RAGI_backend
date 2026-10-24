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
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\AturanSks;
use App\Services\Siakad\KrsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class SiakadAturanSksTest extends TestCase
{
    use RefreshDatabase;

    protected ProgramStudi $prodi;
    protected User $user;
    protected KrsService $krsService;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\SiakadAturanSksSeeder']);

        $this->krsService = app(KrsService::class);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
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

        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $this->user->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE',
            'is_active' => true,
        ]);
    }

    public function test_fallback_dan_dinamisasi_max_sks_by_ipk(): void
    {
        // 1. Uji aturan global default (dari migration atau fallback)
        // IPK 3.50 => 24 SKS
        $this->assertEquals(24, $this->krsService->maxSksByIpk(3.50));
        // IPK 2.75 => 22 SKS
        $this->assertEquals(22, $this->krsService->maxSksByIpk(2.75));
        // IPK 2.20 => 20 SKS
        $this->assertEquals(20, $this->krsService->maxSksByIpk(2.20));
        // IPK 1.50 => 18 SKS
        $this->assertEquals(18, $this->krsService->maxSksByIpk(1.50));

        // 2. Buat aturan khusus untuk Program Studi TI:
        // Misalnya di TI diperketat: IPK 3.00 - 4.00 hanya maksimal 20 SKS
        AturanSks::create([
            'program_studi_id' => $this->prodi->id,
            'ipk_min' => 3.00,
            'ipk_max' => 4.00,
            'max_sks' => 20,
            'keterangan' => 'Khusus TI Ketat',
            'is_active' => true,
        ]);

        // Cek tanpa prodi id (fallback global) -> tetap 24
        $this->assertEquals(24, $this->krsService->maxSksByIpk(3.50));

        // Cek dengan prodi id TI -> 20
        $this->assertEquals(20, $this->krsService->maxSksByIpk(3.50, $this->prodi->id));

        // Cek rentang lain yang belum ada di prodi TI (misal IPK 2.75) -> fallback ke aturan global (22 SKS)
        $this->assertEquals(22, $this->krsService->maxSksByIpk(2.75, $this->prodi->id));
    }

    public function test_api_crud_aturan_sks(): void
    {
        Passport::actingAs($this->user);

        // 1. GET list
        $resGet = $this->getJson('/api/v1/siakad/obe/aturan-sks');
        $resGet->assertStatus(200);
        $resGet->assertJsonStructure(['status', 'data']);

        // 2. POST create
        $resPost = $this->postJson('/api/v1/siakad/obe/aturan-sks', [
            'program_studi_id' => $this->prodi->id,
            'ipk_min' => 3.75,
            'ipk_max' => 4.00,
            'max_sks' => 24,
            'keterangan' => 'Cumlaude',
            'is_active' => true,
        ]);
        $resPost->assertStatus(201);
        $createdId = $resPost->json('data.id');

        // 3. PUT update
        $resPut = $this->putJson("/api/v1/siakad/obe/aturan-sks/{$createdId}", [
            'keterangan' => 'Honor Student',
            'max_sks' => 24,
        ]);
        $resPut->assertStatus(200);
        $this->assertEquals('Honor Student', $resPut->json('data.keterangan'));

        // 4. POST batch replace
        $resBatch = $this->postJson('/api/v1/siakad/obe/aturan-sks', [
            'program_studi_id' => $this->prodi->id,
            'batch' => [
                [
                    'ipk_min' => 3.50,
                    'ipk_max' => 4.00,
                    'max_sks' => 24,
                    'keterangan' => 'Kelas A',
                    'is_active' => true,
                ],
                [
                    'ipk_min' => 0.00,
                    'ipk_max' => 3.49,
                    'max_sks' => 20,
                    'keterangan' => 'Kelas B',
                    'is_active' => true,
                ],
            ],
        ]);
        $resBatch->assertStatus(201);
        $this->assertCount(2, $resBatch->json('data'));

        // 5. DELETE
        $firstId = $resBatch->json('data.0.id');
        $resDelete = $this->deleteJson("/api/v1/siakad/obe/aturan-sks/{$firstId}");
        $resDelete->assertStatus(200);
    }
}
