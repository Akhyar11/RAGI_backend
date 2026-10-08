<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraPeminjamanSuratTest extends TestCase
{
    protected User $adminSinapra;
    protected User $mahasiswaUser;
    protected Ruangan $ruangan;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        // Setup Roles & Permissions
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $mhsRole = Role::firstOrCreate(
            ['slug' => 'mahasiswa'],
            ['name' => 'Mahasiswa', 'is_active' => true]
        );

        $perms = [
            'sinapra.peminjaman_ruangan.read' => 'read',
            'sinapra.peminjaman_ruangan.create' => 'create',
            'sinapra.peminjaman_ruangan.approve' => 'approve',
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
            $permIds['sinapra.peminjaman_ruangan.read'],
            $permIds['sinapra.peminjaman_ruangan.create'],
            $permIds['sinapra.peminjaman_aset.read'],
            $permIds['sinapra.peminjaman_aset.create'],
        ]);

        $this->adminSinapra = User::factory()->create(['email' => 'adminsarpras_surat_' . uniqid() . '@kampus.ac.id']);
        $this->adminSinapra->roles()->sync([$adminRole->id]);

        $this->mahasiswaUser = User::factory()->create(['email' => 'mhs_surat_' . uniqid() . '@kampus.ac.id']);
        $this->mahasiswaUser->roles()->sync([$mhsRole->id]);

        $gedung = Gedung::create([
            'kode' => 'GD-SRT-' . uniqid(),
            'nama' => 'Gedung Serbaguna',
            'jumlah_lantai' => 3,
            'status' => 'aktif',
        ]);

        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-SRT-' . uniqid(),
            'nama' => 'Aula Serbaguna Utama',
            'lantai' => 1,
            'tipe' => 'umum',
            'kapasitas' => 150,
            'ada_ac' => true,
            'ada_proyektor' => true,
            'ada_wifi' => true,
            'status' => 'aktif',
        ]);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-SRT-' . uniqid(),
            'nama' => 'Elektronik & Multimedia',
            'masa_manfaat_tahun' => 5,
        ]);

        $this->aset = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'kode_aset' => 'AST-SRT-' . uniqid(),
            'nama' => 'Proyektor EPSON 4K',
            'merk' => 'EPSON',
            'status' => 'tersedia',
            'kondisi' => 'baik',
            'is_borrowable' => true,
        ]);
    }

    public function test_surat_peminjaman_ruangan_generated_on_approval(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Apply ruangan
        $response = $this->postJson('/api/sinapra/peminjaman-ruangan', [
            'ruangan_id' => $this->ruangan->id,
            'keperluan' => 'Seminar Teknologi Informasi Kampus',
            'tanggal' => now()->addDays(2)->format('Y-m-d'),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'kontak_peminjam' => '081234567890',
        ]);

        $response->assertStatus(201);
        $peminjamanId = $response->json('data.id');

        $peminjaman = PeminjamanRuangan::find($peminjamanId);
        $this->assertNotEmpty($peminjaman->kode_peminjaman);
        $this->assertNull($peminjaman->nomor_surat);

        // Admin approve
        Passport::actingAs($this->adminSinapra);
        $approveResponse = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);
        $approveResponse->assertStatus(200);

        $peminjaman->refresh();
        $this->assertEquals('disetujui', $peminjaman->status);
        $this->assertNotEmpty($peminjaman->nomor_surat);
        $this->assertNotNull($peminjaman->surat_generated_at);
        $this->assertStringContainsString('SINAPRA-RUANG', $peminjaman->nomor_surat);

        // Get surat endpoint
        $suratResponse = $this->getJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/surat");
        $suratResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'peminjaman_id',
                    'kode_peminjaman',
                    'nomor_surat',
                    'surat_generated_at',
                    'tanggal',
                    'jam_mulai',
                    'jam_selesai',
                    'keperluan',
                    'status',
                    'ruangan' => [
                        'id', 'nama', 'kode', 'lantai', 'kapasitas', 'gedung', 'tipe_ruangan'
                    ],
                    'peminjam' => [
                        'user_id', 'nama', 'nomor_identitas', 'unit_kerja', 'kontak', 'email'
                    ],
                    'approver',
                    'verifikasi_token',
                ]
            ]);

        $this->assertEquals($peminjaman->nomor_surat, $suratResponse->json('data.nomor_surat'));
        $this->assertNotEmpty($suratResponse->json('data.verifikasi_token'));
    }

    public function test_surat_peminjaman_aset_generated_on_approval(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Apply aset
        $response = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->aset->id,
            'keperluan' => 'Peminjaman Proyektor untuk Presentasi',
            'tanggal_pinjam' => now()->addDays(1)->format('Y-m-d'),
            'tanggal_kembali_rencana' => now()->addDays(2)->format('Y-m-d'),
            'kontak_peminjam' => '081234567890',
        ]);

        $response->assertStatus(201);
        $peminjamanId = $response->json('data.id');

        $peminjaman = PeminjamanAset::find($peminjamanId);
        $this->assertNull($peminjaman->nomor_surat);

        // Admin approve
        Passport::actingAs($this->adminSinapra);
        $approveResponse = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);
        $approveResponse->assertStatus(200);

        $peminjaman->refresh();
        $this->assertEquals('disetujui', $peminjaman->status);
        $this->assertNotEmpty($peminjaman->nomor_surat);
        $this->assertNotNull($peminjaman->surat_generated_at);
        $this->assertStringContainsString('SINAPRA-ASET', $peminjaman->nomor_surat);

        // Get surat endpoint
        $suratResponse = $this->getJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/surat");
        $suratResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'peminjaman_id',
                    'nomor_surat',
                    'surat_generated_at',
                    'status',
                    'peminjam',
                    'approver',
                    'daftar_barang',
                    'verifikasi_token',
                ]
            ]);

        $this->assertEquals($peminjaman->nomor_surat, $suratResponse->json('data.nomor_surat'));
    }
}
