<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Gedung;
use App\Models\Ruangan;
use App\Models\KategoriAset;
use App\Models\Aset;
use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\MaintenanceLog;
use App\Models\PengajuanPengadaan;
use App\Models\Simpeg\UnitKerja;
use Laravel\Passport\Passport;

class SinapraApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    private function createAdminUser()
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Admin Test',
            'slug' => 'admin',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_unauthenticated_user_cannot_access_sinapra()
    {
        $response = $this->getJson('/api/sinapra/gedung');
        $response->assertStatus(401);
    }

    public function test_admin_can_create_gedung_and_ruangan()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        // 1. Create Gedung
        $gedungPayload = [
            'kode' => 'GDG-TEST-01',
            'nama' => 'Gedung Rektorat Test',
            'jumlah_lantai' => 4,
            'alamat' => 'Jl. Campus Utama No 1',
            'status' => 'aktif',
        ];

        $responseGedung = $this->postJson('/api/sinapra/gedung', $gedungPayload);
        $responseGedung->assertStatus(201)
                       ->assertJsonPath('status', 'success')
                       ->assertJsonPath('data.kode', 'GDG-TEST-01');

        $this->assertDatabaseHas('gedung', ['kode' => 'GDG-TEST-01']);
        $gedungId = $responseGedung->json('data.id');

        // 2. Create Ruangan
        $ruanganPayload = [
            'gedung_id' => $gedungId,
            'kode' => 'R-TEST-101',
            'nama' => 'Ruang Server Test',
            'lantai' => 1,
            'tipe' => 'kantor',
            'kapasitas' => 20,
            'ada_ac' => true,
            'ada_proyektor' => true,
            'ada_wifi' => true,
            'status' => 'aktif',
        ];

        $responseRuangan = $this->postJson('/api/sinapra/ruangan', $ruanganPayload);
        $responseRuangan->assertStatus(201)
                         ->assertJsonPath('status', 'success')
                         ->assertJsonPath('data.kode', 'R-TEST-101');

        $this->assertDatabaseHas('ruangan', ['kode' => 'R-TEST-101']);
    }

    public function test_check_ruangan_ketersediaan_api()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $gedung = Gedung::create([
            'kode' => 'GDG-02',
            'nama' => 'Gedung Kuliah 2',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-201',
            'nama' => 'Ruang 201',
            'lantai' => 2,
            'tipe' => 'kelas',
            'kapasitas' => 40,
            'status' => 'aktif',
        ]);

        $payload = [
            'ruangan_id' => $ruangan->id,
            'tanggal' => now()->addDays(2)->toDateString(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
        ];

        $response = $this->postJson('/api/sinapra/ruangan/check-ketersediaan', $payload);
        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.is_available', true);
    }

    public function test_admin_can_create_kategori_and_aset()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        // 1. Kategori
        $kategoriPayload = [
            'kode' => 'KAT-TEST-01',
            'nama' => 'Elektronik Test',
            'masa_manfaat_tahun' => 5,
            'tarif_penyusutan_persen' => 20.0,
        ];

        $resKat = $this->postJson('/api/sinapra/kategori-aset', $kategoriPayload);
        $resKat->assertStatus(201)->assertJsonPath('data.kode', 'KAT-TEST-01');

        $kategoriId = $resKat->json('data.id');

        // 2. Aset
        $asetPayload = [
            'kategori_id' => $kategoriId,
            'kode_aset' => 'AST-TEST-999',
            'nama' => 'Laptop ThinkPad X1',
            'merk' => 'Lenovo',
            'harga_perolehan' => 20000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ];

        $resAset = $this->postJson('/api/sinapra/aset', $asetPayload);
        $resAset->assertStatus(201)->assertJsonPath('data.kode_aset', 'AST-TEST-999');

        $this->assertDatabaseHas('aset', ['kode_aset' => 'AST-TEST-999']);
    }

    public function test_hitung_penyusutan_aset_api()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-IT',
            'nama' => 'Perangkat Komputer',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25.0,
        ]);

        $aset = Aset::create([
            'kategori_id' => $kategori->id,
            'kode_aset' => 'AST-IT-001',
            'nama' => 'PC Workstation',
            'tanggal_perolehan' => now()->subYears(2)->toDateString(),
            'harga_perolehan' => 10000000,
            'nilai_buku' => 10000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        $response = $this->getJson("/api/sinapra/aset/{$aset->id}/hitung-penyusutan");
        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.aset_id', $aset->id)
                 ->assertJsonPath('data.nilai_buku_saat_ini', 5000000);
    }

    public function test_apply_and_approve_peminjaman_ruangan()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $gedung = Gedung::create([
            'kode' => 'GDG-03',
            'nama' => 'Gedung Serbaguna',
            'jumlah_lantai' => 1,
            'status' => 'aktif',
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-AULA',
            'nama' => 'Aula Utama',
            'lantai' => 1,
            'tipe' => 'aula',
            'kapasitas' => 500,
            'status' => 'aktif',
        ]);

        // 1. Apply
        $applyPayload = [
            'ruangan_id' => $ruangan->id,
            'keperluan' => 'Seminar Teknologi RAGI',
            'tanggal' => now()->addDays(5)->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
        ];

        $resApply = $this->postJson('/api/sinapra/peminjaman-ruangan', $applyPayload);
        $resApply->assertStatus(201)
                 ->assertJsonPath('data.status', 'pending');

        $peminjamanId = $resApply->json('data.id');

        // 2. Approve
        $approvePayload = [
            'is_approved' => true,
        ];

        $resApprove = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve", $approvePayload);
        $resApprove->assertStatus(200)
                   ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('peminjaman_ruangan', [
            'id' => $peminjamanId,
            'status' => 'disetujui',
        ]);
    }

    public function test_apply_approve_and_kembalikan_peminjaman_aset()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-AV',
            'nama' => 'Audio Visual',
        ]);

        $aset = Aset::create([
            'kategori_id' => $kategori->id,
            'kode_aset' => 'AST-PROJ-01',
            'nama' => 'Proyektor Portable 4K',
            'harga_perolehan' => 15000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        // 1. Apply
        $resApply = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $aset->id,
            'keperluan' => 'Presentasi Proyek Client',
            'tanggal_pinjam' => now()->addDays(1)->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
        ]);
        $resApply->assertStatus(201);
        $pinjamId = $resApply->json('data.id');

        // 2. Approve
        $resApprove = $this->postJson("/api/sinapra/peminjaman-aset/{$pinjamId}/approve", [
            'is_approved' => true,
        ]);
        $resApprove->assertStatus(200)
                   ->assertJsonPath('data.status', 'dipinjam');

        $this->assertDatabaseHas('aset', ['id' => $aset->id, 'status' => 'dipinjam']);

        // 3. Kembalikan
        $resKembali = $this->postJson("/api/sinapra/peminjaman-aset/{$pinjamId}/kembalikan", [
            'kondisi_kembali' => 'baik',
        ]);
        $resKembali->assertStatus(200)
                   ->assertJsonPath('data.status', 'kembali');

        $this->assertDatabaseHas('aset', ['id' => $aset->id, 'status' => 'tersedia']);
    }

    public function test_create_and_update_maintenance_log()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $kategori = KategoriAset::create(['kode' => 'KAT-MNT', 'nama' => 'AC & HVAC']);
        $aset = Aset::create([
            'kategori_id' => $kategori->id,
            'kode_aset' => 'AC-01',
            'nama' => 'AC Split 2 PK',
            'harga_perolehan' => 6000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        // 1. Store Log
        $resStore = $this->postJson('/api/sinapra/maintenance', [
            'aset_id' => $aset->id,
            'judul' => 'AC Kurang Dingin & Bocor Air',
            'deskripsi_kerusakan' => 'Freon habis dan saluran pembuangan tersumbat',
            'prioritas' => 'sedang',
            'status' => 'dilaporkan',
        ]);
        $resStore->assertStatus(201);
        $mntId = $resStore->json('data.id');

        // 2. Update Status to Selesai
        $resUpdate = $this->putJson("/api/sinapra/maintenance/{$mntId}", [
            'aset_id' => $aset->id,
            'judul' => 'AC Kurang Dingin & Bocor Air',
            'deskripsi_kerusakan' => 'Sudah dilakukan isi freon & pembersihan saluran',
            'prioritas' => 'sedang',
            'status' => 'selesai',
            'biaya' => 350000,
            'hasil_perbaikan' => 'AC berfungsi dingin normal kembali',
        ]);
        $resUpdate->assertStatus(200)
                  ->assertJsonPath('data.status', 'selesai');

        $this->assertDatabaseHas('maintenance_log', ['id' => $mntId, 'status' => 'selesai']);
    }

    public function test_create_and_approve_pengajuan_pengadaan()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin);

        $unitKerja = UnitKerja::create([
            'kode' => 'UK-LAB-01',
            'nama' => 'Laboratorium Komputer',
            'tipe' => 'fakultas',
            'status' => 'aktif',
        ]);

        $kategori = KategoriAset::create(['kode' => 'KAT-KOMP', 'nama' => 'Komputer Desktop']);

        // 1. Store Pengadaan
        $resStore = $this->postJson('/api/sinapra/pengadaan', [
            'unit_kerja_id' => $unitKerja->id,
            'judul' => 'Pengadaan PC Core i7 untuk Lab Grafis',
            'alasan_kebutuhan' => 'Kebutuhan praktikum mahasiswa baru jurusan DKV',
            'details' => [
                [
                    'kategori_aset_id' => $kategori->id,
                    'nama_barang' => 'PC Core i7 32GB RAM RTX 4060',
                    'spesifikasi' => 'Display 27 inch IPS 4K',
                    'jumlah' => 10,
                    'satuan' => 'unit',
                    'harga_satuan_estimasi' => 20000000,
                ]
            ]
        ]);
        $resStore->assertStatus(201)
                 ->assertJsonPath('data.estimasi_anggaran', '200000000.00');

        $pengadaanId = $resStore->json('data.id');

        // 2. Patch Status
        $resPatch = $this->patchJson("/api/sinapra/pengadaan/{$pengadaanId}/status", [
            'status' => 'disetujui',
        ]);
        $resPatch->assertStatus(200)
                 ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('pengajuan_pengadaan', [
            'id' => $pengadaanId,
            'status' => 'disetujui',
        ]);
    }
}
