<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\MaintenanceLog;
use App\Models\PengajuanPengadaan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraMaintenancePengadaanTest extends TestCase
{
    protected User $adminSinapra;
    protected User $laboranUser;
    protected User $otherLaboran;

    protected Ruangan $labTrpl;
    protected Ruangan $labElektro;
    protected Aset $asetLabTrpl;
    protected Aset $asetLabElektro;
    protected UnitKerja $unitTrpl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        // Roles
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $laboranRole = Role::firstOrCreate(
            ['slug' => 'admin_laboratorium'],
            ['name' => 'Admin Laboratorium', 'is_active' => true]
        );

        $perms = [
            'sinapra.maintenance.read' => 'read',
            'sinapra.maintenance.create' => 'create',
            'sinapra.maintenance.update' => 'update',
            'sinapra.maintenance.delete' => 'delete',
            'sinapra.pengadaan.read' => 'read',
            'sinapra.pengadaan.create' => 'create',
            'sinapra.pengadaan.approve' => 'approve',
            'sinapra.pengadaan.delete' => 'delete',
            'sinapra.aset.read' => 'read',
        ];

        $permIds = [];
        foreach ($perms as $slug => $action) {
            $p = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'sinapra', 'action' => $action]
            );
            $permIds[$slug] = $p->id;
        }

        $adminRole->permissions()->sync(array_values($permIds));
        $laboranRole->permissions()->sync([
            $permIds['sinapra.maintenance.read'],
            $permIds['sinapra.maintenance.create'],
            $permIds['sinapra.maintenance.update'],
            $permIds['sinapra.pengadaan.read'],
            $permIds['sinapra.pengadaan.create'],
            $permIds['sinapra.aset.read'],
        ]);

        $this->adminSinapra = User::factory()->create();
        $this->adminSinapra->roles()->sync([$adminRole->id]);

        $this->laboranUser = User::factory()->create();
        $this->laboranUser->roles()->sync([$laboranRole->id]);

        $this->otherLaboran = User::factory()->create();
        $this->otherLaboran->roles()->sync([$laboranRole->id]);

        // Gedung & Ruangan
        $gedung = Gedung::create([
            'kode' => 'GDG-MNT-' . uniqid(),
            'nama' => 'Gedung Lab Phase 3',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $this->labTrpl = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-TRPL-' . uniqid(),
            'nama' => 'Lab Software Engineering',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 35,
            'status' => 'aktif',
        ]);

        $this->labElektro = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-ELK-' . uniqid(),
            'nama' => 'Lab Hardware & IoT',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 30,
            'status' => 'aktif',
        ]);

        // Penugasan laboranUser ke labTrpl
        $this->labTrpl->laboran()->attach($this->laboranUser->id, ['is_primary' => true]);

        // Kategori & Aset
        $kategori = KategoriAset::create([
            'kode' => 'KAT-MNT-' . uniqid(),
            'nama' => 'Peralatan Lab & IT',
            'masa_manfaat_tahun' => 5,
            'tarif_penyusutan_persen' => 20,
        ]);

        $this->asetLabTrpl = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labTrpl->id,
            'kode_aset' => 'AST-TRPL-' . uniqid(),
            'nama' => 'Server AI Workstation',
            'harga_perolehan' => 45000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);

        $this->asetLabElektro = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labElektro->id,
            'kode_aset' => 'AST-ELK-' . uniqid(),
            'nama' => 'Spectrum Analyzer Keysight',
            'harga_perolehan' => 60000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);

        // Unit Kerja
        $this->unitTrpl = UnitKerja::create([
            'kode' => 'PRODI-TRPL',
            'nama' => 'Program Studi TRPL',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);
    }

    public function test_laboran_only_sees_maintenance_logs_in_assigned_lab(): void
    {
        // Buat log perbaikan di Lab TRPL
        $logTrpl = MaintenanceLog::create([
            'aset_id' => $this->asetLabTrpl->id,
            'ruangan_id' => $this->labTrpl->id,
            'judul' => 'Perbaikan Power Supply Server TRPL',
            'deskripsi_kerusakan' => 'Server mati mendadak saat kompilasi model',
            'prioritas' => 'tinggi',
            'status' => 'dilaporkan',
        ]);

        // Buat log perbaikan di Lab Elektro
        $logElektro = MaintenanceLog::create([
            'aset_id' => $this->asetLabElektro->id,
            'ruangan_id' => $this->labElektro->id,
            'judul' => 'Kalibrasi Spectrum Analyzer Elektro',
            'deskripsi_kerusakan' => 'Frekuensi drifting',
            'prioritas' => 'sedang',
            'status' => 'dilaporkan',
        ]);

        // Login sebagai laboranUser (penanggung jawab Lab TRPL)
        Passport::actingAs($this->laboranUser);

        $res = $this->getJson('/api/sinapra/maintenance');
        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $logIds = collect($res->json('data'))->pluck('id');
        $this->assertTrue($logIds->contains($logTrpl->id));
        $this->assertFalse($logIds->contains($logElektro->id));
    }

    public function test_maintenance_ticket_updates_asset_status_and_restores_on_complete(): void
    {
        Passport::actingAs($this->laboranUser);

        // Buat tiket maintenance untuk server
        $createRes = $this->postJson('/api/sinapra/maintenance', [
            'aset_id' => $this->asetLabTrpl->id,
            'judul' => 'Ganti Pasta Termal CPU Server',
            'deskripsi_kerusakan' => 'Overheating hingga 95 derajat',
            'prioritas' => 'sedang',
        ]);

        $createRes->assertStatus(201);
        $logId = $createRes->json('data.id');

        // Status aset otomatis berubah menjadi 'maintenance'
        $this->assertEquals('maintenance', $this->asetLabTrpl->fresh()->status);

        // Update tiket menjadi 'selesai'
        Passport::actingAs($this->adminSinapra);
        $updateRes = $this->putJson("/api/sinapra/maintenance/{$logId}", [
            'status' => 'selesai',
            'judul' => 'Ganti Pasta Termal CPU Server Selesai',
            'deskripsi_kerusakan' => 'Overheating selesai ditangani',
            'prioritas' => 'sedang',
            'hasil_perbaikan' => 'Suhu stabil di 45 derajat',
            'biaya' => 150000,
        ]);

        $updateRes->assertStatus(200);

        // Status aset kembali menjadi 'tersedia'
        $this->assertEquals('tersedia', $this->asetLabTrpl->fresh()->status);
    }

    public function test_pengadaan_barang_workflow(): void
    {
        Passport::actingAs($this->laboranUser);

        $kategoriId = $this->asetLabTrpl->kategori_id;

        // Ajukan pengadaan barang
        $resPengadaan = $this->postJson('/api/sinapra/pengadaan', [
            'unit_kerja_id' => $this->unitTrpl->id,
            'judul' => 'Pengadaan Kit Mikrokontroler ESP32',
            'alasan_kebutuhan' => 'Kebutuhan praktikum embedded system semester genap',
            'details' => [
                [
                    'kategori_aset_id' => $kategoriId,
                    'nama_barang' => 'ESP32 NodeMCU Board',
                    'spesifikasi' => 'Dual Core WiFi Bluetooth',
                    'jumlah' => 20,
                    'satuan' => 'Pcs',
                    'harga_satuan_estimasi' => 75000,
                ],
            ],
        ]);

        $resPengadaan->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.estimasi_anggaran', '1500000.00');

        $pengadaanId = $resPengadaan->json('data.id');

        // Admin approve pengadaan
        Passport::actingAs($this->adminSinapra);
        $resApprove = $this->patchJson("/api/sinapra/pengadaan/{$pengadaanId}/status", [
            'status' => 'disetujui',
        ]);

        $resApprove->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');
    }
}
