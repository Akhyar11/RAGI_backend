<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\AdminProdi;
use App\Models\Siakad\Fakultas;
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
}
