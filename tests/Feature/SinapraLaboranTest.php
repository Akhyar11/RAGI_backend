<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraLaboranTest extends TestCase
{
    protected User $adminSarpras;
    protected User $laboranUser;
    protected User $otherLaboran;
    protected Ruangan $labTrpl;
    protected Ruangan $labElektro;
    protected Aset $asetLabTrpl;
    protected Aset $asetLabElektro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        // Setup Roles & Permissions
        $adminSarprasRole = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $laboranRole = Role::firstOrCreate(
            ['slug' => 'admin_laboratorium'],
            ['name' => 'Admin Laboratorium', 'is_active' => true]
        );

        $pManage = Permission::firstOrCreate(
            ['slug' => 'sinapra.laboran.manage'],
            ['name' => 'Kelola Laboran', 'module' => 'sinapra', 'action' => 'update']
        );
        $pRuanganRead = Permission::firstOrCreate(
            ['slug' => 'sinapra.ruangan.read'],
            ['name' => 'Lihat Ruangan', 'module' => 'sinapra', 'action' => 'read']
        );
        $pAsetRead = Permission::firstOrCreate(
            ['slug' => 'sinapra.aset.read'],
            ['name' => 'Lihat Aset', 'module' => 'sinapra', 'action' => 'read']
        );

        $adminSarprasRole->permissions()->sync([$pManage->id, $pRuanganRead->id, $pAsetRead->id]);
        $laboranRole->permissions()->sync([$pRuanganRead->id, $pAsetRead->id]);

        $this->adminSarpras = User::factory()->create();
        $this->adminSarpras->roles()->sync([$adminSarprasRole->id]);

        $this->laboranUser = User::factory()->create();
        $this->laboranUser->roles()->sync([$laboranRole->id]);

        $this->otherLaboran = User::factory()->create();
        $this->otherLaboran->roles()->sync([$laboranRole->id]);

        // Create Master Gedung, Ruangan, Kategori & Aset
        $gedung = Gedung::create([
            'kode' => 'GDG-TEST-' . uniqid(),
            'nama' => 'Gedung Lab Terpadu',
            'jumlah_lantai' => 3,
            'status' => 'aktif',
        ]);

        $this->labTrpl = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-TRPL-' . uniqid(),
            'nama' => 'Lab Rekayasa Perangkat Lunak',
            'lantai' => 2,
            'tipe' => 'lab',
            'kapasitas' => 30,
            'status' => 'aktif',
        ]);

        $this->labElektro = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-ELK-' . uniqid(),
            'nama' => 'Lab Teknik Elektro',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 25,
            'status' => 'aktif',
        ]);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-' . uniqid(),
            'nama' => 'Komputer & Elektronik',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25,
        ]);

        $this->asetLabTrpl = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labTrpl->id,
            'kode_aset' => 'AST-TRPL-' . uniqid(),
            'nama' => 'PC iMac Apple M2',
            'harga_perolehan' => 25000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => true,
        ]);

        $this->asetLabElektro = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labElektro->id,
            'kode_aset' => 'AST-ELK-' . uniqid(),
            'nama' => 'Osiloskop Rigol 100MHz',
            'harga_perolehan' => 12000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);
    }

    public function test_admin_sarpras_can_assign_and_unassign_laboran_to_ruangan(): void
    {
        Passport::actingAs($this->adminSarpras);

        // Assign laboranUser ke labTrpl
        $response = $this->postJson("/api/sinapra/ruangan/{$this->labTrpl->id}/laboran", [
            'user_id' => $this->laboranUser->id,
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.ruangan_id', $this->labTrpl->id)
            ->assertJsonPath('data.user_id', $this->laboranUser->id);

        $this->assertDatabaseHas('sinapra_laboran_ruangan', [
            'ruangan_id' => $this->labTrpl->id,
            'user_id' => $this->laboranUser->id,
        ]);

        // Get daftar laboran ruangan
        $getRes = $this->getJson("/api/sinapra/ruangan/{$this->labTrpl->id}/laboran");
        $getRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertTrue(collect($getRes->json('data'))->contains('id', $this->laboranUser->id));

        // Unassign laboran
        $delRes = $this->deleteJson("/api/sinapra/ruangan/{$this->labTrpl->id}/laboran/{$this->laboranUser->id}");
        $delRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('sinapra_laboran_ruangan', [
            'ruangan_id' => $this->labTrpl->id,
            'user_id' => $this->laboranUser->id,
        ]);
    }

    public function test_laboran_only_sees_assets_in_assigned_lab(): void
    {
        // Beri penugasan laboranUser ke labTrpl saja
        $this->labTrpl->laboran()->attach($this->laboranUser->id, ['is_primary' => true]);

        // Login sebagai laboranUser
        Passport::actingAs($this->laboranUser);

        $response = $this->getJson('/api/sinapra/aset');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $asetIds = collect($response->json('data'))->pluck('id');

        // Pastikan aset lab TRPL muncul, tetapi aset lab Elektro TIDAK muncul
        $this->assertTrue($asetIds->contains($this->asetLabTrpl->id));
        $this->assertFalse($asetIds->contains($this->asetLabElektro->id));
    }

    public function test_admin_sarpras_sees_all_assets(): void
    {
        Passport::actingAs($this->adminSarpras);

        $response = $this->getJson('/api/sinapra/aset');
        $response->assertStatus(200);

        $asetIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($asetIds->contains($this->asetLabTrpl->id));
        $this->assertTrue($asetIds->contains($this->asetLabElektro->id));
    }
}
