<?php

namespace Tests\Feature\Simpeg;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\UnitKerja;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuratTugasSikeuWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $dosenUser;
    protected Pegawai $dosen;
    protected UnitKerja $unitKerja;
    protected MasterKategoriKegiatanTugas $kategori;
    protected MasterJenisTransportasi $transport;
    protected UnitKas $unitKas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('public');

        // Roles & Permissions
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $dosenRole = Role::create(['name' => 'Dosen', 'slug' => 'dosen']);

        $perms = [
            'simpeg.surat_tugas.create' => 'create',
            'simpeg.surat_tugas.read' => 'read',
            'simpeg.surat_tugas.update' => 'update',
            'simpeg.surat_tugas.approve' => 'approve',
            'simpeg.surat_tugas.delete' => 'delete',
        ];
        foreach ($perms as $slug => $action) {
            $p = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'module' => 'simpeg',
                'action' => $action,
            ]);
            $adminRole->permissions()->attach($p->id);
            if ($action !== 'approve') {
                $dosenRole->permissions()->attach($p->id);
            }
        }

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);

        $this->dosenUser = User::factory()->create();
        $this->dosenUser->roles()->attach($dosenRole->id);

        $this->unitKerja = UnitKerja::create([
            'nama' => 'Fakultas Teknik',
            'kode' => 'FT-01',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $this->dosen = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'unit_kerja_id' => $this->unitKerja->id,
            'nama_lengkap' => 'Dr. Budi Santoso',
            'nip' => '198501012010121001',
            'email' => 'budi@kampus.ac.id',
            'status_kepegawaian' => 'tetap',
            'tanggal_masuk' => '2015-01-01',
        ]);

        $this->kategori = MasterKategoriKegiatanTugas::create([
            'nama' => 'Studi Banding & Kerjasama',
            'deskripsi' => 'Kunjungan benchmarking MoU',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->transport = MasterJenisTransportasi::create([
            'kode' => 'PESAWAT',
            'nama' => 'Pesawat Terbang',
            'urutan' => 1,
            'is_active' => true,
        ]);

        // Akun Keuangan & Unit Kas untuk SIKEU
        $akunKas = AkunKeuangan::create([
            'kode_akun' => '101.01',
            'nama_akun' => 'Kas Operasional Rektorat',
            'kategori' => 'aset',
            'saldo_normal' => 'debet',
            'is_active' => true,
        ]);

        AkunKeuangan::create([
            'kode_akun' => '502.01',
            'nama_akun' => 'Beban Perjalanan Dinas',
            'kategori' => 'beban',
            'saldo_normal' => 'debet',
            'is_active' => true,
        ]);

        $this->unitKas = UnitKas::create([
            'nama_kas' => 'Kas Operasional Rektorat',
            'tipe_kas' => 'operasional',
            'kanal' => 'bank_manual',
            'saldo_awal' => 10000000,
            'saldo_saat_ini' => 10000000,
            'status' => true,
            'akun_keuangan_id' => $akunKas->id,
        ]);
    }

    public function test_full_8_stages_workflow_surat_tugas_and_sikeu(): void
    {
        // -------------------------------------------------------------
        // TAHAP 1: Dosen Mengajukan Surat Tugas di SIMPEG
        // -------------------------------------------------------------
        $createRes = $this->actingAs($this->dosenUser, 'api')->postJson('/api/simpeg/surat-tugas', [
            'pegawai_id' => $this->dosen->id,
            'kategori_kegiatan_id' => $this->kategori->id,
            'jenis_transportasi_id' => $this->transport->id,
            'nama_kegiatan' => 'Kunjungan Benchmarking Kurikulum',
            'tempat_berangkat' => 'Jakarta',
            'lokasi_tujuan' => 'Semarang',
            'tanggal_berangkat' => '2026-10-01',
            'tanggal_kembali' => '2026-10-03',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-03',
            'maksud_tujuan' => 'Koordinasi penyusunan kurikulum MBKM',
            'beban_anggaran' => 'DIPA Kampus 2026',
            'estimasi_biaya' => 500000,
            'status' => 'diajukan',
        ]);

        $createRes->assertStatus(201);
        $suratTugasId = $createRes->json('data.id');
        $this->assertNotNull($suratTugasId);

        // -------------------------------------------------------------
        // TAHAP 2: Pimpinan Menyetujui Surat Tugas (Terbit Nomor Resmi)
        // -------------------------------------------------------------
        $approvePimpinanRes = $this->actingAs($this->admin, 'api')->postJson("/api/simpeg/surat-tugas/{$suratTugasId}/approve", [
            'status' => 'disetujui',
            'nomor_surat' => 'ST/FT/001/2026',
            'catatan_approval' => 'Disetujui untuk berangkat',
        ]);

        $approvePimpinanRes->assertStatus(200);
        $stFresh = SuratTugas::findOrFail($suratTugasId);
        $this->assertEquals('disetujui', $stFresh->status);
        $this->assertEquals('ST/FT/001/2026', $stFresh->nomor_surat);
        $this->assertEquals('menunggu_keuangan', $stFresh->status_pencairan);
        $this->assertNotNull($stFresh->sikeu_pencairan_id);

        $pengajuanKas = PengajuanPencairanKas::findOrFail($stFresh->sikeu_pencairan_id);
        $this->assertEquals('pending_keuangan', $pengajuanKas->status);
        $this->assertEquals('simpeg_surat_tugas', $pengajuanKas->kanal);
        $this->assertEquals('ST/FT/001/2026', $pengajuanKas->referensi_eksternal);
        $this->assertEquals(500000, (float)$pengajuanKas->nominal_diajukan);
        $this->assertNull($pengajuanKas->unit_kas_id);

        // -------------------------------------------------------------
        // TAHAP 3: Keuangan (SIKEU) Menentukan Unit Kas & Nominal Panjar
        // -------------------------------------------------------------
        $approveSikeuRes = $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$pengajuanKas->id}/setujui-panjar-simpeg", [
            'unit_kas_id' => $this->unitKas->id,
            'nominal_disetujui' => 450000,
            'catatan' => 'Panjar disetujui Rp 450.000 dari Kas Operasional',
        ]);

        $approveSikeuRes->assertStatus(200);
        $pengajuanKas->refresh();
        $this->assertEquals('menunggu_konfirmasi_pegawai', $pengajuanKas->status);
        $this->assertEquals($this->unitKas->id, $pengajuanKas->unit_kas_id);
        $this->assertEquals(450000, (float)$pengajuanKas->nominal_disetujui);

        $stFresh->refresh();
        $this->assertEquals('panjar_disetujui', $stFresh->status_pencairan);
        $this->assertEquals(450000, (float)$stFresh->nominal_disetujui);

        // -------------------------------------------------------------
        // TAHAP 4: Dosen Mengonfirmasi Panjar & Ajukan Pencairan
        // -------------------------------------------------------------
        $konfirmasiRes = $this->actingAs($this->dosenUser, 'api')->postJson("/api/simpeg/surat-tugas/{$suratTugasId}/konfirmasi-panjar");
        $konfirmasiRes->assertStatus(200);

        $stFresh->refresh();
        $this->assertEquals('siap_cair', $stFresh->status_pencairan);

        $pengajuanKas->refresh();
        $this->assertEquals('disetujui', $pengajuanKas->status);

        // -------------------------------------------------------------
        // TAHAP 5: Keuangan Mencairkan Dana & Mengunggah Resi Transfer
        // -------------------------------------------------------------
        $fileBukti = UploadedFile::fake()->create('resi_transfer.pdf', 300, 'application/pdf');
        $cairkanRes = $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$pengajuanKas->id}/pencairan", [
            'nominal_cair' => 450000,
            'tanggal_pencairan' => '2026-09-30',
            'bukti_pencairan' => $fileBukti,
            'referensi_eksternal' => 'TRF-BNI-998811',
        ]);

        $cairkanRes->assertStatus(200);
        $pengajuanKas->refresh();
        $this->assertEquals('dicairkan', $pengajuanKas->status);
        $this->assertNotNull($pengajuanKas->bukti_pencairan_path);

        $stFresh->refresh();
        $this->assertEquals('dicairkan', $stFresh->status_pencairan);

        // -------------------------------------------------------------
        // TAHAP 6 & 7: Dosen Mengunggah Berkas LPJ & Nominal Riil Terpakai
        // Panjar disetujui: Rp 450.000, Terpakai: Rp 350.000 -> Sisa pengembalian: Rp 100.000
        // -------------------------------------------------------------
        $fileLpj = UploadedFile::fake()->create('lpj_kegiatan.pdf', 500, 'application/pdf');
        $lpjRes = $this->actingAs($this->dosenUser, 'api')->postJson("/api/simpeg/surat-tugas/{$suratTugasId}/lpj", [
            'file_lpj' => $fileLpj,
            'laporan_kegiatan' => 'Kegiatan benchmarking telah selesai dilaksanakan dengan baik.',
            'biaya_realisasi' => 350000,
        ]);

        $lpjRes->assertStatus(200);
        $stFresh->refresh();
        $this->assertEquals('lpj_diunggah', $stFresh->status_pencairan);
        $this->assertEquals(350000, (float)$stFresh->biaya_realisasi);
        $this->assertEquals(100000, (float)$stFresh->sisa_nominal); // Rp 450.000 - Rp 350.000 = Rp 100.000 kembali ke kas kampus

        $pengajuanKas->refresh();
        $this->assertEquals('lpj_pending', $pengajuanKas->status);
        $this->assertEquals(350000, (float)$pengajuanKas->total_realisasi);
        $this->assertEquals(100000, (float)$pengajuanKas->sisa_nominal);

        // -------------------------------------------------------------
        // TAHAP 8: Keuangan Memverifikasi LPJ & Menutup Buku (Selesai)
        // -------------------------------------------------------------
        $closingRes = $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$pengajuanKas->id}/tutup-lpj-simpeg", [
            'catatan' => 'Sisa lebih bayar Rp 100.000 telah disetorkan kembali ke kas kampus. Transaksi ditutup.',
        ]);

        $closingRes->assertStatus(200);
        $pengajuanKas->refresh();
        $this->assertEquals('selesai', $pengajuanKas->status);

        $stFresh->refresh();
        $this->assertEquals('selesai', $stFresh->status_pencairan);
        $this->assertEquals('selesai', $stFresh->status);
    }
}
