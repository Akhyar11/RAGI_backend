<?php

namespace Tests\Feature;

use App\Models\Arsip\KlasifikasiSurat;
use App\Models\Arsip\KopSurat;
use App\Models\Arsip\RequestNomorSurat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpegArsipSikeuIntegratedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $dosenUser;
    protected User $arsipUser;
    protected Pegawai $dosen;
    protected UnitKerja $unitKerja;
    protected MasterKategoriKegiatanTugas $kategori;
    protected MasterJenisTransportasi $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('public');

        // Roles & Permissions
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $dosenRole = Role::create(['name' => 'Dosen', 'slug' => 'dosen']);
        $arsipRole = Role::create(['name' => 'Admin Arsip', 'slug' => 'admin_arsip']);

        $perms = [
            'simpeg.surat_tugas.create',
            'simpeg.surat_tugas.read',
            'simpeg.surat_tugas.update',
            'simpeg.surat_tugas.approve',
            'arsip.request.read',
            'arsip.request.approve',
            'arsip.nomor.read',
            'arsip.nomor.create',
        ];

        foreach ($perms as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'module' => str_starts_with($slug, 'arsip') ? 'arsip' : 'simpeg',
                'action' => explode('.', $slug)[2] ?? 'manage',
            ]);
            $adminRole->permissions()->attach($p->id);
            if (str_starts_with($slug, 'arsip')) {
                $arsipRole->permissions()->attach($p->id);
            }
        }

        $this->admin = User::factory()->create(['name' => 'Direktur Utama']);
        $this->admin->roles()->attach($adminRole->id);

        $this->arsipUser = User::factory()->create(['name' => 'Petugas Arsip']);
        $this->arsipUser->roles()->attach($arsipRole->id);

        $this->dosenUser = User::factory()->create(['name' => 'Dr. Hendra Gunawan']);
        $this->dosenUser->roles()->attach($dosenRole->id);

        $this->unitKerja = UnitKerja::create([
            'nama' => 'Teknik Informatika',
            'kode' => 'TI',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        $this->dosen = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'unit_kerja_id' => $this->unitKerja->id,
            'nama_lengkap' => 'Dr. Hendra Gunawan',
            'nip' => '198701012015011001',
            'email' => 'hendra@kampus.ac.id',
            'status_kepegawaian' => 'tetap',
            'tanggal_masuk' => '2015-01-01',
        ]);

        $this->kategori = MasterKategoriKegiatanTugas::create([
            'nama' => 'Seminar Nasional & Diseminasi',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->transport = MasterJenisTransportasi::create([
            'kode' => 'KERETA',
            'nama' => 'Kereta Api',
            'urutan' => 1,
            'is_active' => true,
        ]);

        // Master Klasifikasi DII untuk Surat Tugas
        KlasifikasiSurat::updateOrCreate(
            ['kode' => 'DII'],
            [
                'nama' => 'Surat Tugas / SPPD',
                'kategori' => 'klasifikasi',
                'is_active' => true,
            ]
        );

        KlasifikasiSurat::updateOrCreate(
            ['kode' => 'TI'],
            [
                'nama' => 'Teknik Informatika',
                'kategori' => 'unit',
                'is_active' => true,
            ]
        );

        KopSurat::create([
            'nama' => 'Kop Kampus 2026',
            'versi' => 'baru',
            'tahun_mulai' => 2021,
            'file_path' => 'arsip/kop/kop-2026.png',
            'is_active' => true,
        ]);
    }

    public function test_integrated_surat_tugas_to_sikeu_and_arsip_numbering_workflow()
    {
        // 1. Buat Surat Tugas dengan anggaran SPPD Rp 2.500.000 di SIMPEG
        $suratTugas = SuratTugas::create([
            'pegawai_id' => $this->dosen->id,
            'kategori_kegiatan_id' => $this->kategori->id,
            'jenis_transportasi_id' => $this->transport->id,
            'nama_kegiatan' => 'Diseminasi Penelitian AI di Yogyakarta',
            'tempat_berangkat' => 'Surakarta',
            'lokasi_tujuan' => 'Yogyakarta',
            'tanggal_berangkat' => '2026-10-15',
            'tanggal_kembali' => '2026-10-17',
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-17',
            'maksud_tujuan' => 'Mempresentasikan hasil riset machine learning',
            'estimasi_biaya' => 2500000,
            'status' => 'diajukan',
        ]);

        $this->assertNull($suratTugas->nomor_surat);

        // 2. Pimpinan approve surat tugas tanpa mengisikan nomor surat manual
        $responseApprove = $this->actingAs($this->admin, 'api')->postJson(
            "/api/simpeg/surat-tugas/{$suratTugas->id}/approve",
            [
                'status' => 'disetujui',
                'nominal_disetujui' => 2500000,
                'catatan_approval' => 'Disetujui untuk berangkat dinas.',
            ]
        );

        $responseApprove->assertStatus(200);

        // Pastikan terintegrasi ke SIKEU (pengajuan pencairan kas dibuat)
        $suratTugas->refresh();
        $this->assertEquals('disetujui', $suratTugas->status);
        $this->assertNotNull($suratTugas->sikeu_pencairan_id);
        $this->assertEquals('menunggu_keuangan', $suratTugas->status_pencairan);

        // Pastikan RequestNomorSurat otomatis terbentuk di modul ARSIP
        $arsipRequest = RequestNomorSurat::where('reference_type', SuratTugas::class)
            ->where('reference_id', $suratTugas->id)
            ->first();

        $this->assertNotNull($arsipRequest);
        $this->assertEquals('simpeg', $arsipRequest->module_origin);
        $this->assertEquals('menunggu_verifikasi', $arsipRequest->status);
        $this->assertEquals('Surat Tugas: Diseminasi Penelitian AI di Yogyakarta', $arsipRequest->perihal);
        $this->assertStringContainsString('MENUNGGU ARSIP', $suratTugas->nomor_surat);

        // 3. Admin Arsip meninjau di modul ARSIP, mengesahkan Kode Klasifikasi DII, dan menyetujui
        $responseVerify = $this->actingAs($this->arsipUser, 'api')->postJson(
            "/api/arsip/request-nomor/{$arsipRequest->id}/verify",
            [
                'action' => 'setujui',
                'kode_klasifikasi' => 'DII',
                'kode_unit' => 'TI',
                'perihal' => 'Surat Tugas Resmi: Diseminasi Penelitian AI di Yogyakarta',
                'catatan' => 'Sesuai dengan tata naskah dinas DII (Surat Tugas).',
            ]
        );

        $responseVerify->assertStatus(200);

        // 4. Verifikasi nomor resmi terbit dengan format [Urutan]/DII/INDO/[Romawi]/[Tahun]
        $arsipRequest->refresh();
        $this->assertEquals('disetujui', $arsipRequest->status);
        $this->assertCount(1, $arsipRequest->nomorSurat);

        $nomorSuratResmi = $arsipRequest->nomorSurat->first()->nomor_surat;
        $this->assertStringContainsString('/DII/TI/', $nomorSuratResmi);

        // 5. Pastikan nomor resmi otomatis ter-sinkronisasi ke simpeg_surat_tugas dan pengajuan kas SIKEU!
        $suratTugas->refresh();
        $this->assertEquals($nomorSuratResmi, $suratTugas->nomor_surat);

        $pengajuanKas = \App\Models\Sikeu\PengajuanPencairanKas::find($suratTugas->sikeu_pencairan_id);
        $this->assertNotNull($pengajuanKas);
        $this->assertEquals($nomorSuratResmi, $pengajuanKas->referensi_eksternal);
    }
}
