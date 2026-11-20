<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\AdminProdi;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\Dosen;
use App\Models\Simpeg\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class AdminProdiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);
    }

    public function test_admin_siakad_can_assign_admin_obe_prodi()
    {
        $superAdminRole = Role::where('slug', 'superadmin')->first();
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach($superAdminRole->id);

        $fakultas = Fakultas::create(['kode' => 'FTI', 'nama' => 'Fakultas TI']);
        $prodi = ProgramStudi::create([
            'fakultas_id' => $fakultas->id,
            'kode_prodi' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $targetUser = User::factory()->create();

        Passport::actingAs($adminUser);

        $response = $this->postJson('/api/v1/siakad/akademik/admin-prodi', [
            'program_studi_id' => $prodi->id,
            'user_id' => $targetUser->id,
            'jabatan' => 'Admin Tim Kurikulum',
            'can_approve_rps' => true,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('siakad_admin_prodi', [
            'program_studi_id' => $prodi->id,
            'user_id' => $targetUser->id,
            'jabatan' => 'Admin Tim Kurikulum',
            'can_approve_rps' => 1,
        ]);

        $this->assertTrue($targetUser->fresh()->canManageObeForProdi($prodi->id));
        $this->assertTrue($targetUser->fresh()->canApproveRpsForProdi($prodi->id));
    }

    public function test_eligible_personnel_options_include_active_users_linked_to_dosen_or_pegawai_only(): void
    {
        $superAdminRole = Role::where('slug', 'superadmin')->first();
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach($superAdminRole->id);
        Passport::actingAs($adminUser);

        $dosenUser = User::factory()->create(['name' => 'Dosen Aktif']);
        Dosen::create([
            'user_id' => $dosenUser->id,
            'nama_lengkap' => 'Dosen Aktif',
            'nidn' => '0012345678',
            'is_active' => true,
        ]);

        $pegawaiUser = User::factory()->create(['name' => 'Pegawai Aktif']);
        Pegawai::create([
            'user_id' => $pegawaiUser->id,
            'nama_lengkap' => 'Pegawai Aktif',
            'nip' => '198001012020011001',
            'jenis_pegawai' => 'tendik',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $ordinaryUser = User::factory()->create(['name' => 'Akun Biasa']);
        $inactiveUser = User::factory()->create(['name' => 'Pegawai Nonaktif']);
        Pegawai::create([
            'user_id' => $inactiveUser->id,
            'nama_lengkap' => 'Pegawai Nonaktif',
            'jenis_pegawai' => 'tendik',
            'status' => 'nonaktif',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/siakad/akademik/admin-prodi/eligible-personnel?per_page=100');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['id' => $dosenUser->id, 'personnel_type' => 'Dosen'])
            ->assertJsonFragment(['id' => $pegawaiUser->id, 'personnel_type' => 'Pegawai'])
            ->assertJsonMissing(['id' => $ordinaryUser->id])
            ->assertJsonMissing(['id' => $inactiveUser->id]);

        $this->getJson('/api/v1/siakad/akademik/admin-prodi/eligible-personnel?search=0012345678')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $dosenUser->id);

        $regularUser = User::factory()->create();
        Passport::actingAs($regularUser);
        $this->getJson('/api/v1/siakad/akademik/admin-prodi/eligible-personnel')
            ->assertForbidden();
    }
}
