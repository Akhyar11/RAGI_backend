<?php

namespace Tests\Feature;

use App\Models\AlatKalibrasi;
use App\Models\Aset;
use App\Models\BebasTanggungan;
use App\Models\Gedung;
use App\Models\KategoriAset;
use App\Models\LabBhp;
use App\Models\PeminjamanAset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraLaboratoriumKhususTest extends TestCase
{
    protected User $adminSarpras;
    protected User $laboranTrpl;
    protected User $laboranElektro;
    protected User $mahasiswa;

    protected Ruangan $labTrpl;
    protected Ruangan $labElektro;
    protected Aset $mikroskopLabTrpl;

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
        $mhsRole = Role::firstOrCreate(
            ['slug' => 'mahasiswa'],
            ['name' => 'Mahasiswa', 'is_active' => true]
        );

        $perms = [
            'sinapra.bhp.read' => 'read',
            'sinapra.bhp.manage' => 'update',
            'sinapra.bebas_tanggungan.read' => 'read',
            'sinapra.bebas_tanggungan.create' => 'create',
            'sinapra.bebas_tanggungan.approve' => 'update',
            'sinapra.kalibrasi.read' => 'read',
            'sinapra.kalibrasi.manage' => 'update',
            'sinapra.aset.read' => 'read',
            'sinapra.peminjaman_aset.read' => 'read',
            'sinapra.peminjaman_aset.create' => 'create',
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
            $permIds['sinapra.bhp.read'],
            $permIds['sinapra.bhp.manage'],
            $permIds['sinapra.bebas_tanggungan.read'],
            $permIds['sinapra.bebas_tanggungan.approve'],
            $permIds['sinapra.kalibrasi.read'],
            $permIds['sinapra.kalibrasi.manage'],
            $permIds['sinapra.aset.read'],
        ]);
        $mhsRole->permissions()->sync([
            $permIds['sinapra.bebas_tanggungan.read'],
            $permIds['sinapra.bebas_tanggungan.create'],
            $permIds['sinapra.peminjaman_aset.create'],
        ]);

        $this->adminSarpras = User::factory()->create();
        $this->adminSarpras->roles()->sync([$adminRole->id]);

        $this->laboranTrpl = User::factory()->create();
        $this->laboranTrpl->roles()->sync([$laboranRole->id]);

        $this->laboranElektro = User::factory()->create();
        $this->laboranElektro->roles()->sync([$laboranRole->id]);

        $this->mahasiswa = User::factory()->create();
        $this->mahasiswa->roles()->sync([$mhsRole->id]);

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

        // Hubungkan laboranTrpl ke labTrpl
        $this->labTrpl->laboran()->attach($this->laboranTrpl->id, ['is_primary' => true]);
        $this->labElektro->laboran()->attach($this->laboranElektro->id, ['is_primary' => true]);

        // Aset Lab
        $kategori = KategoriAset::create([
            'kode' => 'KAT-LAB-' . uniqid(),
            'nama' => 'Instrumen Presisi Lab',
            'masa_manfaat_tahun' => 5,
            'tarif_penyusutan_persen' => 20,
        ]);

        $this->mikroskopLabTrpl = Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $this->labTrpl->id,
            'kode_aset' => 'AST-MIC-' . uniqid(),
            'nama' => 'Mikroskop Digital Lab TRPL',
            'harga_perolehan' => 15000000,
            'kondisi' => 'baik',
            'status' => 'tersedia',
            'is_borrowable' => true,
            'is_lab_asset' => true,
        ]);
    }

    public function test_bhp_stock_management_and_laboran_scoping(): void
    {
        // 1. Laboran TRPL membuat BHP di lab binaannya
        Passport::actingAs($this->laboranTrpl);

        $resCreate = $this->postJson('/api/sinapra/lab-bhp', [
            'ruangan_id' => $this->labTrpl->id,
            'kode_bhp' => 'BHP-RJ45-' . uniqid(),
            'nama_bhp' => 'Konektor RJ45 Cat6',
            'kategori' => 'komponen_elektronik',
            'stok_saat_ini' => 100,
            'stok_minimum' => 20,
            'satuan' => 'Pcs',
        ]);

        $resCreate->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $bhpId = $resCreate->json('data.id');

        // 2. Catat transaksi keluar (pemakaian praktikum)
        $resKeluar = $this->postJson("/api/sinapra/lab-bhp/{$bhpId}/transaksi", [
            'jenis_transaksi' => 'keluar',
            'jumlah' => 30,
            'keterangan' => 'Praktikum Jaringan Komputer Kelas 2A',
        ]);

        $resKeluar->assertStatus(201)
            ->assertJsonPath('data.stok_terkini', 70);

        // 3. Catat transaksi keluar melebihi sisa stok -> wajib ditolak (422)
        $resMelebihi = $this->postJson("/api/sinapra/lab-bhp/{$bhpId}/transaksi", [
            'jenis_transaksi' => 'keluar',
            'jumlah' => 100, // sisa 70
            'keterangan' => 'Pemakaian berlebih',
        ]);

        $resMelebihi->assertStatus(422)
            ->assertJsonValidationErrors(['jumlah']);

        // 4. Catat transaksi restock (masuk)
        $resMasuk = $this->postJson("/api/sinapra/lab-bhp/{$bhpId}/transaksi", [
            'jenis_transaksi' => 'masuk',
            'jumlah' => 50,
            'keterangan' => 'Restock pengadaan barang baru',
        ]);

        $resMasuk->assertStatus(201)
            ->assertJsonPath('data.stok_terkini', 120);

        // 5. Scoping: Laboran Elektro TIDAK boleh melihat BHP Lab TRPL
        Passport::actingAs($this->laboranElektro);
        $resListElektro = $this->getJson('/api/sinapra/lab-bhp');
        $resListElektro->assertStatus(200);
        $bhpIds = collect($resListElektro->json('data'))->pluck('id');
        $this->assertFalse($bhpIds->contains($bhpId));
    }

    public function test_bebas_tanggungan_lab_rejects_student_with_active_loans(): void
    {
        // Mahasiswa meminjam mikroskop (aktif/belum dikembalikan)
        PeminjamanAset::create([
            'aset_id' => $this->mikroskopLabTrpl->id,
            'user_id' => $this->mahasiswa->id,
            'keperluan' => 'Penelitian Skripsi Tugas Akhir',
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'status' => 'disetujui',
        ]);

        // Mahasiswa mengajukan surat bebas tanggungan
        Passport::actingAs($this->mahasiswa);
        $resApply = $this->postJson('/api/sinapra/bebas-tanggungan', [
            'catatan' => 'Mohon penerbitan surat bebas lab untuk yudisium',
        ]);

        $resApply->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'diajukan');

        $bebasTanggunganId = $resApply->json('data.id');

        // Laboran mencoba menyetujui -> wajib ditolak karena ada tanggungan pinjaman aktif!
        Passport::actingAs($this->laboranTrpl);
        $resApprove = $this->postJson("/api/sinapra/bebas-tanggungan/{$bebasTanggunganId}/approve", [
            'is_approved' => true,
        ]);

        $resApprove->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Kembalikan pinjaman aset (ubah ke 'selesai')
        PeminjamanAset::where('user_id', $this->mahasiswa->id)->update(['status' => 'selesai']);

        // Laboran mencoba menyetujui lagi -> berhasil & nomor surat terbit!
        $resApproveSuccess = $this->postJson("/api/sinapra/bebas-tanggungan/{$bebasTanggunganId}/approve", [
            'is_approved' => true,
            'catatan' => 'Verifikasi bersih dari tanggungan lab',
        ]);

        $resApproveSuccess->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');

        $nomorSurat = $resApproveSuccess->json('data.nomor_surat');
        $this->assertNotNull($nomorSurat);
        $this->assertStringStartsWith('SBT/', $nomorSurat);
    }

    public function test_alat_kalibrasi_schedule_and_alert(): void
    {
        Passport::actingAs($this->laboranTrpl);

        // Catat jadwal kalibrasi alat
        $resKalibrasi = $this->postJson('/api/sinapra/alat-kalibrasi', [
            'aset_id' => $this->mikroskopLabTrpl->id,
            'institusi_kalibrasi' => 'Balai Pengujian & Kalibrasi Fasilitas Kesehatan',
            'nomor_sertifikat' => 'CERT-KAL-2026-001',
            'tanggal_kalibrasi' => now()->subMonths(11)->toDateString(),
            'tanggal_kadaluarsa' => now()->addDays(15)->toDateString(), // Mendekati kadaluarsa (<= 30 hari)
            'status_kelayakan' => 'laik',
            'catatan' => 'Hasil kalibrasi akurasi magnifikasi 99.8%',
        ]);

        $resKalibrasi->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $kalibrasiId = $resKalibrasi->json('data.id');

        // Filter alat mendekati kadaluarsa
        $resFilter = $this->getJson('/api/sinapra/alat-kalibrasi?mendekati_kadaluarsa=true');
        $resFilter->assertStatus(200);
        $ids = collect($resFilter->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($kalibrasiId));
    }

    public function test_early_warnings_endpoint(): void
    {
        // 1. Buat BHP yang stoknya menipis
        LabBhp::create([
            'ruangan_id' => $this->labTrpl->id,
            'kode_bhp' => 'BHP-WARN-001',
            'nama_bhp' => 'Alkohol Kritis 96%',
            'kategori' => 'Bahan Kimia',
            'stok_saat_ini' => 1,
            'stok_minimum' => 5,
            'satuan' => 'Botol',
        ]);

        // 2. Buat Kalibrasi yang mendekati kedaluwarsa
        AlatKalibrasi::create([
            'aset_id' => $this->mikroskopLabTrpl->id,
            'institusi_kalibrasi' => 'Badan Kalibrasi Presisi',
            'nomor_sertifikat' => 'CERT-WARN-01',
            'tanggal_kalibrasi' => now()->subMonths(11)->toDateString(),
            'tanggal_kadaluarsa' => now()->addDays(10)->toDateString(),
            'status_kelayakan' => 'laik',
        ]);

        // 3. Buat Peminjaman Ruangan yang pending
        \App\Models\PeminjamanRuangan::create([
            'ruangan_id' => $this->labTrpl->id,
            'user_id' => $this->mahasiswa->id,
            'keperluan' => 'Praktikum Mandiri Robotika',
            'tanggal' => now()->addDay()->toDateString(),
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '12:00:00',
            'status' => 'pending_laboran',
        ]);

        Passport::actingAs($this->laboranTrpl);

        $response = $this->getJson('/api/sinapra/laboratorium/early-warnings');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'summary' => [
                        'total_bhp_critical',
                        'total_kalibrasi_critical',
                        'total_pending_peminjaman',
                        'total_warnings',
                    ],
                    'bhp_critical',
                    'kalibrasi_critical',
                    'pending_peminjaman',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.total_bhp_critical'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.total_kalibrasi_critical'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.total_pending_peminjaman'));
        $this->assertGreaterThanOrEqual(3, $response->json('data.summary.total_warnings'));
    }
}
