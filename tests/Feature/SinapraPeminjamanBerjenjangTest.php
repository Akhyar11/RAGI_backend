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
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraPeminjamanBerjenjangTest extends TestCase
{
    protected User $adminSinapra;
    protected User $laboranUser;
    protected User $otherLaboran;
    protected User $mahasiswaUser;

    protected Ruangan $ruangLab;
    protected Ruangan $ruangNonLab;

    protected Aset $asetLab;
    protected Aset $asetNonLab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        // Setup Roles & Permissions
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin_sarpras'],
            ['name' => 'Admin Sarpras', 'is_active' => true]
        );
        $laboranRole = Role::firstOrCreate(
            ['slug' => 'admin_laboratorium'],
            ['name' => 'Admin Laboratorium', 'is_active' => true]
        );
        $mhsRole = Role::firstOrCreate(
            ['slug' => 'mahasiswa'],
            ['name' => 'Mahasiswa', 'is_active' => true]
        );

        $perms = [
            'sinapra.peminjaman_ruangan.read' => 'read',
            'sinapra.peminjaman_ruangan.create' => 'create',
            'sinapra.peminjaman_ruangan.approve' => 'approve',
            'sinapra.peminjaman_ruangan.approve_laboran' => 'approve',
            'sinapra.peminjaman_aset.read' => 'read',
            'sinapra.peminjaman_aset.create' => 'create',
            'sinapra.peminjaman_aset.approve' => 'approve',
            'sinapra.peminjaman_aset.approve_laboran' => 'approve',
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
            $permIds['sinapra.peminjaman_ruangan.read'],
            $permIds['sinapra.peminjaman_ruangan.approve_laboran'],
            $permIds['sinapra.peminjaman_aset.read'],
            $permIds['sinapra.peminjaman_aset.approve_laboran'],
        ]);
        $mhsRole->permissions()->sync([
            $permIds['sinapra.peminjaman_ruangan.read'],
            $permIds['sinapra.peminjaman_ruangan.create'],
            $permIds['sinapra.peminjaman_aset.read'],
            $permIds['sinapra.peminjaman_aset.create'],
        ]);

        $this->adminSinapra = User::factory()->create();
        $this->adminSinapra->roles()->sync([$adminRole->id]);

        $this->laboranUser = User::factory()->create();
        $this->laboranUser->roles()->sync([$laboranRole->id]);

        $this->otherLaboran = User::factory()->create();
        $this->otherLaboran->roles()->sync([$laboranRole->id]);

        $this->mahasiswaUser = User::factory()->create();
        $this->mahasiswaUser->roles()->sync([$mhsRole->id]);

        // Master Gedung & Ruangan
        $gedung = Gedung::create([
            'kode' => 'GDG-BERJENJANG-' . uniqid(),
            'nama' => 'Gedung Terpadu',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);

        $this->ruangLab = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-PB-' . uniqid(),
            'nama' => 'Laboratorium Komputer A',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 40,
            'status' => 'aktif',
        ]);

        $this->ruangNonLab = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-TEORI-' . uniqid(),
            'nama' => 'Ruang Teori 101',
            'lantai' => 1,
            'tipe' => 'kelas',
            'kapasitas' => 50,
            'status' => 'aktif',
        ]);

        // Assign laboranUser ke ruangLab
        $this->ruangLab->laboran()->attach($this->laboranUser->id, ['is_primary' => true]);

        // Master Kategori & Aset
        $kategori = KategoriAset::create([
            'kode' => 'KAT-PB-' . uniqid(),
            'nama' => 'Peralatan Elektronik',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25,
        ]);

        $this->asetLab = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruangLab->id,
            'kode_aset' => 'AST-LAB-' . uniqid(),
            'nama' => 'Mikroskop Digital USB',
            'harga_perolehan' => 5000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => true,
        ]);

        $this->asetNonLab = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->ruangNonLab->id,
            'kode_aset' => 'AST-GEN-' . uniqid(),
            'nama' => 'Proyektor Portabel Epson',
            'harga_perolehan' => 7000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => false,
        ]);
    }

    public function test_peminjaman_ruang_lab_requires_laboran_approval_first(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Mahasiswa mengajukan peminjaman ruang Lab
        $res = $this->postJson('/api/sinapra/peminjaman-ruangan', [
            'ruangan_id' => $this->ruangLab->id,
            'tanggal' => '2026-10-15',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'keperluan' => 'Praktikum Mandiri Robotika',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending_laboran');

        $peminjamanId = $res->json('data.id');

        // Laboran yang TIDAK bertugas di lab tersebut mencoba approve -> HARUS 403 Forbidden
        Passport::actingAs($this->otherLaboran);
        $resForbidden = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve-laboran", [
            'is_approved' => true,
            'catatan_laboran' => 'Mencoba approve lab orang lain',
        ]);
        $resForbidden->assertStatus(403);

        // Laboran yang bertugas menyetujui -> status naik jadi pending_admin_sinapra
        Passport::actingAs($this->laboranUser);
        $resLaboranApprove = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve-laboran", [
            'is_approved' => true,
            'catatan_laboran' => 'Alat robotika sudah siap digunakan',
        ]);

        $resLaboranApprove->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending_admin_sinapra')
            ->assertJsonPath('data.catatan_laboran', 'Alat robotika sudah siap digunakan');

        // Admin SINAPRA melakukan persetujuan akhir
        Passport::actingAs($this->adminSinapra);
        $resAdminApprove = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);

        $resAdminApprove->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');
    }

    public function test_peminjaman_ruang_non_lab_goes_directly_to_admin_sinapra(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Mahasiswa mengajukan ruang Teori/Non-lab
        $res = $this->postJson('/api/sinapra/peminjaman-ruangan', [
            'ruangan_id' => $this->ruangNonLab->id,
            'tanggal' => '2026-10-16',
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'keperluan' => 'Diskusi Belajar Kelompok',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending_admin_sinapra');

        $peminjamanId = $res->json('data.id');

        // Langsung di-approve oleh Admin SINAPRA
        Passport::actingAs($this->adminSinapra);
        $resAdmin = $this->postJson("/api/sinapra/peminjaman-ruangan/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);

        $resAdmin->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');
    }

    public function test_peminjaman_aset_berjenjang_workflow(): void
    {
        Passport::actingAs($this->mahasiswaUser);

        // Ajukan pinjam aset lab
        $res = $this->postJson('/api/sinapra/peminjaman-aset', [
            'aset_id' => $this->asetLab->id,
            'tanggal_pinjam' => '2026-10-20',
            'tanggal_kembali_rencana' => '2026-10-22',
            'keperluan' => 'Peminjaman Mikroskop Proyek Akhir',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending_laboran');

        $peminjamanId = $res->json('data.id');

        // Laboran approve
        Passport::actingAs($this->laboranUser);
        $resLab = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve-laboran", [
            'is_approved' => true,
            'catatan_laboran' => 'Kondisi lensa bagus',
        ]);

        $resLab->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_admin_sinapra');

        // Admin approve
        Passport::actingAs($this->adminSinapra);
        $resAdmin = $this->postJson("/api/sinapra/peminjaman-aset/{$peminjamanId}/approve", [
            'is_approved' => true,
        ]);

        $resAdmin->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertEquals('dipinjam', $this->asetLab->fresh()->status);
    }
}
