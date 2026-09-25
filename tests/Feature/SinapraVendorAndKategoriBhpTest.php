<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sinapra\MasterKategoriBhp;
use App\Models\Sinapra\MasterVendor;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraVendorAndKategoriBhpTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator', 'is_active' => true]
        );

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->sync([$adminRole->id]);
    }

    public function test_can_list_master_vendor(): void
    {
        Passport::actingAs($this->adminUser);

        MasterVendor::create([
            'kode' => 'VND-TEST-' . uniqid(),
            'nama' => 'PT Mitra Kalibrasi Test',
            'jenis_rekanan' => 'laboratorium_kalibrasi',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/sinapra/master/vendor');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'filters',
            ]);
    }

    public function test_can_create_update_and_delete_master_vendor(): void
    {
        Passport::actingAs($this->adminUser);

        $kode = 'VND-BARU-' . rand(100, 999);

        // CREATE
        $createRes = $this->postJson('/api/sinapra/master/vendor', [
            'kode' => $kode,
            'nama' => 'PT Penyedia Barang Sukses',
            'jenis_rekanan' => 'penyedia_barang',
            'alamat' => 'Jl. Merdeka No. 10',
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode', $kode);

        $vendorId = $createRes->json('data.id');

        // UPDATE
        $updateRes = $this->putJson("/api/sinapra/master/vendor/{$vendorId}", [
            'kode' => $kode,
            'nama' => 'PT Penyedia Barang Sukses Sejahtera',
            'jenis_rekanan' => 'penyedia_barang',
            'alamat' => 'Jl. Merdeka No. 15',
            'is_active' => true,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama', 'PT Penyedia Barang Sukses Sejahtera');

        // DELETE
        $deleteRes = $this->deleteJson("/api/sinapra/master/vendor/{$vendorId}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('sinapra_master_vendor', ['id' => $vendorId]);
    }

    public function test_can_list_master_kategori_bhp(): void
    {
        Passport::actingAs($this->adminUser);

        MasterKategoriBhp::create([
            'kode' => 'KAT-TEST-' . uniqid(),
            'nama' => 'Kategori BHP Test',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/sinapra/master/kategori-bhp');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'filters',
            ]);
    }

    public function test_can_create_update_and_delete_master_kategori_bhp(): void
    {
        Passport::actingAs($this->adminUser);

        $kode = 'KAT-ROBOTIKA-' . rand(100, 999);

        // CREATE
        $createRes = $this->postJson('/api/sinapra/master/kategori-bhp', [
            'kode' => $kode,
            'nama' => 'Komponen Robotika Lab',
            'deskripsi' => 'Kit modul robotika praktikum',
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode', $kode);

        $kategoriId = $createRes->json('data.id');

        // UPDATE
        $updateRes = $this->putJson("/api/sinapra/master/kategori-bhp/{$kategoriId}", [
            'kode' => $kode,
            'nama' => 'Komponen Robotika & IoT Lab',
            'deskripsi' => 'Kit modul robotika dan IoT',
            'is_active' => true,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama', 'Komponen Robotika & IoT Lab');

        // DELETE
        $deleteRes = $this->deleteJson("/api/sinapra/master/kategori-bhp/{$kategoriId}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseMissing('sinapra_master_kategori_bhp', ['id' => $kategoriId]);
    }
}
