<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\LaboranProdi;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraLaboranProdiIntegrationTest extends TestCase
{
    protected User $adminSarpras;
    protected User $laboranTI;
    protected ProgramStudi $prodiTI;
    protected ProgramStudi $prodiElektro;
    protected Ruangan $ruanganTI;
    protected Ruangan $ruanganElektro;
    protected Aset $asetTI;
    protected Aset $asetElektro;

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
        $pDashboard = Permission::firstOrCreate(
            ['slug' => 'sinapra.dashboard.read'],
            ['name' => 'Lihat Dashboard', 'module' => 'sinapra', 'action' => 'read']
        );

        $adminSarprasRole->permissions()->sync([$pManage->id, $pRuanganRead->id, $pAsetRead->id, $pDashboard->id]);
        $laboranRole->permissions()->sync([$pRuanganRead->id, $pAsetRead->id, $pDashboard->id]);

        $this->adminSarpras = User::factory()->create();
        $this->adminSarpras->roles()->sync([$adminSarprasRole->id]);

        $this->laboranTI = User::factory()->create();
        $this->laboranTI->roles()->sync([$laboranRole->id]);

        // Create Program Studi
        $this->prodiTI = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI-' . uniqid()],
            ['nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]
        );

        $this->prodiElektro = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'EL-' . uniqid()],
            ['nama' => 'Teknik Elektro', 'jenjang' => 'S1', 'is_active' => true]
        );

        // Create Gedung & Kategori Aset
        $gedung = Gedung::create([
            'kode' => 'GDG-' . uniqid(),
            'nama' => 'Gedung Lab Fakultas',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-' . uniqid(),
            'nama' => 'Perangkat Komputer',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25,
        ]);

        // Create Ruangan per Prodi
        $this->ruanganTI = Ruangan::create([
            'gedung_id' => $gedung->id,
            'program_studi_id' => $this->prodiTI->id,
            'kode' => 'LAB-TI-' . uniqid(),
            'nama' => 'Lab Software Engineering TI',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 35,
            'status' => 'aktif',
        ]);

        $this->ruanganElektro = Ruangan::create([
            'gedung_id' => $gedung->id,
            'program_studi_id' => $this->prodiElektro->id,
            'kode' => 'LAB-EL-' . uniqid(),
            'nama' => 'Lab Sistem Tertanam Elektro',
            'lantai' => 2,
            'tipe' => 'lab',
            'kapasitas' => 25,
            'status' => 'aktif',
        ]);

        // Create Aset per Prodi
        $this->asetTI = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruanganTI->id,
            'program_studi_id' => $this->prodiTI->id,
            'kode_aset' => 'AST-TI-' . uniqid(),
            'nama' => 'Server High-Performance TI',
            'harga_perolehan' => 25000000,
            'nilai_buku' => 20000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);

        $this->asetElektro = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruanganElektro->id,
            'program_studi_id' => $this->prodiElektro->id,
            'kode_aset' => 'AST-EL-' . uniqid(),
            'nama' => 'Oscilloscope Digital Elektro',
            'harga_perolehan' => 18000000,
            'nilai_buku' => 15000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => true,
        ]);
    }

    /**
     * Test Admin Sarpras dapat menugaskan dan menghapus penugasan laboran ke prodi.
     */
    public function test_admin_sarpras_can_assign_and_unassign_laboran_to_prodi(): void
    {
        Passport::actingAs($this->adminSarpras);

        // Assign laboran ke Prodi TI
        $response = $this->postJson('/api/sinapra/laboran-prodi', [
            'user_id' => $this->laboranTI->id,
            'program_studi_id' => $this->prodiTI->id,
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Laboran berhasil ditugaskan ke program studi',
            ]);

        $this->assertDatabaseHas('sinapra_laboran_prodi', [
            'user_id' => $this->laboranTI->id,
            'program_studi_id' => $this->prodiTI->id,
        ]);

        $assignmentId = $response->json('data.id');

        // Unassign laboran
        $delResponse = $this->deleteJson("/api/sinapra/laboran-prodi/{$assignmentId}");
        $delResponse->assertStatus(200);

        $this->assertDatabaseMissing('sinapra_laboran_prodi', [
            'id' => $assignmentId,
        ]);
    }

    /**
     * Test isolasi akses: Laboran Prodi TI hanya dapat melihat ruangan & aset Prodi TI.
     */
    public function test_laboran_prodi_scoped_access_to_assets_and_rooms(): void
    {
        // Tugaskan laboran ke prodi TI
        LaboranProdi::create([
            'user_id' => $this->laboranTI->id,
            'program_studi_id' => $this->prodiTI->id,
            'is_primary' => true,
        ]);

        Passport::actingAs($this->laboranTI);

        // Cek Ruangan: Harus hanya memuat ruangan TI
        $resRuangan = $this->getJson('/api/sinapra/ruangan');
        $resRuangan->assertStatus(200);

        $ruanganCodes = collect($resRuangan->json('data'))->pluck('kode');
        $this->assertTrue($ruanganCodes->contains($this->ruanganTI->kode));
        $this->assertFalse($ruanganCodes->contains($this->ruanganElektro->kode));

        // Cek Aset: Harus hanya memuat aset TI
        $resAset = $this->getJson('/api/sinapra/aset');
        $resAset->assertStatus(200);

        $asetCodes = collect($resAset->json('data'))->pluck('kode_aset');
        $this->assertTrue($asetCodes->contains($this->asetTI->kode_aset));
        $this->assertFalse($asetCodes->contains($this->asetElektro->kode_aset));
    }

    /**
     * Test Admin Sarpras dapat melakukan filter ruangan dan aset berdasarkan program_studi_id.
     */
    public function test_admin_sarpras_filter_by_program_studi(): void
    {
        Passport::actingAs($this->adminSarpras);

        // Filter ruangan Prodi Elektro
        $resRuangan = $this->getJson('/api/sinapra/ruangan?program_studi_id=' . $this->prodiElektro->id);
        $resRuangan->assertStatus(200);
        $ruanganCodes = collect($resRuangan->json('data'))->pluck('kode');
        $this->assertTrue($ruanganCodes->contains($this->ruanganElektro->kode));
        $this->assertFalse($ruanganCodes->contains($this->ruanganTI->kode));

        // Filter aset Prodi TI
        $resAset = $this->getJson('/api/sinapra/aset?program_studi_id=' . $this->prodiTI->id);
        $resAset->assertStatus(200);
        $asetCodes = collect($resAset->json('data'))->pluck('kode_aset');
        $this->assertTrue($asetCodes->contains($this->asetTI->kode_aset));
        $this->assertFalse($asetCodes->contains($this->asetElektro->kode_aset));
    }

    /**
     * Test Dashboard SINAPRA memuat agregasi distribusi per Program Studi.
     */
    public function test_dashboard_summary_contains_distribusi_prodi(): void
    {
        Passport::actingAs($this->adminSarpras);

        $res = $this->getJson('/api/sinapra/dashboard-summary');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'metrics',
                    'breakdown_aset',
                    'distribusi_prodi' => [
                        'prodi_list',
                        'fasilitas_umum' => [
                            'nama',
                            'total_aset',
                            'total_ruangan',
                            'total_nilai_aset',
                        ],
                    ],
                    'early_warnings',
                    'recent_activities',
                ],
            ]);
    }
}
