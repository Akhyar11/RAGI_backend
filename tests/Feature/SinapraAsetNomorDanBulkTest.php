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

class SinapraAsetNomorDanBulkTest extends TestCase
{
    protected User $adminUser;
    protected Ruangan $ruangan;
    protected KategoriAset $kategori;

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
            'kode' => 'GD-TEST-' . uniqid(),
            'nama' => 'Gedung Laboratorium Uji',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-TEST-' . uniqid(),
            'nama' => 'Ruang Laboratorium Uji',
            'lantai' => 1,
            'kapasitas' => 30,
            'status' => 'tersedia',
        ]);

        $this->kategori = KategoriAset::create([
            'kode' => 'KUR',
            'nama' => 'Kursi & Meja Kuliah',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25,
        ]);
    }

    public function test_preview_nomor_inventaris_formula_baku(): void
    {
        Passport::actingAs($this->adminUser);

        $response = $this->getJson("/api/sinapra/aset/preview-nomor?ruangan_id={$this->ruangan->id}&kategori_id={$this->kategori->id}&tahun=2023&nomor_urut=4523&jumlah=5");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nomor_urut_mulai' => 4523,
                    'nomor_urut_selesai' => 4527,
                    'jumlah' => 5,
                    'kode_ruangan' => 'RF' . str_pad((string) $this->ruangan->id, 3, '0', STR_PAD_LEFT),
                    'kode_kategori' => 'KUR',
                    'default_institusi' => 'INDO',
                    'tahun' => 2023,
                ],
            ]);

        $expectedKode = '4523/RF' . str_pad((string) $this->ruangan->id, 3, '0', STR_PAD_LEFT) . '/KUR/INDO/2023';
        $this->assertEquals($expectedKode, $response->json('data.preview_nomor'));
    }

    public function test_tambah_aset_satuan_otomatis_nomor_inventaris(): void
    {
        Passport::actingAs($this->adminUser);

        $payload = [
            'mode' => 'satuan',
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'nama' => 'Kursi Kuliah Ergonomis',
            'merk' => 'Futura',
            'tanggal_perolehan' => '2023-05-10',
            'harga_perolehan' => 750000,
            'nomor_urut' => 4523,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ];

        $response = $this->postJson('/api/sinapra/aset', $payload);

        $expectedKode = '4523/RF' . str_pad((string) $this->ruangan->id, 3, '0', STR_PAD_LEFT) . '/KUR/INDO/2023';

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama' => 'Kursi Kuliah Ergonomis',
                    'nomor_urut' => 4523,
                    'kode_aset' => $expectedKode,
                ],
            ]);

        $this->assertDatabaseHas('sinapra_aset', [
            'kode_aset' => $expectedKode,
            'nomor_urut' => 4523,
        ]);
    }

    public function test_tambah_aset_masal_bulk_penomoran_berurutan(): void
    {
        Passport::actingAs($this->adminUser);

        $payload = [
            'mode' => 'bulk',
            'jumlah_barang' => 3,
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'nama' => 'Monitor Samsung 24 Inch',
            'merk' => 'Samsung',
            'tanggal_perolehan' => '2023-06-01',
            'harga_perolehan' => 1900000,
            'nomor_urut' => 5001,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ];

        $response = $this->postJson('/api/sinapra/aset', $payload);

        $response->assertStatus(201);
        $this->assertCount(3, $response->json('data'));

        $rf = 'RF' . str_pad((string) $this->ruangan->id, 3, '0', STR_PAD_LEFT);
        $this->assertEquals("5001/{$rf}/KUR/INDO/2023", $response->json('data.0.kode_aset'));
        $this->assertEquals("5002/{$rf}/KUR/INDO/2023", $response->json('data.1.kode_aset'));
        $this->assertEquals("5003/{$rf}/KUR/INDO/2023", $response->json('data.2.kode_aset'));

        $this->assertDatabaseHas('sinapra_aset', ['nomor_urut' => 5001]);
        $this->assertDatabaseHas('sinapra_aset', ['nomor_urut' => 5002]);
        $this->assertDatabaseHas('sinapra_aset', ['nomor_urut' => 5003]);
    }
}
