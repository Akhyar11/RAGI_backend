<?php

namespace Tests\Feature\Simpeg;

use App\Models\Role;
use App\Models\Simpeg\MasterGolonganPangkat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterGolonganPangkatCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $roleSuper = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrator', 'is_active' => true]);

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);
        $this->admin->roles()->attach($roleSuper->id);
    }

    public function test_can_list_master_golongan_pangkat_with_pagination()
    {
        MasterGolonganPangkat::create([
            'kode' => 'III/a',
            'nama' => 'Penata Muda',
            'pangkat' => 'Penata Muda',
            'ruang' => 'a',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/master-golongan-pangkat');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_can_create_master_golongan_pangkat()
    {
        $payload = [
            'kode' => 'IV/a',
            'nama' => 'Pembina',
            'pangkat' => 'Pembina',
            'ruang' => 'a',
            'urutan' => 5,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master-golongan-pangkat', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode', 'IV/a')
            ->assertJsonPath('data.nama', 'Pembina');

        $this->assertDatabaseHas('simpeg_master_golongan_pangkat', [
            'kode' => 'IV/a',
            'nama' => 'Pembina',
        ]);
    }

    public function test_can_update_master_golongan_pangkat()
    {
        $golongan = MasterGolonganPangkat::create([
            'kode' => 'III/b',
            'nama' => 'Penata Muda Tk I',
            'pangkat' => 'Penata Muda Tingkat I',
            'ruang' => 'b',
            'urutan' => 2,
            'is_active' => true,
        ]);

        $payload = [
            'kode' => 'III/b',
            'nama' => 'Penata Muda Tingkat I (Revisi)',
            'urutan' => 3,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/master-golongan-pangkat/{$golongan->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama', 'Penata Muda Tingkat I (Revisi)');

        $this->assertDatabaseHas('simpeg_master_golongan_pangkat', [
            'id' => $golongan->id,
            'nama' => 'Penata Muda Tingkat I (Revisi)',
        ]);
    }

    public function test_can_delete_master_golongan_pangkat()
    {
        $golongan = MasterGolonganPangkat::create([
            'kode' => 'III/c',
            'nama' => 'Penata',
            'urutan' => 4,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/master-golongan-pangkat/{$golongan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('simpeg_master_golongan_pangkat', [
            'id' => $golongan->id,
        ]);
    }
}
