<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\RpsReferensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class SiakadRpsReferensiTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $superAdminRole = \App\Models\Role::where('slug', 'superadmin')->first();
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($superAdminRole->id);
        Passport::actingAs($this->admin);
    }

    public function test_crud_rps_referensi(): void
    {
        $res = $this->postJson('/api/v1/siakad/obe/rps-referensi', [
            'tipe' => 'bentuk',
            'kode' => 'BTK-01',
            'nama' => 'Kuliah Tatap Muka',
            'deskripsi' => 'Perkuliahan reguler terjadwal',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode', 'BTK-01');

        $id = $res->json('data.id');

        $list = $this->getJson('/api/v1/siakad/obe/rps-referensi?tipe=bentuk');
        $list->assertStatus(200)
            ->assertJsonPath('status', 'success');
        $this->assertCount(1, collect($list->json('data')));

        $this->putJson('/api/v1/siakad/obe/rps-referensi/' . $id, [
            'kode' => 'BTK-01B',
            'nama' => 'Kuliah Tatap Muka & Daring',
            'deskripsi' => 'Perkuliahan hybrid',
        ])->assertStatus(200);

        $this->assertSame('Kuliah Tatap Muka & Daring', RpsReferensi::findOrFail($id)->nama);

        $this->deleteJson('/api/v1/siakad/obe/rps-referensi/' . $id)->assertStatus(200);
        $this->assertSoftDeleted('siakad_rps_referensi', ['id' => $id]);
    }
}
