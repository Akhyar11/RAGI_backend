<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\LabBhp;
use App\Models\PeminjamanRuangan;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinapraDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'username' => 'admin_sarpras',
            'email' => 'sarpras@campus.ac.id',
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Administrator', 'is_active' => true]);
        $this->admin->roles()->sync([$adminRole->id]);
    }

    public function test_can_fetch_sinapra_dashboard_summary(): void
    {
        // 1. Seed sample data
        $gedung = Gedung::create([
            'nama' => 'Gedung Rektorat',
            'kode' => 'REK',
            'jumlah_lantai' => 4,
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-101',
            'nama' => 'Ruang Seminar Utama',
            'lantai' => 1,
            'kapasitas' => 100,
            'status' => 'tersedia',
        ]);

        $kategori = KategoriAset::create([
            'nama' => 'Elektronik Audio Visual',
            'kode' => 'EAV',
            'umur_ekonomis' => 4,
            'tarif_penyusutan_persen' => 25.0,
        ]);

        Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'kode_aset' => 'EAV-001',
            'nama' => 'Proyektor Laser HD',
            'harga_perolehan' => 20000000,
            'nilai_buku' => 15000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
        ]);

        LabBhp::create([
            'ruangan_id' => $ruangan->id,
            'kode_bhp' => 'BHP-TEST-01',
            'nama_bhp' => 'Baterai Pointer AAA',
            'stok_saat_ini' => 2,
            'stok_minimum' => 10,
            'satuan' => 'Pcs',
        ]);

        PeminjamanRuangan::create([
            'ruangan_id' => $ruangan->id,
            'user_id' => $this->admin->id,
            'keperluan' => 'Kuliah Umum',
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '12:00',
            'status' => 'disetujui',
        ]);

        // 2. Request GET /api/sinapra/dashboard-summary
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/sinapra/dashboard-summary');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.metrics.total_gedung', 1)
            ->assertJsonPath('data.metrics.total_ruangan', 1)
            ->assertJsonPath('data.metrics.total_aset', 1)
            ->assertJsonPath('data.metrics.total_harga_perolehan', 20000000)
            ->assertJsonPath('data.metrics.total_nilai_buku', 15000000)
            ->assertJsonPath('data.metrics.total_akumulasi_penyusutan', 5000000)
            ->assertJsonPath('data.early_warnings.bhp_kritis_count', 1);
    }
}
