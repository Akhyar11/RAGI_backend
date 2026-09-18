<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\MasterKategoriSkp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegMasterSuratTugasDanSkpTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'admin_sdm',
            'email' => 'admin.sdm@campus.ac.id',
        ]);

        $role = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'superadmin']);
        $this->admin->roles()->attach($role->id);

        $permSlugs = [
            'simpeg.surat_tugas.read' => 'read',
            'simpeg.surat_tugas.create' => 'create',
            'simpeg.surat_tugas.update' => 'update',
            'simpeg.surat_tugas.delete' => 'delete',
            'simpeg.kinerja.read' => 'read',
            'simpeg.kinerja.create' => 'create',
            'simpeg.kinerja.update' => 'update',
            'simpeg.kinerja.delete' => 'delete',
        ];

        foreach ($permSlugs as $slug => $action) {
            $p = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'module' => 'simpeg',
                'action' => $action,
            ]);
            $role->permissions()->syncWithoutDetaching([$p->id]);
        }
    }

    // ── KATEGORI KEGIATAN TUGAS TESTS ─────────────────────────────
    public function test_can_list_master_kategori_kegiatan()
    {
        MasterKategoriKegiatanTugas::create([
            'nama' => 'Studi Banding Kampus',
            'deskripsi' => 'Kunjungan benchmarking',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/master/kategori-kegiatan-tugas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_master_kategori_kegiatan()
    {
        $payload = [
            'nama' => 'Rapat Koordinasi Nasional',
            'deskripsi' => 'Rakornas tahunan kementerian',
            'urutan' => 2,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master/kategori-kegiatan-tugas', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama', 'Rapat Koordinasi Nasional');

        $this->assertDatabaseHas('simpeg_master_kategori_kegiatan_tugas', [
            'nama' => 'Rapat Koordinasi Nasional',
        ]);
    }

    public function test_can_update_master_kategori_kegiatan()
    {
        $kategori = MasterKategoriKegiatanTugas::create([
            'nama' => 'Workshop Internal',
            'deskripsi' => 'Pelatihan internal',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/master/kategori-kegiatan-tugas/{$kategori->id}", [
                'nama' => 'Workshop Internal Diperbarui',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama', 'Workshop Internal Diperbarui');
    }

    public function test_can_delete_master_kategori_kegiatan()
    {
        $kategori = MasterKategoriKegiatanTugas::create([
            'nama' => 'Hapus Kategori',
            'urutan' => 99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/master/kategori-kegiatan-tugas/{$kategori->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('simpeg_master_kategori_kegiatan_tugas', ['id' => $kategori->id]);
    }

    // ── MODA TRANSPORTASI TESTS ──────────────────────────────────
    public function test_can_list_and_create_master_jenis_transportasi()
    {
        $payload = [
            'nama' => 'Kapal Laut / Feri',
            'kode' => 'KAPAL_LAUT',
            'deskripsi' => 'Penyeberangan antarpulau',
            'urutan' => 5,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master/jenis-transportasi', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode', 'KAPAL_LAUT');

        $list = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/master/jenis-transportasi');

        $list->assertStatus(200)
            ->assertJsonFragment(['kode' => 'KAPAL_LAUT']);
    }

    // ── MASTER KATEGORI SKP TESTS ────────────────────────────────
    public function test_can_list_and_create_master_kategori_skp()
    {
        $payload = [
            'nama' => 'Pengembangan Inovasi Kampus',
            'kode' => 'INOVASI_KAMPUS',
            'deskripsi' => 'Pengembangan sistem & inovasi internal',
            'urutan' => 6,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master/kategori-skp', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode', 'INOVASI_KAMPUS');

        $list = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/master/kategori-skp');

        $list->assertStatus(200)
            ->assertJsonFragment(['kode' => 'INOVASI_KAMPUS']);
    }

    // ── VERIFIKASI SURAT TUGAS & KINERJA MASTERS ENDPOINT ────────
    public function test_surat_tugas_and_kinerja_masters_endpoints_include_data()
    {
        MasterKategoriKegiatanTugas::create([
            'nama' => 'Uji Coba Tugas',
            'urutan' => 1,
            'is_active' => true,
        ]);
        MasterJenisTransportasi::create([
            'nama' => 'Uji Coba Mobil',
            'kode' => 'MOBIL_TEST',
            'urutan' => 1,
            'is_active' => true,
        ]);
        MasterKategoriSkp::create([
            'nama' => 'Uji Coba SKP',
            'kode' => 'SKP_TEST',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $resSuratTugas = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/surat-tugas/masters');
        $resSuratTugas->assertStatus(200)
            ->assertJsonFragment(['nama' => 'Uji Coba Tugas'])
            ->assertJsonFragment(['kode' => 'MOBIL_TEST']);

        $resKinerja = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/penilaian-kinerja/masters');
        $resKinerja->assertStatus(200)
            ->assertJsonFragment(['kode' => 'SKP_TEST']);
    }
}
