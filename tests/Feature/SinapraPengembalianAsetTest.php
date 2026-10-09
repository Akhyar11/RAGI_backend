<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\MaintenanceLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use App\Models\PeminjamanAset;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraPengembalianAsetTest extends TestCase
{
    protected User $adminSinapra;
    protected User $mahasiswaUser;
    protected Ruangan $ruangan;
    protected Aset $aset1;
    protected Aset $aset2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $mhsRole = Role::firstOrCreate(
            ['slug' => 'mahasiswa'],
            ['name' => 'Mahasiswa', 'is_active' => true]
        );

        $perms = [
            'sinapra.peminjaman_aset.read' => 'read',
            'sinapra.peminjaman_aset.create' => 'create',
            'sinapra.peminjaman_aset.approve' => 'approve',
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
        $mhsRole->permissions()->sync([
            $permIds['sinapra.peminjaman_aset.read'],
            $permIds['sinapra.peminjaman_aset.create'],
        ]);

        $this->adminSinapra = User::factory()->create(['email' => 'admin_kembali_' . uniqid() . '@kampus.ac.id']);
        $this->adminSinapra->roles()->sync([$adminRole->id]);

        $this->mahasiswaUser = User::factory()->create(['email' => 'mhs_kembali_' . uniqid() . '@kampus.ac.id']);
        $this->mahasiswaUser->roles()->sync([$mhsRole->id]);

        $gedung = Gedung::create([
            'kode' => 'GD-KMB-' . uniqid(),
            'nama' => 'Gedung Laboratorium Terpadu',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-KMB-' . uniqid(),
            'nama' => 'Ruang Peralatan',
            'lantai' => 1,
            'status' => 'aktif',
        ]);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-ELK-' . uniqid(),
            'nama' => 'Elektronik & Multimedia',
            'masa_manfaat_tahun' => 4,
            'metode_penyusutan' => 'garis_lurus',
        ]);

        $this->aset1 = Aset::create([
            'kode_aset' => 'AST-PROJ-' . uniqid(),
            'nama' => 'Proyektor Epson EB-X400',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'tanggal_perolehan' => '2025-01-10',
            'harga_perolehan' => 6500000,
            'nilai_sisa' => 500000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
        ]);

        $this->aset2 = Aset::create([
            'kode_aset' => 'AST-MIC-' . uniqid(),
            'nama' => 'Wireless Mic Shure',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'tanggal_perolehan' => '2025-01-10',
            'harga_perolehan' => 2500000,
            'nilai_sisa' => 200000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
        ]);
    }

    public function test_apply_peminjaman_aset_prevents_date_collision(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Pengajuan pertama
        $res1 = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset1->id,
            'keperluan' => 'Workshop Multimedia',
            'tanggal_pinjam' => '2026-10-15',
            'tanggal_kembali_rencana' => '2026-10-20',
        ]);
        $res1->assertStatus(201);

        // Pengajuan kedua pada tanggal beririsan harus ditolak
        $res2 = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset1->id,
            'keperluan' => 'Seminar BEM',
            'tanggal_pinjam' => '2026-10-18',
            'tanggal_kembali_rencana' => '2026-10-22',
        ]);
        $res2->assertStatus(500);
        $this->assertStringContainsString('sudah diajukan atau sedang dipinjam', $res2->json('message'));
    }

    public function test_approve_aset_changes_status_to_dipinjam(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        $res = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset1->id,
            'keperluan' => 'Kuliah Tamu',
            'tanggal_pinjam' => '2026-11-01',
            'tanggal_kembali_rencana' => '2026-11-03',
        ]);
        $res->assertStatus(201);
        $peminjamanId = $res->json('data.id');

        Passport::actingAs($this->adminSinapra);

        $approveRes = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);
        $approveRes->assertStatus(200);

        // Status aset fisik di tabel sinapra_aset wajib berubah menjadi dipinjam
        $this->aset1->refresh();
        $this->assertEquals('dipinjam', $this->aset1->status);
    }

    public function test_pengembalian_aset_baik_restores_status_tersedia(): void
    {
        Passport::actingAs($this->mahasiswaUser);
        $res = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset1->id,
            'keperluan' => 'Presentasi Tugas Akhir',
            'tanggal_pinjam' => '2026-12-01',
            'tanggal_kembali_rencana' => '2026-12-02',
        ]);
        $peminjamanId = $res->json('data.id');

        Passport::actingAs($this->adminSinapra);
        $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve", ['is_approved' => true]);

        // Proses pengembalian barang dalam kondisi baik
        $kembaliRes = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/kembalikan", [
            'kondisi_kembali' => 'baik',
            'tanggal_kembali_aktual' => '2026-12-02',
            'catatan_pengembalian' => 'Barang kembali lengkap dengan remote dan kabel HDMI.',
        ]);
        $kembaliRes->assertStatus(200);

        $this->aset1->refresh();
        $this->assertEquals('tersedia', $this->aset1->status);
        $this->assertEquals('baik', $this->aset1->kondisi);

        $peminjaman = PeminjamanAset::find($peminjamanId);
        $this->assertEquals('kembali', $peminjaman->status);
        $this->assertEquals('baik', $peminjaman->kondisi_kembali);
    }

    public function test_pengembalian_aset_rusak_berat_triggers_maintenance_log(): void
    {
        Passport::actingAs($this->mahasiswaUser);
        $res = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset2->id,
            'keperluan' => 'Acara Musik UKM',
            'tanggal_pinjam' => '2026-12-10',
            'tanggal_kembali_rencana' => '2026-12-11',
        ]);
        $peminjamanId = $res->json('data.id');

        Passport::actingAs($this->adminSinapra);
        $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve", ['is_approved' => true]);

        // Proses pengembalian dengan kondisi rusak berat
        $kembaliRes = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/kembalikan", [
            'kondisi_kembali' => 'rusak_berat',
            'tanggal_kembali_aktual' => '2026-12-11',
            'catatan_pengembalian' => 'Mic terjatuh dan komponen transmitter patah tidak bersuara.',
        ]);
        $kembaliRes->assertStatus(200);

        // Status aset fisik menjadi maintenance
        $this->aset2->refresh();
        $this->assertEquals('maintenance', $this->aset2->status);
        $this->assertEquals('rusak_berat', $this->aset2->kondisi);

        // Record tiket perawatan otomatis terbuat di sinapra_maintenance_log
        $log = MaintenanceLog::where('aset_id', $this->aset2->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Perbaikan Pengembalian Peminjaman', $log->judul);
        $this->assertEquals('tinggi', $log->prioritas);
        $this->assertEquals('dilaporkan', $log->status);
    }
}
