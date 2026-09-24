<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraAsetLabelTest extends TestCase
{
    protected User $adminUser;
    protected Aset $aset1;
    protected Aset $aset2;

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

        $gedung = Gedung::create([
            'kode' => 'GD-LAB-' . uniqid(),
            'nama' => 'Gedung Laboratorium Terpadu',
            'jumlah_lantai' => 3,
            'status' => 'aktif',
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-LAB-' . uniqid(),
            'nama' => 'Laboratorium Komputer & Jaringan',
            'lantai' => 2,
            'kapasitas' => 40,
            'status' => 'tersedia',
        ]);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-IT-' . uniqid(),
            'nama' => 'Peralatan Jaringan & Komputer',
            'masa_manfaat_tahun' => 5,
            'tarif_penyusutan_persen' => 20,
        ]);

        $this->aset1 = Aset::create([
            'kode_aset' => 'AST-PC-' . uniqid(),
            'nama' => 'Workstation AI Deep Learning',
            'merk' => 'Dell Precision',
            'model' => '7920 Tower',
            'serial_number' => 'SN-DELL-' . uniqid(),
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'tanggal_perolehan' => '2026-02-10',
            'harga_perolehan' => 45000000,
            'nilai_buku' => 45000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);

        $this->aset2 = Aset::create([
            'kode_aset' => 'AST-SW-' . uniqid(),
            'nama' => 'Manageable Switch 24 Port Gigabit',
            'merk' => 'Cisco Catalyst',
            'model' => 'C9200L',
            'serial_number' => 'SN-CISCO-' . uniqid(),
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'tanggal_perolehan' => '2026-03-01',
            'harga_perolehan' => 18000000,
            'nilai_buku' => 18000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);
    }

    public function test_can_generate_single_aset_label(): void
    {
        Passport::actingAs($this->adminUser);

        $response = $this->getJson("/api/sinapra/aset/{$this->aset1->id}/label");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.id', $this->aset1->id)
                 ->assertJsonPath('data.kode_aset', $this->aset1->kode_aset)
                 ->assertJsonPath('data.nama', $this->aset1->nama)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'id',
                         'kode_aset',
                         'nama',
                         'merk',
                         'model',
                         'serial_number',
                         'kategori',
                         'lokasi_ruangan',
                         'lokasi_gedung',
                         'tanggal_perolehan',
                         'kondisi',
                         'status',
                         'qr_content',
                         'qr_code_svg',
                         'instansi',
                     ],
                 ]);

        $this->assertStringContainsString('<svg', $response->json('data.qr_code_svg'));
    }

    public function test_can_generate_batch_aset_labels(): void
    {
        Passport::actingAs($this->adminUser);

        $payload = [
            'aset_ids' => [$this->aset1->id, $this->aset2->id],
        ];

        $response = $this->postJson('/api/sinapra/aset/labels/batch', $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.id', $this->aset1->id)
                 ->assertJsonPath('data.1.id', $this->aset2->id);

        $this->assertStringContainsString('<svg', $response->json('data.0.qr_code_svg'));
        $this->assertStringContainsString('<svg', $response->json('data.1.qr_code_svg'));
    }

    public function test_batch_labels_validation_error(): void
    {
        Passport::actingAs($this->adminUser);

        $response = $this->postJson('/api/sinapra/aset/labels/batch', [
            'aset_ids' => [],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['aset_ids']);
    }

    public function test_unauthenticated_cannot_access_label(): void
    {
        $response = $this->getJson("/api/sinapra/aset/{$this->aset1->id}/label");
        $response->assertStatus(401);
    }
}
