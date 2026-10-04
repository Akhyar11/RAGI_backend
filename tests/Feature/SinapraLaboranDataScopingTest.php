<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SinapraLaboranDataScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWasisTrpl;
    protected User $userBudiMesin;
    protected User $userAdminSarpras;

    protected Role $roleLaboranTrpl;
    protected Role $roleLaboranMesin;
    protected Role $roleAdminSarpras;

    protected ProgramStudi $prodiTrpl;
    protected ProgramStudi $prodiMesin;

    protected Gedung $gedungUtama;
    protected Ruangan $ruanganTrpl;
    protected Ruangan $ruanganMesin;

    protected Aset $asetTrpl;
    protected Aset $asetMesin;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'superadmin_role'],
            ['value' => 'superadmin']
        );

        // 1. Setup Permissions
        $permRuanganRead = Permission::firstOrCreate(['slug' => 'sinapra.ruangan.read'], ['name' => 'Read Ruangan', 'module' => 'sinapra', 'action' => 'read']);
        $permRuanganUpdate = Permission::firstOrCreate(['slug' => 'sinapra.ruangan.update'], ['name' => 'Update Ruangan', 'module' => 'sinapra', 'action' => 'update']);
        $permAsetRead = Permission::firstOrCreate(['slug' => 'sinapra.aset.read'], ['name' => 'Read Aset', 'module' => 'sinapra', 'action' => 'read']);
        $permAsetUpdate = Permission::firstOrCreate(['slug' => 'sinapra.aset.update'], ['name' => 'Update Aset', 'module' => 'sinapra', 'action' => 'update']);
        $permAsetDelete = Permission::firstOrCreate(['slug' => 'sinapra.aset.delete'], ['name' => 'Delete Aset', 'module' => 'sinapra', 'action' => 'delete']);

        // 2. Setup Roles
        $this->roleLaboranTrpl = Role::firstOrCreate(
            ['slug' => 'laboran_lab_trpl'],
            ['name' => 'Laboran Lab TRPL', 'is_active' => true]
        );
        $this->roleLaboranTrpl->permissions()->sync([
            $permRuanganRead->id,
            $permRuanganUpdate->id,
            $permAsetRead->id,
            $permAsetUpdate->id,
            $permAsetDelete->id,
        ]);

        $this->roleLaboranMesin = Role::firstOrCreate(
            ['slug' => 'laboran_lab_mesin'],
            ['name' => 'Laboran Lab Mesin', 'is_active' => true]
        );
        $this->roleLaboranMesin->permissions()->sync([
            $permRuanganRead->id,
            $permRuanganUpdate->id,
            $permAsetRead->id,
            $permAsetUpdate->id,
            $permAsetDelete->id,
        ]);

        $this->roleAdminSarpras = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $this->roleAdminSarpras->permissions()->sync([
            $permRuanganRead->id,
            $permRuanganUpdate->id,
            $permAsetRead->id,
            $permAsetUpdate->id,
            $permAsetDelete->id,
        ]);

        // 3. Setup Master Data Program Studi SIAKAD
        $this->prodiTrpl = ProgramStudi::create([
            'kode_prodi' => 'TRPL-D4',
            'nama' => 'Teknologi Rekayasa Perangkat Lunak',
            'jenjang' => 'D4',
            'is_active' => true,
        ]);

        $this->prodiMesin = ProgramStudi::create([
            'kode_prodi' => 'MESIN-D3',
            'nama' => 'Teknik Mesin',
            'jenjang' => 'D3',
            'is_active' => true,
        ]);

        // 4. Plotting Role ke Program Studi di SINAPRA (tabel sinapra_prodi_roles)
        DB::table('sinapra_prodi_roles')->insert([
            ['program_studi_id' => $this->prodiTrpl->id, 'role_id' => $this->roleLaboranTrpl->id, 'created_at' => now(), 'updated_at' => now()],
            ['program_studi_id' => $this->prodiMesin->id, 'role_id' => $this->roleLaboranMesin->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 5. Setup Users
        $this->userWasisTrpl = User::factory()->create(['name' => 'Wasis Waluyo', 'email' => 'wasis@campus.id']);
        $this->userWasisTrpl->roles()->attach($this->roleLaboranTrpl);

        $this->userBudiMesin = User::factory()->create(['name' => 'Budi Mesin', 'email' => 'budi@campus.id']);
        $this->userBudiMesin->roles()->attach($this->roleLaboranMesin);

        $this->userAdminSarpras = User::factory()->create(['name' => 'Admin Sarpras Pusat', 'email' => 'sarpras@campus.id']);
        $this->userAdminSarpras->roles()->attach($this->roleAdminSarpras);

        // 6. Setup Fasilitas Gedung, Ruangan & Aset
        $this->gedungUtama = Gedung::create([
            'kode' => 'GD-TI',
            'nama' => 'Gedung Teknologi Informasi & Rekayasa',
            'jumlah_lantai' => 4,
            'status' => 'aktif',
        ]);

        $this->ruanganTrpl = Ruangan::create([
            'gedung_id' => $this->gedungUtama->id,
            'program_studi_id' => $this->prodiTrpl->id,
            'kode' => 'LAB-TRPL-1',
            'nama' => 'Laboratorium Rekayasa Perangkat Lunak',
            'lantai' => 2,
            'tipe' => 'laboratorium',
            'kapasitas' => 35,
            'status' => 'tersedia',
        ]);

        $this->ruanganMesin = Ruangan::create([
            'gedung_id' => $this->gedungUtama->id,
            'program_studi_id' => $this->prodiMesin->id,
            'kode' => 'LAB-MESIN-1',
            'nama' => 'Workshop Bubut & CNC Mesin',
            'lantai' => 1,
            'tipe' => 'laboratorium',
            'kapasitas' => 25,
            'status' => 'tersedia',
        ]);

        $kategoriAset = KategoriAset::create([
            'kode' => 'KAT-LAB',
            'nama' => 'Peralatan Laboratorium',
            'masa_manfaat_tahun' => 5,
        ]);

        $this->asetTrpl = Aset::create([
            'kategori_id' => $kategoriAset->id,
            'ruangan_id' => $this->ruanganTrpl->id,
            'program_studi_id' => $this->prodiTrpl->id,
            'kode_aset' => 'AST-TRPL-001',
            'nama' => 'Server High-Performance TRPL',
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'harga_perolehan' => 35000000,
            'nilai_buku' => 35000000,
            'tanggal_perolehan' => now()->toDateString(),
        ]);

        $this->asetMesin = Aset::create([
            'kategori_id' => $kategoriAset->id,
            'ruangan_id' => $this->ruanganMesin->id,
            'program_studi_id' => $this->prodiMesin->id,
            'kode_aset' => 'AST-MESIN-001',
            'nama' => 'Mesin Bubut CNC 5 Axis',
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'harga_perolehan' => 120000000,
            'nilai_buku' => 120000000,
            'tanggal_perolehan' => now()->toDateString(),
        ]);
    }

    /**
     * Wasis (Laboran TRPL) hanya boleh melihat ruangan yang dinaungi prodi TRPL.
     */
    public function test_wasis_laboran_trpl_can_only_see_trpl_ruangan(): void
    {
        $response = $this->actingAs($this->userWasisTrpl, 'api')
            ->getJson('/api/sinapra/ruangan');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($this->ruanganTrpl->id, $data[0]['id']);
        $this->assertEquals('Laboratorium Rekayasa Perangkat Lunak', $data[0]['nama']);
    }

    /**
     * Wasis (Laboran TRPL) hanya boleh melihat aset/alat laboratorium milik TRPL.
     */
    public function test_wasis_laboran_trpl_can_only_see_trpl_aset(): void
    {
        $response = $this->actingAs($this->userWasisTrpl, 'api')
            ->getJson('/api/sinapra/aset');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($this->asetTrpl->id, $data[0]['id']);
        $this->assertEquals('Server High-Performance TRPL', $data[0]['nama']);
    }

    /**
     * Budi (Laboran Mesin) hanya boleh melihat aset/alat laboratorium milik Mesin.
     */
    public function test_budi_laboran_mesin_can_only_see_mesin_aset(): void
    {
        $response = $this->actingAs($this->userBudiMesin, 'api')
            ->getJson('/api/sinapra/aset');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($this->asetMesin->id, $data[0]['id']);
        $this->assertEquals('Mesin Bubut CNC 5 Axis', $data[0]['nama']);
    }

    /**
     * Wasis DILARANG mengakses/melihat detail aset prodi lain (Mesin) via show endpoint.
     */
    public function test_wasis_cannot_view_or_access_mesin_aset(): void
    {
        $response = $this->actingAs($this->userWasisTrpl, 'api')
            ->getJson("/api/sinapra/aset/{$this->asetMesin->id}");

        $response->assertStatus(403);
    }

    /**
     * Wasis DILARANG mengakses/melihat detail ruangan prodi lain (Mesin) via showRuangan endpoint.
     */
    public function test_wasis_cannot_view_or_access_mesin_ruangan(): void
    {
        $response = $this->actingAs($this->userWasisTrpl, 'api')
            ->getJson("/api/sinapra/ruangan/{$this->ruanganMesin->id}");

        $response->assertStatus(403);
    }

    /**
     * Admin Sarpras Global dapat melihat seluruh ruangan dan aset tanpa pembatasan prodi.
     */
    public function test_admin_sarpras_global_can_see_all_data(): void
    {
        $responseRuangan = $this->actingAs($this->userAdminSarpras, 'api')
            ->getJson('/api/sinapra/ruangan');

        $responseRuangan->assertStatus(200);
        $this->assertCount(2, $responseRuangan->json('data'));

        $responseAset = $this->actingAs($this->userAdminSarpras, 'api')
            ->getJson('/api/sinapra/aset');

        $responseAset->assertStatus(200);
        $this->assertCount(2, $responseAset->json('data'));
    }

    /**
     * Ringkasan Dashboard SINAPRA otomatis ter-scope sesuai prodi binaan laboran.
     */
    public function test_dashboard_summary_is_scoped_for_wasis(): void
    {
        $response = $this->actingAs($this->userWasisTrpl, 'api')
            ->getJson('/api/sinapra/dashboard-summary');

        $response->assertStatus(200);
        $metrics = $response->json('data.metrics');

        // Total ruangan & aset Wasis hanya 1 (TRPL)
        $this->assertEquals(1, $metrics['total_ruangan']);
        $this->assertEquals(1, $metrics['total_aset']);
        $this->assertEquals(35000000, $metrics['total_harga_perolehan']);
    }
}
