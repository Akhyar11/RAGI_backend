<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinapraSimpegAsetClearanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai;
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

        $unitKerja = UnitKerja::create([
            'nama' => 'Biro Umum & Keuangan',
            'kode' => 'BUK',
            'tipe' => 'biro',
            'is_active' => true,
        ]);

        $userPegawai = User::factory()->create([
            'username' => 'budi_santoso',
            'email' => 'budi@campus.ac.id',
        ]);

        $this->pegawai = Pegawai::create([
            'user_id' => $userPegawai->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '198901012015011001',
            'nama_lengkap' => 'Budi Santoso, S.Kom.',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Tendik',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
        ]);

        $this->kategori = KategoriAset::create([
            'nama' => 'Peralatan Kantor & Elektronik',
            'kode' => 'ELK',
            'umur_ekonomis' => 5,
            'persentase_penyusutan' => 20.0,
        ]);

        $gedung = Gedung::create([
            'nama' => 'Gedung Rektorat',
            'kode' => 'REK',
            'jumlah_lantai' => 4,
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'nama' => 'Ruang Administrasi BUK',
            'kode' => 'REK-101',
            'lantai' => 1,
            'kapasitas' => 10,
        ]);
    }

    public function test_can_assign_penanggung_jawab_pegawai_to_aset()
    {
        $payload = [
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'penanggung_jawab_pegawai_id' => $this->pegawai->id,
            'kode_aset' => 'AST-LAPTOP-001',
            'nama' => 'Laptop ThinkPad T14 Gen 3',
            'merk' => 'Lenovo',
            'serial_number' => 'TP-99281-XYZ',
            'tanggal_perolehan' => '2026-03-01',
            'harga_perolehan' => 18500000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => false,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/sinapra/aset', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.penanggung_jawab_pegawai_id', $this->pegawai->id);

        $this->assertDatabaseHas('sinapra_aset', [
            'kode_aset' => 'AST-LAPTOP-001',
            'penanggung_jawab_pegawai_id' => $this->pegawai->id,
        ]);
    }

    public function test_can_get_clearance_status_for_pegawai()
    {
        // Kondisi 1: Pegawai belum memegang aset dinas
        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/simpeg/pegawai/{$this->pegawai->id}/clearance");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_cleared', true)
            ->assertJsonPath('data.aset_dipegang_count', 0);

        // Kondisi 2: Pegawai memegang aset dinas
        $aset = Aset::create([
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'penanggung_jawab_pegawai_id' => $this->pegawai->id,
            'kode_aset' => 'AST-CAR-001',
            'nama' => 'Mobil Operasional Innova Zenix',
            'tanggal_perolehan' => '2026-01-10',
            'harga_perolehan' => 450000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        $response2 = $this->actingAs($this->admin, 'api')
            ->getJson("/api/simpeg/pegawai/{$this->pegawai->id}/clearance");

        $response2->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_cleared', false)
            ->assertJsonPath('data.aset_dipegang_count', 1);
    }

    public function test_cannot_deactivate_or_delete_pegawai_with_active_assets()
    {
        // Berikan aset dinas ke pegawai
        $aset = Aset::create([
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'penanggung_jawab_pegawai_id' => $this->pegawai->id,
            'kode_aset' => 'AST-PC-001',
            'nama' => 'Workstation Dell Precision',
            'tanggal_perolehan' => '2026-01-10',
            'harga_perolehan' => 35000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        // Coba update status menjadi keluar -> Ditolak clearance
        $updateResponse = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/pegawai/{$this->pegawai->id}", [
                'status' => 'keluar',
            ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Coba delete pegawai -> Ditolak clearance
        $deleteResponse = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/pegawai/{$this->pegawai->id}");

        $deleteResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pegawai']);
    }

    public function test_can_deactivate_or_delete_pegawai_after_asset_cleared()
    {
        $aset = Aset::create([
            'kategori_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'penanggung_jawab_pegawai_id' => $this->pegawai->id,
            'kode_aset' => 'AST-PC-002',
            'nama' => 'Workstation HP Z4',
            'tanggal_perolehan' => '2026-01-10',
            'harga_perolehan' => 30000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
        ]);

        // Handover aset (lepaskan penanggung jawab)
        $aset->update(['penanggung_jawab_pegawai_id' => null]);

        // Sekarang update status ke keluar diperbolehkan
        $updateResponse = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/pegawai/{$this->pegawai->id}", [
                'status' => 'keluar',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Delete pegawai juga diperbolehkan
        $deleteResponse = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/pegawai/{$this->pegawai->id}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('simpeg_pegawai', ['id' => $this->pegawai->id]);
    }
}
