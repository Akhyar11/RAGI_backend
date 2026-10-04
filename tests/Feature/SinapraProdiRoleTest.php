<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinapraProdiRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminSinapra;
    protected Role $adminSinapraRole;
    protected Role $labRole1;
    protected Role $labRole2;
    protected ProgramStudi $prodiTrpl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminSinapraRole = Role::firstOrCreate(
            ['slug' => 'admin_sinapra'],
            ['name' => 'Admin SINAPRA', 'is_active' => true]
        );

        $this->labRole1 = Role::firstOrCreate(
            ['slug' => 'admin_laboratorium'],
            ['name' => 'Admin Laboratorium', 'is_active' => true]
        );

        $this->labRole2 = Role::firstOrCreate(
            ['slug' => 'laboran_khusus_trpl'],
            ['name' => 'Laboran Khusus TRPL', 'is_active' => true]
        );

        $this->adminSinapra = User::factory()->create();
        $this->adminSinapra->roles()->attach($this->adminSinapraRole);

        $this->prodiTrpl = ProgramStudi::create([
            'kode_prodi' => 'TRPL-D4',
            'nama' => 'Teknologi Rekayasa Perangkat Lunak S1 Terapan',
            'jenjang' => 'D4',
            'is_active' => true,
        ]);
    }

    public function test_can_get_prodi_roles_list(): void
    {
        $response = $this->actingAs($this->adminSinapra, 'api')
            ->getJson('/api/sinapra/master/prodi-roles');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'kode_prodi',
                        'nama',
                        'jenjang',
                        'sinapra_roles',
                    ],
                ],
                'meta',
            ]);
    }

    public function test_can_get_available_roles_options(): void
    {
        $response = $this->actingAs($this->adminSinapra, 'api')
            ->getJson('/api/sinapra/master/prodi-roles/roles-options');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }

    public function test_can_plot_roles_to_program_studi(): void
    {
        $payload = [
            'role_id' => $this->labRole1->id,
        ];

        $response = $this->actingAs($this->adminSinapra, 'api')
            ->postJson("/api/sinapra/master/prodi-roles/{$this->prodiTrpl->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.sinapra_roles');

        $this->assertDatabaseHas('sinapra_prodi_roles', [
            'program_studi_id' => $this->prodiTrpl->id,
            'role_id' => $this->labRole1->id,
        ]);
    }

    public function test_can_update_or_replot_roles(): void
    {
        // Plot role 1
        $this->prodiTrpl->sinapraRoles()->sync([$this->labRole1->id]);

        // Replot ke role 2
        $payload = [
            'role_id' => $this->labRole2->id,
        ];

        $response = $this->actingAs($this->adminSinapra, 'api')
            ->putJson("/api/sinapra/master/prodi-roles/{$this->prodiTrpl->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.sinapra_roles');

        $this->assertDatabaseMissing('sinapra_prodi_roles', [
            'program_studi_id' => $this->prodiTrpl->id,
            'role_id' => $this->labRole1->id,
        ]);

        $this->assertDatabaseHas('sinapra_prodi_roles', [
            'program_studi_id' => $this->prodiTrpl->id,
            'role_id' => $this->labRole2->id,
        ]);
    }

    public function test_newly_added_siakad_prodi_automatically_appears_in_sinapra(): void
    {
        $newProdi = ProgramStudi::create([
            'kode_prodi' => 'TRO-D4',
            'nama' => 'Teknologi Rekayasa Otomotif S1 Terapan',
            'jenjang' => 'D4',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminSinapra, 'api')
            ->getJson('/api/sinapra/master/prodi-roles?search=Otomotif');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.kode_prodi', 'TRO-D4')
            ->assertJsonPath('data.0.nama', 'Teknologi Rekayasa Otomotif S1 Terapan');
    }
}
