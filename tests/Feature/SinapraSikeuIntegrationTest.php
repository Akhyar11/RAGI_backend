<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\PengajuanPengadaan;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\UnitKas;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinapraSikeuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKerja $unitKerja;
    protected KategoriAset $kategori;
    protected Ruangan $ruangan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Administrator', 'is_active' => true]);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->unitKerja = UnitKerja::create([
            'nama' => 'Biro Sarana & Prasarana',
            'kode' => 'BSP',
            'tipe' => 'biro',
            'is_active' => true,
        ]);

        $this->kategori = KategoriAset::create([
            'nama' => 'Peralatan Laboratorium Komputer',
            'kode' => 'LABKOM',
            'umur_ekonomis' => 4,
            'tarif_penyusutan_persen' => 25.0,
        ]);

        $gedung = Gedung::create([
            'nama' => 'Gedung TI Lt. 2',
            'kode' => 'TI-2',
            'jumlah_lantai' => 3,
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'nama' => 'Lab Rekayasa Perangkat Lunak',
            'kode' => 'LAB-RPL',
            'lantai' => 2,
            'kapasitas' => 30,
        ]);
    }

    public function test_pengadaan_approval_automatically_creates_sikeu_pencairan_kas_with_items()
    {
        // 1. Buat pengajuan pengadaan dengan 2 detail barang
        $pengadaan = PengajuanPengadaan::create([
            'unit_kerja_id' => $this->unitKerja->id,
            'diajukan_oleh' => $this->admin->id,
            'judul' => 'Pengadaan 10 Unit PC Workstation Lab RPL',
            'alasan_kebutuhan' => 'Kebutuhan praktikum semester ganjil',
            'tanggal_pengajuan' => now()->toDateString(),
            'estimasi_anggaran' => 150000000,
            'status' => 'diajukan',
        ]);

        $pengadaan->details()->create([
            'kategori_aset_id' => $this->kategori->id,
            'nama_barang' => 'PC Workstation Core i7',
            'spesifikasi' => 'RAM 32GB SSD 1TB RTX 4060',
            'jumlah' => 10,
            'harga_satuan_estimasi' => 14000000,
            'total_estimasi' => 140000000,
        ]);

        $pengadaan->details()->create([
            'kategori_aset_id' => $this->kategori->id,
            'nama_barang' => 'Switch Gigabit 24 Port',
            'spesifikasi' => 'Manageable Switch L2',
            'jumlah' => 2,
            'harga_satuan_estimasi' => 5000000,
            'total_estimasi' => 10000000,
        ]);

        // 2. Setujui pengadaan via endpoint
        $response = $this->actingAs($this->admin, 'api')
            ->patchJson("/api/sinapra/pengadaan/{$pengadaan->id}/status", [
                'status' => 'disetujui',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $pengadaan->refresh();
        $this->assertEquals('disetujui', $pengadaan->status);
        $this->assertNotNull($pengadaan->sikeu_pencairan_id);

        // 3. Verifikasi tiket pencairan kas di SIKEU terbentuk otomatis
        $pencairan = PengajuanPencairanKas::find($pengadaan->sikeu_pencairan_id);
        $this->assertNotNull($pencairan);
        $this->assertEquals('sinapra_pengadaan', $pencairan->kanal);
        $this->assertEquals('pending_keuangan', $pencairan->status);
        $this->assertEquals(150000000, (float) $pencairan->nominal_diajukan);
        $this->assertCount(2, $pencairan->items);
    }

    public function test_sikeu_disbursement_syncs_sinapra_pengadaan_status_to_proses_pengadaan()
    {
        $pengadaan = PengajuanPengadaan::create([
            'unit_kerja_id' => $this->unitKerja->id,
            'diajukan_oleh' => $this->admin->id,
            'judul' => 'Pengadaan Server Cloud Kampus',
            'alasan_kebutuhan' => 'Server database utama',
            'tanggal_pengajuan' => now()->toDateString(),
            'estimasi_anggaran' => 85000000,
            'status' => 'disetujui',
        ]);

        $unitKas = UnitKas::create([
            'nama_kas' => 'Kas Operasional Rektorat',
            'saldo_saat_ini' => 200000000,
            'status' => true,
        ]);

        $pencairan = PengajuanPencairanKas::create([
            'nomor_pengajuan' => 'OPR-TEST-001',
            'unit_kerja_id' => $this->unitKerja->id,
            'unit_kas_id' => $unitKas->id,
            'pemohon_id' => $this->admin->id,
            'judul_pengajuan' => 'Pengadaan Server Cloud Kampus',
            'nominal_diajukan' => 85000000,
            'nominal_disetujui' => 85000000,
            'jenis_pengajuan' => 'sarpras',
            'kategori_pengajuan' => 'pengadaan_barang',
            'status' => 'disetujui',
            'kanal' => 'sinapra_pengadaan',
            'referensi_eksternal' => 'sinapra_pengadaan:' . $pengadaan->id,
        ]);

        $pengadaan->update(['sikeu_pencairan_id' => $pencairan->id]);

        // Eksekusi pencairan melalui service SIKEU
        $sikeuService = app(\App\Services\Sikeu\PengajuanOperasionalService::class);
        $sikeuService->cairkan($pencairan, [
            'nominal_cair' => 85000000,
            'tanggal_pencairan' => now()->toDateString(),
        ]);

        $pengadaan->refresh();
        $this->assertEquals('proses_pengadaan', $pengadaan->status);
    }

    public function test_can_post_jurnal_penyusutan_aset_to_sikeu()
    {
        $aset = Aset::create([
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'kode_aset' => 'AST-LAB-SERVER',
            'nama' => 'Server Virtualisasi Proxmox',
            'tanggal_perolehan' => '2025-01-01',
            'harga_perolehan' => 100000000,
            'nilai_buku' => 100000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        $tahun = 2026;
        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/sinapra/aset/{$aset->id}/post-jurnal-penyusutan", [
                'tahun' => $tahun,
                'catatan' => 'Penyusutan akhir tahun buku 2026',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Beban = 25% * 100.000.000 = 25.000.000
        $this->assertDatabaseHas('sinapra_riwayat_penyusutan_aset', [
            'aset_id' => $aset->id,
            'periode_tahun' => $tahun,
            'beban_penyusutan' => 25000000,
            'nilai_buku_setelah' => 75000000,
        ]);

        $aset->refresh();
        $this->assertEquals(75000000, (float) $aset->nilai_buku);

        // Verifikasi Jurnal Umum SIKEU terbentuk (Double-Entry Debit 505.01 / Kredit 105.01)
        $this->assertDatabaseHas('sikeu_jurnal_umum', [
            'jenis_sumber' => 'penyesuaian',
            'referensi_id' => $aset->id,
            'total_debet' => 25000000,
            'total_kredit' => 25000000,
        ]);
    }

    public function test_cannot_post_duplicate_jurnal_penyusutan_for_same_year()
    {
        $aset = Aset::create([
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'kode_aset' => 'AST-LAB-ROUTER',
            'nama' => 'Router Mikrotik CCR1036',
            'tanggal_perolehan' => '2025-01-01',
            'harga_perolehan' => 20000000,
            'nilai_buku' => 20000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        // Posting pertama berhasil
        $response1 = $this->actingAs($this->admin, 'api')
            ->postJson("/api/sinapra/aset/{$aset->id}/post-jurnal-penyusutan", [
                'tahun' => 2026,
            ]);
        $response1->assertStatus(201);

        // Posting kedua untuk tahun yang sama harus ditolak
        $response2 = $this->actingAs($this->admin, 'api')
            ->postJson("/api/sinapra/aset/{$aset->id}/post-jurnal-penyusutan", [
                'tahun' => 2026,
            ]);
        $response2->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }
}
