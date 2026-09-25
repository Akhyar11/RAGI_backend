<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Sinapra\MasterSatuan;
use App\Models\Sinapra\MasterTipeRuangan;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraMasterDataTest extends TestCase
{
    protected User $adminUser;
    protected Gedung $gedung;

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

        $this->gedung = Gedung::create([
            'kode' => 'GD-TEST-' . uniqid(),
            'nama' => 'Gedung Rektorat & Laboratorium',
            'jumlah_lantai' => 4,
            'status' => 'aktif',
        ]);
    }

    public function test_can_list_and_create_master_tipe_ruangan(): void
    {
        Passport::actingAs($this->adminUser);

        $payload = [
            'kode' => 'studio_vr_' . uniqid(),
            'nama' => 'Studio Virtual Reality',
            'deskripsi' => 'Lab riset VR dan AR lanjut',
            'is_active' => true,
            'urutan' => 12,
        ];

        $response = $this->postJson('/api/sinapra/master/tipe-ruangan', $payload);
        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.nama', 'Studio Virtual Reality');

        $listRes = $this->getJson('/api/sinapra/master/tipe-ruangan?search=Studio');
        $listRes->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_update_and_delete_master_tipe_ruangan(): void
    {
        Passport::actingAs($this->adminUser);

        $tipe = MasterTipeRuangan::create([
            'kode' => 'tipe_temp_' . uniqid(),
            'nama' => 'Tipe Sementara',
            'is_active' => true,
            'urutan' => 50,
        ]);

        $updateRes = $this->putJson("/api/sinapra/master/tipe-ruangan/{$tipe->id}", [
            'kode' => $tipe->kode,
            'nama' => 'Tipe Sementara Diperbarui',
            'is_active' => false,
            'urutan' => 55,
        ]);

        $updateRes->assertStatus(200)
                  ->assertJsonPath('status', 'success')
                  ->assertJsonPath('data.nama', 'Tipe Sementara Diperbarui');

        $deleteRes = $this->deleteJson("/api/sinapra/master/tipe-ruangan/{$tipe->id}");
        $deleteRes->assertStatus(200)
                  ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('sinapra_master_tipe_ruangan', ['id' => $tipe->id]);
    }

    public function test_can_list_create_update_delete_master_satuan(): void
    {
        Passport::actingAs($this->adminUser);

        $payload = [
            'kode' => 'DRUM_' . uniqid(),
            'nama' => 'Drum / Tangki',
            'keterangan' => 'Satuan penyimpanan bahan bakar atau cairan besar',
            'is_active' => true,
            'urutan' => 15,
        ];

        $createRes = $this->postJson('/api/sinapra/master/satuan', $payload);
        $createRes->assertStatus(201)
                  ->assertJsonPath('status', 'success')
                  ->assertJsonPath('data.nama', 'Drum / Tangki');

        $satuanId = $createRes->json('data.id');

        $listRes = $this->getJson('/api/sinapra/master/satuan');
        $listRes->assertStatus(200)
                ->assertJsonPath('status', 'success');

        $updateRes = $this->putJson("/api/sinapra/master/satuan/{$satuanId}", [
            'kode' => $payload['kode'],
            'nama' => 'Drum / Tangki Besar',
            'is_active' => true,
            'urutan' => 16,
        ]);
        $updateRes->assertStatus(200)
                  ->assertJsonPath('data.nama', 'Drum / Tangki Besar');

        $deleteRes = $this->deleteJson("/api/sinapra/master/satuan/{$satuanId}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('sinapra_master_satuan', ['id' => $satuanId]);
    }

    public function test_can_create_ruangan_with_tipe_ruangan_id(): void
    {
        Passport::actingAs($this->adminUser);

        $tipe = MasterTipeRuangan::create([
            'kode' => 'lab_jaringan_' . uniqid(),
            'nama' => 'Lab Jaringan Komputer',
            'is_active' => true,
        ]);

        $payload = [
            'gedung_id' => $this->gedung->id,
            'tipe_ruangan_id' => $tipe->id,
            'kode' => 'R-NET-' . uniqid(),
            'nama' => 'Ruang Lab Jaringan & Keamanan Cyber',
            'lantai' => 2,
            'kapasitas' => 35,
            'ada_ac' => true,
            'ada_proyektor' => true,
            'ada_wifi' => true,
            'status' => 'aktif',
        ];

        $response = $this->postJson('/api/sinapra/ruangan', $payload);
        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.tipe_ruangan_id', $tipe->id)
                 ->assertJsonPath('data.tipe_ruangan.nama', 'Lab Jaringan Komputer');
    }
}
