<?php

namespace Tests\Feature;

use App\Models\Arsip\KlasifikasiSurat;
use App\Models\Arsip\KopSurat;
use App\Models\Arsip\NomorSurat;
use App\Models\Arsip\RequestNomorSurat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArsipNomorSuratTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected KopSurat $kopLama;
    protected KopSurat $kopBaru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\IAM\RoleSeeder::class);
        $this->seed(\Database\Seeders\IAM\PermissionSeeder::class);

        $superAdminRole = Role::where('slug', 'superadmin')->first();

        $this->admin = User::factory()->create([
            'email' => 'admin.arsip@test.com',
            'username' => 'admin_arsip_user',
        ]);
        $this->admin->roles()->attach($superAdminRole);

        $this->user = User::factory()->create([
            'email' => 'staff.sinapra@test.com',
            'username' => 'staff_sinapra',
        ]);
        $staffRole = Role::firstOrCreate(['slug' => 'staff'], ['name' => 'Staff', 'is_active' => true]);
        $permCreate = Permission::where('slug', 'arsip.request.create')->first();
        $permRead = Permission::where('slug', 'arsip.request.read')->first();
        if ($permCreate) {
            $staffRole->permissions()->syncWithoutDetaching([$permCreate->id]);
        }
        if ($permRead) {
            $staffRole->permissions()->syncWithoutDetaching([$permRead->id]);
        }
        $this->user->roles()->attach($staffRole);

        // Buat 2 spesimen kop surat (Lama < 2021 vs Baru >= 2021)
        $this->kopLama = KopSurat::create([
            'nama' => 'Kop Surat Versi Lama (Hingga 2020)',
            'versi' => 'lama',
            'tahun_mulai' => 2000,
            'tahun_selesai' => 2020,
            'file_path' => 'arsip/kop-surat/kop_lama.png',
            'is_active' => true,
        ]);

        $this->kopBaru = KopSurat::create([
            'nama' => 'Kop Surat Versi Baru (2021-Sekarang)',
            'versi' => 'baru',
            'tahun_mulai' => 2021,
            'tahun_selesai' => null,
            'file_path' => 'arsip/kop-surat/kop_baru.png',
            'is_active' => true,
        ]);

        KlasifikasiSurat::firstOrCreate(
            ['kode' => 'REK'],
            ['nama' => 'Rektorat', 'kategori' => 'unit', 'is_active' => true]
        );
        KlasifikasiSurat::firstOrCreate(
            ['kode' => 'BAAK'],
            ['nama' => 'BAAK', 'kategori' => 'unit', 'is_active' => true]
        );
    }

    public function test_generate_nomor_surat_satuan_format_romawi(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/arsip/nomor-surat', [
                'mode' => 'satuan',
                'tanggal_surat' => '2025-02-14',
                'kode_unit' => 'BAAK',
                'kode_klasifikasi' => 'DI',
                'perihal' => 'Peminjaman Ruang Praktikum',
                'tujuan' => 'Kepala Lab TRPL',
                'module_origin' => 'sinapra',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertEquals('1/DI/BAAK/II/2025', $data['nomor_surat']);
        $this->assertEquals(1, $data['nomor_urut']);
        $this->assertEquals('II', $data['bulan_romawi']);
        $this->assertEquals(2025, $data['tahun']);
        $this->assertEquals($this->kopBaru->id, $data['kop_surat_id']);
    }

    public function test_generate_nomor_surat_bulk_berurutan(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/arsip/nomor-surat', [
                'mode' => 'bulk',
                'jumlah_nomor' => 3,
                'tanggal_surat' => '2025-05-20',
                'kode_unit' => 'REK',
                'kode_klasifikasi' => 'DII',
                'perihal' => 'Sertifikat Kompetensi Mahasiswa',
                'module_origin' => 'siakad',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertCount(3, $data);

        $this->assertEquals('1/DII/REK/V/2025', $data[0]['nomor_surat']);
        $this->assertEquals('2/DII/REK/V/2025', $data[1]['nomor_surat']);
        $this->assertEquals('3/DII/REK/V/2025', $data[2]['nomor_surat']);
    }

    public function test_resolusi_kop_surat_berdasarkan_tahun(): void
    {
        // Tahun 2019 -> Versi Lama
        $resLama = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/kop-surat/by-year?year=2019');

        $resLama->assertStatus(200);
        $resLama->assertJsonPath('data.versi', 'lama');
        $resLama->assertJsonPath('versi_diterapkan', 'lama');

        // Tahun 2025 -> Versi Baru
        $resBaru = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/kop-surat/by-year?year=2025');

        $resBaru->assertStatus(200);
        $resBaru->assertJsonPath('data.versi', 'baru');
        $resBaru->assertJsonPath('versi_diterapkan', 'baru');
    }

    public function test_alur_request_nomor_surat_dan_verifikasi_admin(): void
    {
        // 1. User mengajukan permohonan nomor surat dari modul SINAPRA
        $reqResponse = $this->actingAs($this->user, 'api')
            ->postJson('/api/arsip/request-nomor', [
                'module_origin' => 'sinapra',
                'perihal' => 'Permohonan Nomor Surat Peminjaman Alat Berat',
                'tujuan' => 'Bagian Logistik',
                'tanggal_surat' => '2025-08-10',
                'kode_unit' => 'LAB',
                'kode_klasifikasi' => 'DIII',
                'jumlah_nomor' => 2,
                'catatan_pemohon' => 'Dibutuhkan segera untuk kegiatan mahasiswa',
            ]);

        $reqResponse->assertStatus(201);
        $requestId = $reqResponse->json('data.id');
        $this->assertEquals('menunggu_verifikasi', $reqResponse->json('data.status'));

        // 2. Admin Arsip memverifikasi (menyetujui) request
        $verifyResponse = $this->actingAs($this->admin, 'api')
            ->postJson("/api/arsip/request-nomor/{$requestId}/verify", [
                'action' => 'setujui',
                'catatan' => 'Permohonan telah dicek dan disetujui.',
            ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonPath('data.status', 'disetujui');

        // 3. Verifikasi bahwa 2 nomor surat terbit otomatis dengan link ke request_id
        $nomorList = NomorSurat::where('request_id', $requestId)->get();
        $this->assertCount(2, $nomorList);
        $this->assertEquals('1/DIII/LAB/VIII/2025', $nomorList[0]->nomor_surat);
        $this->assertEquals('2/DIII/LAB/VIII/2025', $nomorList[1]->nomor_surat);
    }

    public function test_arsip_dashboard_endpoint(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'current_year',
                'total_nomor_surat',
                'nomor_surat_tahun_ini',
                'nomor_surat_terpakai',
                'nomor_surat_direservasi',
                'request_pending',
                'request_disetujui',
                'total_kop_surat',
                'kop_status',
                'recent_nomor',
                'recent_requests',
            ],
        ]);
    }

    public function test_nomor_surat_list_detail_and_cancellation(): void
    {
        // 1. Buat nomor surat
        $postRes = $this->actingAs($this->admin, 'api')
            ->postJson('/api/arsip/nomor-surat', [
                'mode' => 'satuan',
                'tanggal_surat' => '2026-03-01',
                'kode_unit' => 'REK',
                'kode_klasifikasi' => 'DIV',
                'perihal' => 'Panggilan Rekrutmen Dosen',
            ]);
        $postRes->assertStatus(201);
        $nomorId = $postRes->json('data.id');

        // 2. List nomor surat
        $listRes = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/nomor-surat');
        $listRes->assertStatus(200);
        $this->assertNotEmpty($listRes->json('data'));

        // 3. Detail nomor surat
        $detailRes = $this->actingAs($this->admin, 'api')
            ->getJson("/api/arsip/nomor-surat/{$nomorId}");
        $detailRes->assertStatus(200);
        $detailRes->assertJsonPath('data.nomor_surat', '1/DIV/REK/III/2026');

        // 4. Batalkan nomor surat
        $batalRes = $this->actingAs($this->admin, 'api')
            ->postJson("/api/arsip/nomor-surat/{$nomorId}/batalkan", [
                'alasan' => 'Ada kekeliruan perihal agenda rapat',
            ]);
        $batalRes->assertStatus(200);
        $batalRes->assertJsonPath('data.status', 'dibatalkan');
    }

    public function test_master_klasifikasi_crud_and_dropdown_query(): void
    {
        // 1. Ambil list dropdown all=true untuk unit dan klasifikasi
        $unitRes = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/klasifikasi?all=true&kategori=unit');
        $unitRes->assertStatus(200);
        $this->assertIsArray($unitRes->json('data'));
        $this->assertNotEmpty($unitRes->json('data'));

        $klasifikasiRes = $this->actingAs($this->admin, 'api')
            ->getJson('/api/arsip/klasifikasi?all=true&kategori=klasifikasi');
        $klasifikasiRes->assertStatus(200);
        $this->assertIsArray($klasifikasiRes->json('data'));
        $this->assertNotEmpty($klasifikasiRes->json('data'));

        // 2. Tambah klasifikasi baru
        $storeRes = $this->actingAs($this->admin, 'api')
            ->postJson('/api/arsip/klasifikasi', [
                'kode' => 'NEWUNIT',
                'nama' => 'Unit Penjaminan Mutu Baru',
                'kategori' => 'unit',
                'is_active' => true,
            ]);
        $storeRes->assertStatus(201);
        $newId = $storeRes->json('data.id');

        // 3. Update klasifikasi
        $updateRes = $this->actingAs($this->admin, 'api')
            ->putJson("/api/arsip/klasifikasi/{$newId}", [
                'nama' => 'Unit Penjaminan Mutu & Audit Baru',
                'kategori' => 'unit',
                'is_active' => true,
            ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('data.nama', 'Unit Penjaminan Mutu & Audit Baru');

        // 4. Hapus klasifikasi
        $deleteRes = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/arsip/klasifikasi/{$newId}");
        $deleteRes->assertStatus(200);
    }
}
