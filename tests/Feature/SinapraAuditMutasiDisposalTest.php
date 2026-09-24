<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DisposalAset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\MutasiAset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraAuditMutasiDisposalTest extends TestCase
{
    protected User $adminSarpras;
    protected User $laboranTrpl;
    protected User $laboranElektro;

    protected Ruangan $labTrpl;
    protected Ruangan $labElektro;
    protected Aset $asetLabTrpl;
    protected Aset $asetDisposalTarget;

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
            'sinapra.opname.read' => 'read',
            'sinapra.opname.manage' => 'update',
            'sinapra.mutasi.read' => 'read',
            'sinapra.mutasi.manage' => 'update',
            'sinapra.disposal.read' => 'read',
            'sinapra.disposal.manage' => 'update',
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
        $laboranRole->permissions()->sync(array_values($permIds));

        $this->adminSarpras = User::factory()->create();
        $this->adminSarpras->roles()->sync([$adminRole->id]);

        $this->laboranTrpl = User::factory()->create();
        $this->laboranTrpl->roles()->sync([$laboranRole->id]);

        $this->laboranElektro = User::factory()->create();
        $this->laboranElektro->roles()->sync([$laboranRole->id]);

        // Fasilitas Lab
        $gedung = Gedung::create([
            'kode' => 'GDG-LAB-' . uniqid(),
            'nama' => 'Gedung Laboratorium Terpadu',
            'jumlah_lantai' => 3,
            'status' => 'aktif',
        ]);

        $this->labTrpl = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-TRPL-' . uniqid(),
            'nama' => 'Laboratorium Rekayasa Perangkat Lunak',
            'lantai' => 2,
            'tipe' => 'lab',
            'kapasitas' => 40,
            'status' => 'aktif',
        ]);

        $this->labElektro = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-ELK-' . uniqid(),
            'nama' => 'Laboratorium Elektronika & Instrumentasi',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 30,
            'status' => 'aktif',
        ]);

        // Hubungkan laboran ke ruangan
        $this->labTrpl->laboran()->attach($this->laboranTrpl->id, ['is_primary' => true]);
        $this->labElektro->laboran()->attach($this->laboranElektro->id, ['is_primary' => true]);

        // Kategori & Aset
        $kategori = KategoriAset::create([
            'kode' => 'KAT-LAB-' . uniqid(),
            'nama' => 'Peralatan Lab Komputer',
            'masa_manfaat_tahun' => 5,
            'tarif_penyusutan_persen' => 20,
        ]);

        $this->asetLabTrpl = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labTrpl->id,
            'kode_aset' => 'AST-PC-' . uniqid(),
            'nama' => 'Workstation AI Lab TRPL',
            'harga_perolehan' => 25000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => true,
        ]);

        $this->asetDisposalTarget = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labTrpl->id,
            'kode_aset' => 'AST-OLD-' . uniqid(),
            'nama' => 'Printer Inkjet Rusak Parah',
            'harga_perolehan' => 3000000,
            'kondisi' => 'rusak_berat',
            'status' => 'maintenance',
            'is_borrowable' => false,
            'is_lab_asset' => true,
        ]);
    }

    public function test_stock_opname_flow_and_laboran_scoping(): void
    {
        // 1. Laboran TRPL dilarang membuat stock opname di Lab Elektro
        Passport::actingAs($this->laboranTrpl);
        $resForbidden = $this->postJson('/api/sinapra/stock-opname', [
            'ruangan_id' => $this->labElektro->id,
            'catatan' => 'Audit ilegal',
        ]);
        $resForbidden->assertStatus(403);

        // 2. Laboran TRPL membuat sesi stock opname di Lab binaannya
        $resCreate = $this->postJson('/api/sinapra/stock-opname', [
            'ruangan_id' => $this->labTrpl->id,
            'catatan' => 'Audit fisik semester genap',
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $opnameId = $resCreate->json('data.id');
        $this->assertDatabaseHas('sinapra_stock_opname', [
            'id' => $opnameId,
            'ruangan_id' => $this->labTrpl->id,
            'status' => 'berlangsung',
        ]);

        // Verifikasi auto-snapshot aset ke checklist opname
        $opnameItem = StockOpnameItem::where('stock_opname_id', $opnameId)
            ->where('aset_id', $this->asetLabTrpl->id)
            ->first();
        $this->assertNotNull($opnameItem);
        $this->assertEquals('sesuai', $opnameItem->status_keberadaan);

        // 3. Update checklist pemeriksaan fisik item
        $resUpdateItem = $this->putJson("/api/sinapra/stock-opname/{$opnameId}/items/{$opnameItem->id}", [
            'status_keberadaan' => 'rusak',
            'kondisi_fisik' => 'rusak_berat',
            'catatan' => 'Kabel power putus dan korslet',
        ]);
        $resUpdateItem->assertStatus(200)
            ->assertJsonPath('data.status_keberadaan', 'rusak');

        // 4. Tutup / Selesaikan sesi stock opname
        $resFinish = $this->postJson("/api/sinapra/stock-opname/{$opnameId}/finish", [
            'catatan' => 'Audit selesai dan diverifikasi',
        ]);
        $resFinish->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // Aset di tabel master harus tersinkronisasi menjadi maintenance
        $this->asetLabTrpl->refresh();
        $this->assertEquals('maintenance', $this->asetLabTrpl->status);
        $this->assertEquals('rusak_berat', $this->asetLabTrpl->kondisi);
    }

    public function test_mutasi_aset_flow_and_approval(): void
    {
        // 1. Laboran TRPL mengajukan mutasi Workstation ke Lab Elektro
        Passport::actingAs($this->laboranTrpl);
        $resMutasi = $this->postJson('/api/sinapra/mutasi-aset', [
            'aset_id' => $this->asetLabTrpl->id,
            'ruangan_tujuan_id' => $this->labElektro->id,
            'alasan' => 'Dibutuhkan untuk praktikum mikrokontroler lanjutan',
            'catatan' => 'Lengkap dengan monitor dual',
        ]);

        $resMutasi->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $mutasiId = $resMutasi->json('data.id');
        $this->assertDatabaseHas('sinapra_mutasi_aset', [
            'id' => $mutasiId,
            'status' => 'diajukan',
        ]);

        // 2. Laboran TRPL (pemohon/asal) TIDAK BERHAK menyetujui mutasi
        $resIllegalApprove = $this->postJson("/api/sinapra/mutasi-aset/{$mutasiId}/approve", [
            'is_approved' => true,
            'catatan' => 'Saya setujui sendiri',
        ]);
        $resIllegalApprove->assertStatus(403);

        // 3. Laboran Elektro (tujuan penerima) menyetujui mutasi
        Passport::actingAs($this->laboranElektro);
        $resApprove = $this->postJson("/api/sinapra/mutasi-aset/{$mutasiId}/approve", [
            'is_approved' => true,
            'catatan' => 'Barang diterima dan dicek fungsi di Lab Elektro',
        ]);
        $resApprove->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        // Pastikan ruangan_id di aset telah berpindah ke Lab Elektro
        $this->asetLabTrpl->refresh();
        $this->assertEquals($this->labElektro->id, $this->asetLabTrpl->ruangan_id);
    }

    public function test_disposal_aset_flow_and_approval(): void
    {
        // 1. Laboran mengajukan usulan pemutihan aset rusak
        Passport::actingAs($this->laboranTrpl);
        $resDisposal = $this->postJson('/api/sinapra/disposal-aset', [
            'aset_id' => $this->asetDisposalTarget->id,
            'metode_disposal' => 'rusak_total',
            'nilai_residu' => 50000,
            'alasan' => 'Mainboard terbakar dan berkarat, tidak dapat diperbaiki',
            'catatan' => 'Sisa logam akan dilelang rongsok',
        ]);

        $resDisposal->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $disposalId = $resDisposal->json('data.id');
        $this->assertDatabaseHas('sinapra_disposal_aset', [
            'id' => $disposalId,
            'status' => 'diajukan',
        ]);

        // 2. Laboran dilarang memutuskan approval pemutihan aset (Khusus Sarpras/Superadmin)
        $resLaboranApprove = $this->postJson("/api/sinapra/disposal-aset/{$disposalId}/approve", [
            'is_approved' => true,
        ]);
        $resLaboranApprove->assertStatus(403);

        // 3. Admin Sarpras menyetujui usulan pemutihan
        Passport::actingAs($this->adminSarpras);
        $resAdminApprove = $this->postJson("/api/sinapra/disposal-aset/{$disposalId}/approve", [
            'is_approved' => true,
            'catatan' => 'Penghapusan aset telah disetujui Wakil Rektor II',
        ]);
        $resAdminApprove->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        // Status aset di tabel master harus menjadi 'dihapus' dan is_borrowable = false
        $this->asetDisposalTarget->refresh();
        $this->assertEquals('dihapus', $this->asetDisposalTarget->status);
        $this->assertFalse((bool) $this->asetDisposalTarget->is_borrowable);
    }
}
