<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\Lms\LmsSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\TahunAkademik;
use App\Models\Simpeg\CampusAnnouncement;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\DokumenPegawai;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\IzinJamKerja;
use App\Models\Simpeg\MasterJenisCuti;
use App\Models\Simpeg\MasterJenisIzinJamKerja;
use App\Models\Simpeg\MasterJenisSertifikasi;
use App\Models\Simpeg\MasterJenisTransportasi;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use App\Models\Simpeg\MasterKategoriSk;
use App\Models\Simpeg\PengajuanCuti;
use App\Models\Simpeg\SertifikasiDosen;
use App\Models\Simpeg\SkPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sinapra\MaintenanceLog;
use App\Models\Spmb\ReferralUsage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileStaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $dosenUser;
    private Dosen $dosen;
    private TahunAkademik $taAktif;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->taAktif = TahunAkademik::create([
            'kode' => '20261',
            'nama' => 'Ganjil 2026/2027',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $this->dosenUser = User::create([
            'username' => 'dosen.test',
            'email' => 'dosen.test@kampus.ac.id',
            'name' => 'Dr. Budi Santoso, M.Kom',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => '55201',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Dosen Pengajar',
            'slug' => 'dosen',
            'description' => 'Role dosen pengajar',
        ]);
        $perms = [
            'siakad.kelas.read',
            'siakad.kelas.manage',
            'siakad.nilai.read',
            'siakad.nilai.manage',
            'siakad.pa.read',
            'siakad.pa.create',
            'siakad.krs.read',
            'siakad.krs.approve',
            'simpeg.pegawai.read',
            'simpeg.pegawai.create',
            'sippm.proposal.read',
            'sippm.proposal.create',
            'simpeg.payroll.read',
            'simpeg.dokumen.read',
            'simpeg.dokumen.create',
            'simpeg.cuti.read',
            'simpeg.cuti.create',
            'simpeg.izin_kerja.read',
            'simpeg.izin_kerja.create',
            'simpeg.surat_tugas.read',
            'simpeg.surat_tugas.create',
            'simpeg.surat_tugas.update',
            'simpeg.sk_pegawai.read',
            'simpeg.sk_pegawai.create',
            'simpeg.kompetensi.read',
            'arsip.nomor_surat.read',
            'sikeu.pengajuan.read',
            'sikeu.pengajuan.create',
            'sinapra.maintenance.read',
            'sinapra.maintenance.create',
            'sinapra.peminjaman_ruangan.read',
            'spmb.pendaftaran.read',
            'spmb.pendaftaran.create',
        ];
        foreach ($perms as $slug) {
            $action = match (true) {
                str_ends_with($slug, '.read') => 'read',
                str_ends_with($slug, '.create') => 'create',
                str_ends_with($slug, '.approve') => 'approve',
                str_ends_with($slug, '.delete') => 'delete',
                default => 'update',
            };
            $p = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'module' => str_starts_with($slug, 'siakad') ? 'siakad' : 'simpeg',
                'action' => $action,
            ]);
            $role->permissions()->attach($p->id);
        }
        $this->dosenUser->roles()->attach($role->id);
        $this->dosenUser->refresh();

        $this->dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'nidn' => '0620018801',
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'program_studi_id' => $prodi->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/mobile/staff/jadwal-mengajar')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/staff/home')->assertUnauthorized();
        $this->postJson('/api/v1/mobile/staff/avatar', [])->assertUnauthorized();
    }

    public function test_jadwal_mengajar_authenticated_staff_empty_classes(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/mobile/staff/jadwal-mengajar');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.dosen.id', $this->dosen->id)
            ->assertJsonPath('data.total_kelas', 0)
            ->assertJsonPath('data.total_sks', 0)
            ->assertJsonPath('data.today_classes', [])
            ->assertJsonPath('data.all_classes', []);
    }

    public function test_jadwal_mengajar_with_real_kelas_and_pengampu(): void
    {
        Passport::actingAs($this->dosenUser);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-TI-2026',
            'nama' => 'Kurikulum TI 2026',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'TI301',
            'nama' => 'Pemrograman Web',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $gedung = Gedung::create([
            'kode' => 'G-A',
            'nama' => 'Gedung Rektorat',
            'is_active' => true,
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-101',
            'nama' => 'Ruang 101',
            'kapasitas' => 40,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'ruangan_id' => $ruangan->id,
            'kode_kelas' => 'TI-3A',
            'nama_kelas' => 'TI-3A',
            'hari' => 'senin',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:30:00',
            'kapasitas' => 35,
            'status' => 'aktif',
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
            'rencana_tatap_muka' => 16,
        ]);

        $response = $this->getJson('/api/v1/mobile/staff/jadwal-mengajar?hari=senin');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_kelas', 1)
            ->assertJsonPath('data.all_classes.0.course_code', 'TI301');
    }

    public function test_home_summary_authenticated_staff(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/mobile/staff/home');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $this->dosenUser->id)
            ->assertJsonPath('data.user.role', 'Dosen Pengajar')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'role'],
                    'jadwal',
                ],
            ]);
    }

    public function test_update_avatar_base64_and_audit_log(): void
    {
        Passport::actingAs($this->dosenUser);

        // 1x1 transparent PNG valid base64
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $response = $this->postJson('/api/v1/mobile/staff/avatar', [
            'avatar_base64' => $base64Image,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->dosenUser->id);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'IAM',
            'action' => 'update_avatar',
            'record_id' => (string) $this->dosenUser->id,
        ]);

        $this->dosenUser->refresh();
        $this->assertNotNull($this->dosenUser->avatar_path);
    }

    public function test_update_avatar_multipart(): void
    {
        Passport::actingAs($this->dosenUser);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/v1/mobile/staff/avatar', [
            'avatar' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->dosenUser->id);
    }

    public function test_siakad_perkuliahan_jadwal_mengajar(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/siakad/perkuliahan/jadwal-mengajar');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.dosen.id', $this->dosen->id);
    }

    public function test_list_kelas_nilai_empty_returns_empty_array(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->getJson('/api/v1/mobile/staff/nilai/kelas');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', []);
    }

    public function test_list_and_detail_and_save_kelas_nilai_flow(): void
    {
        Passport::actingAs($this->dosenUser);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-TI-OBE',
            'nama' => 'Kurikulum OBE TI',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'TI501',
            'nama' => 'Pemrograman Lanjut',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 5,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $gedung = Gedung::create(['kode' => 'GD-B', 'nama' => 'Gedung B', 'is_active' => true]);
        $ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'LAB-1',
            'nama' => 'Lab Komputer 1',
            'kapasitas' => 30,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'ruangan_id' => $ruangan->id,
            'kode_kelas' => 'TI-5A',
            'nama_kelas' => 'Pemrograman Lanjut (A)',
            'hari' => 'rabu',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:30:00',
            'kapasitas' => 30,
            'status' => 'aktif',
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
            'rencana_tatap_muka' => 16,
        ]);

        // Buat Mahasiswa dan KRS Detail
        $mhsUser = User::create([
            'username' => 'mhs.2026001',
            'email' => 'mhs001@kampus.ac.id',
            'name' => 'Fulan bin Fulan',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'nim' => '2026001',
            'nama' => 'Fulan bin Fulan',
            'nama_lengkap' => 'Fulan bin Fulan',
            'program_studi_id' => $this->dosen->program_studi_id,
            'angkatan' => '2026',
            'status' => 'A',
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $mhs->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'total_sks_diambil' => 3,
            'status' => 'disetujui',
        ]);

        $krsDetail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        // Buat Skala Nilai untuk prodi
        SkalaNilai::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'nilai_huruf' => 'A',
            'bobot_indeks' => 4.00,
            'batas_bawah' => 85.00,
            'batas_atas' => 100.00,
            'is_lulus' => true,
            'is_active' => true,
        ]);

        // 1. List Kelas Nilai
        $listRes = $this->getJson('/api/v1/mobile/staff/nilai/kelas');
        $listRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.kelas_id', $kelas->id)
            ->assertJsonPath('data.0.kode_kelas', 'TI-5A')
            ->assertJsonPath('data.0.total_mahasiswa', 1);

        // 2. Detail Kelas Nilai (Found & 404)
        $this->getJson('/api/v1/mobile/staff/nilai/kelas/99999')->assertStatus(404);

        $detailRes = $this->getJson("/api/v1/mobile/staff/nilai/kelas/{$kelas->id}");
        $detailRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kelas_id', $kelas->id)
            ->assertJsonPath('data.students.0.nim', '2026001')
            ->assertJsonPath('data.students.0.nama', 'Fulan bin Fulan');

        // 3. Simpan Nilai & Audit Log
        $saveRes = $this->postJson("/api/v1/mobile/staff/nilai/kelas/{$kelas->id}", [
            'grades' => [
                [
                    'krs_detail_id' => $krsDetail->id,
                    'nilai_harian' => 90,
                    'nilai_uts' => 85,
                    'nilai_uas' => 90,
                    'nilai_praktik' => 95,
                    'is_final' => true,
                ],
            ],
        ]);

        $saveRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.updated_count', 1);

        $this->assertDatabaseHas('siakad_nilai_mahasiswa', [
            'krs_detail_id' => $krsDetail->id,
            'nilai_huruf' => 'A',
            'is_final' => true,
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'create_nilai_mahasiswa',
            'table_name' => 'siakad_nilai_mahasiswa',
        ]);
    }

    public function test_bimbingan_pa_and_krs_approval_flow(): void
    {
        Passport::actingAs($this->dosenUser);

        $mhsUser = User::create([
            'username' => 'mhs.pa.001',
            'email' => 'mhspa@kampus.ac.id',
            'name' => 'Ahmad Bimbingan',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'nim' => '2026PA01',
            'nama' => 'Ahmad Bimbingan',
            'nama_lengkap' => 'Ahmad Bimbingan',
            'dosen_wali_id' => $this->dosen->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'angkatan' => '2026',
            'semester_aktif' => 1,
            'status' => 'A',
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $mhs->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'total_sks_diambil' => 20,
            'status' => 'diajukan',
        ]);

        // 1. List Mahasiswa Bimbingan
        $listRes = $this->getJson('/api/v1/mobile/staff/bimbingan-pa/mahasiswa');
        $listRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.id', $mhs->id)
            ->assertJsonPath('data.0.nim', '2026PA01')
            ->assertJsonPath('data.0.krs.status', 'diajukan');

        // 2. Detail KRS Bimbingan
        $this->getJson('/api/v1/mobile/staff/bimbingan-pa/krs/99999')->assertStatus(404);

        $detailRes = $this->getJson("/api/v1/mobile/staff/bimbingan-pa/krs/{$krs->id}");
        $detailRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.krs_id', $krs->id)
            ->assertJsonPath('data.mahasiswa.nim', '2026PA01');

        // 3. Reject / Revisi KRS
        $rejectRes = $this->postJson("/api/v1/mobile/staff/bimbingan-pa/krs/{$krs->id}/reject", [
            'alasan' => 'Mata kuliah pilihan semester 3 belum boleh diambil.',
        ]);

        $rejectRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'revisi');

        $this->assertDatabaseHas('siakad_krs', [
            'id' => $krs->id,
            'status' => 'revisi',
        ]);

        $this->assertDatabaseHas('siakad_pa_catatan', [
            'mahasiswa_id' => $mhs->id,
            'kategori' => 'revisi_krs',
        ]);

        // 4. Approve KRS
        $approveRes = $this->postJson("/api/v1/mobile/staff/bimbingan-pa/krs/{$krs->id}/approve");
        $approveRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('siakad_krs', [
            'id' => $krs->id,
            'status' => 'disetujui',
            'disetujui_oleh' => $this->dosen->id,
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'approve_krs',
            'record_id' => (string) $krs->id,
        ]);

        // 5. Catatan Bimbingan CRUD
        $storeCatatanRes = $this->postJson("/api/v1/mobile/staff/bimbingan-pa/catatan/{$mhs->id}", [
            'isi' => 'Konsultasi rencana studi semester 1 berjalan lancar.',
            'kategori' => 'Konsultasi KRS',
            'kesimpulan' => 'Setujui pengambilan mata kuliah.',
        ]);

        $storeCatatanRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kategori', 'Konsultasi KRS');

        $listCatatanRes = $this->getJson("/api/v1/mobile/staff/bimbingan-pa/catatan/{$mhs->id}");
        $listCatatanRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data'); // 1 from reject revisi, 1 from store
    }

    public function test_campus_announcement_broadcast_and_today_agendas(): void
    {
        Passport::actingAs($this->dosenUser);

        // 1. Broadcast Pengumuman Seragam Hari Ini
        $broadcastRes = $this->postJson('/api/simpeg/announcements/broadcast', [
            'title' => 'Seragam Hari Senin',
            'category' => 'seragam',
            'target_role' => 'all_staff',
            'content' => 'Gunakan kemeja putih dan celana/rok gelap.',
            'is_agenda' => false,
            'event_date' => now()->toDateString(),
        ]);

        $broadcastRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Seragam Hari Senin');

        $this->assertDatabaseHas('simpeg_campus_announcements', [
            'title' => 'Seragam Hari Senin',
            'category' => 'seragam',
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIMPEG',
            'action' => 'broadcast_announcement',
        ]);

        // 2. Broadcast Agenda Rapat Mendatang
        $this->postJson('/api/simpeg/announcements/broadcast', [
            'title' => 'Rapat Koordinasi Akademik Semester Ganjil',
            'category' => 'rapat',
            'target_role' => 'dosen',
            'content' => 'Rapat di Ruang Senat Lt 2.',
            'is_agenda' => true,
            'event_date' => now()->addDays(2)->toDateString(),
            'event_time' => '09:00 WIB',
            'event_location' => 'Ruang Senat',
        ])->assertStatus(201);

        // 3. Mobile Staff Agendas Endpoint
        $agendasRes = $this->getJson('/api/v1/mobile/staff/agendas');
        $agendasRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.seragam_hari_ini.title', 'Seragam Hari Senin')
            ->assertJsonCount(1, 'data.upcoming_agendas')
            ->assertJsonPath('data.upcoming_agendas.0.title', 'Rapat Koordinasi Akademik Semester Ganjil');
    }

    public function test_perkuliahan_bap_and_presensi_flow(): void
    {
        Passport::actingAs($this->dosenUser);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-TI-2026',
            'nama' => 'Kurikulum TI 2026',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'TI601',
            'nama' => 'Cloud Computing Architecture',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 6,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'nama_kelas' => 'Cloud Architecture (TI-6A)',
            'kode_kelas' => 'TI-6A',
            'kuota' => 35,
            'is_active' => true,
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
        ]);

        $mhsUser = User::create([
            'username' => 'mhs.cloud.001',
            'email' => 'mhscloud@kampus.ac.id',
            'name' => 'Budi Cloud',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'nim' => '2026999',
            'nama' => 'Budi Cloud',
            'nama_lengkap' => 'Budi Cloud',
            'program_studi_id' => $this->dosen->program_studi_id,
            'angkatan' => '2026',
            'status' => 'A',
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $mhs->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'total_sks_diambil' => 3,
            'status' => 'disetujui',
        ]);

        KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        // 1. List Kelas Perkuliahan (BAP)
        $listRes = $this->getJson('/api/v1/mobile/staff/perkuliahan/kelas');
        $listRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.kelas_id', $kelas->id)
            ->assertJsonPath('data.0.total_pertemuan', 16)
            ->assertJsonPath('data.0.total_mahasiswa', 1);

        // 2. Detail Kelas Pertemuan (1 s/d 16) & 404
        $this->getJson('/api/v1/mobile/staff/perkuliahan/kelas/999999')->assertStatus(404);

        $detailRes = $this->getJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}");
        $detailRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(16, 'data.pertemuan')
            ->assertJsonPath('data.kelas.id', $kelas->id);

        // 3. Mulai Sesi Perkuliahan (Pertemuan 1) & 422
        $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}/mulai", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pertemuan_ke', 'materi']);

        $mulaiRes = $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}/mulai", [
            'pertemuan_ke' => 1,
            'materi' => 'Pengenalan Cloud Native & Microservices',
            'catatan' => 'Pertemuan perdana teori.',
        ]);

        $mulaiRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pertemuan_ke', 1)
            ->assertJsonPath('data.status', 'berlangsung');

        $pertemuanId = $mulaiRes->json('data.id');
        $tokenAwal = $mulaiRes->json('data.token_absensi');

        $this->assertNotEmpty($tokenAwal);
        $this->assertSame("RAG-PRESENSI:{$pertemuanId}:{$tokenAwal}", $mulaiRes->json('data.qr_payload'));
        $this->assertSame(30, $mulaiRes->json('data.rotate_interval_seconds'));
        $this->assertDatabaseHas('siakad_pertemuan', [
            'id' => $pertemuanId,
            'status_pertemuan' => 'berlangsung',
            'token_absensi' => $tokenAwal,
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'start_perkuliahan_session',
            'record_id' => (string) $pertemuanId,
        ]);

        // 4. Rotate Token Presensi Dinamis
        $rotateRes = $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/rotate-token");
        $rotateRes->assertOk()
            ->assertJsonPath('status', 'success');

        $tokenBaru = $rotateRes->json('data.token_absensi');
        $this->assertNotEmpty($tokenBaru);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'rotate_attendance_token',
            'record_id' => (string) $pertemuanId,
        ]);
        $rotateAudit = \App\Models\AuditLog::where('action', 'rotate_attendance_token')->firstOrFail();
        $this->assertSame('********', $rotateAudit->new_values['token_absensi'] ?? null);

        // 5. Detail Presensi Mahasiswa & 404
        $this->getJson('/api/v1/mobile/staff/perkuliahan/pertemuan/999999/presensi')->assertStatus(404);

        $presensiRes = $this->getJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi");
        $presensiRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rekap.total', 1)
            ->assertJsonPath('data.mahasiswa.0.mahasiswa_id', $mhs->id);

        // 6. Update / Override Status Presensi Mahasiswa Manual
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi", [
            'items' => [
                [
                    'mahasiswa_id' => $mhs->id,
                    'status' => 'status_tidak_valid',
                ],
            ],
        ])->assertStatus(422);

        $updatePresensiRes = $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi", [
            'items' => [
                [
                    'mahasiswa_id' => $mhs->id,
                    'status' => 'izin',
                    'catatan' => 'Izin lomba akademik.',
                ],
            ],
        ]);

        $updatePresensiRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rekap.izin', 1);

        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $pertemuanId,
            'mahasiswa_id' => $mhs->id,
            'status' => 'izin',
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'batch_update_attendance',
            'record_id' => (string) $pertemuanId,
        ]);

        // 7. Selesaikan Sesi Perkuliahan
        $selesaiRes = $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/selesai");
        $selesaiRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'selesai');

        $this->assertDatabaseHas('siakad_pertemuan', [
            'id' => $pertemuanId,
            'status_pertemuan' => 'selesai',
        ]);

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'finish_perkuliahan_session',
            'record_id' => (string) $pertemuanId,
        ]);

        // 8. Rotate token pada sesi yang sudah selesai menghasilkan 400 Bad Request
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/rotate-token")
            ->assertStatus(400);
    }

    public function test_perkuliahan_mobile_menolak_dosen_bukan_pengampu(): void
    {
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-TI-OTORITAS',
            'nama' => 'Kurikulum Uji Otorisasi',
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'TI602',
            'nama' => 'Keamanan Sesi Perkuliahan',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 6,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'nama_kelas' => 'Keamanan (TI-6B)',
            'kode_kelas' => 'TI-6B',
            'kuota' => 35,
            'is_active' => true,
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
        ]);

        Passport::actingAs($this->dosenUser);
        $mulaiRes = $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}/mulai", [
            'pertemuan_ke' => 1,
            'materi' => 'Sesi yang hanya boleh diakses pengampu',
        ])->assertOk();

        $pertemuanId = $mulaiRes->json('data.id');
        $tokenAwal = $mulaiRes->json('data.token_absensi');

        $mhsUser = User::create([
            'username' => 'mhs.otoritas.001',
            'email' => 'mhs.otoritas@kampus.ac.id',
            'name' => 'Mahasiswa Uji Otorisasi',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'nim' => '2026998',
            'nama' => 'Mahasiswa Uji Otorisasi',
            'nama_lengkap' => 'Mahasiswa Uji Otorisasi',
            'program_studi_id' => $this->dosen->program_studi_id,
            'angkatan' => '2026',
            'status' => 'A',
        ]);

        // Dosen lain tetap memiliki permission siakad.kelas.manage, tetapi bukan pengampu kelas ini.
        $dosenRole = Role::where('slug', 'dosen')->firstOrFail();
        $otherUser = User::create([
            'username' => 'dosen.asing',
            'email' => 'dosen.asing@kampus.ac.id',
            'name' => 'Dr. Siti Aminah, M.Kom',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $otherUser->roles()->attach($dosenRole->id);
        Dosen::create([
            'user_id' => $otherUser->id,
            'nidn' => '0620018802',
            'nip' => '198801012015011002',
            'nama_lengkap' => 'Dr. Siti Aminah, M.Kom',
            'program_studi_id' => $this->dosen->program_studi_id,
            'is_active' => true,
        ]);

        Passport::actingAs($otherUser);

        $this->getJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}")->assertForbidden();
        $this->getJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi")->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}/mulai", [
            'pertemuan_ke' => 1,
            'materi' => 'Upaya pembukaan oleh dosen asing',
        ])->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/rotate-token")->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/selesai")->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi", [
            'items' => [
                ['mahasiswa_id' => $mhs->id, 'status' => 'alfa'],
            ],
        ])->assertForbidden();

        $this->assertDatabaseHas('siakad_pertemuan', [
            'id' => $pertemuanId,
            'status_pertemuan' => 'berlangsung',
            'token_absensi' => $tokenAwal,
        ]);
    }

    public function test_mobile_staff_sippm_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        $pegawai = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);

        $periode = PeriodeHibah::create([
            'tahun_anggaran' => '2026/2027',
            'nama_gelombang' => 'Hibah Penelitian 2026',
            'is_active' => true,
            'tgl_buka_proposal' => '2026-01-01',
            'tgl_tutup_proposal' => '2026-12-31',
        ]);

        $skema = SkemaKegiatan::create([
            'kode' => 'PDP',
            'nama' => 'Penelitian Dosen Pemula',
            'tipe' => 'penelitian',
            'sumber_dana' => 'internal',
            'maksimal_anggaran' => 25000000,
            'is_active' => true,
        ]);

        // 1. GET list proposal awal (kosong)
        $this->getJson('/api/v1/mobile/staff/sippm/proposal')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonCount(0, 'data');

        // 2. POST proposal baru
        $postRes = $this->postJson('/api/v1/mobile/staff/sippm/proposal', [
            'judul' => 'Pengembangan Sistem Mobile Terpadu Kampus',
            'abstrak' => 'Abstrak riset mengenai integrasi sistem mobile di lingkungan kampus.',
            'rumpun_ilmu' => 'Ilmu Komputer',
            'skema_id' => $skema->id,
            'periode_id' => $periode->id,
            'target_tkt' => 3,
            'anggaran_diajukan' => 15000000,
            'file_proposal' => UploadedFile::fake()->create('dokumen_proposal.pdf', 500, 'application/pdf'),
        ]);

        $postRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Pengembangan Sistem Mobile Terpadu Kampus');

        $this->assertDatabaseHas('sippm_proposal_kegiatan', [
            'judul' => 'Pengembangan Sistem Mobile Terpadu Kampus',
            'ketua_pegawai_id' => $pegawai->id,
        ]);

        // 3. GET list proposal setelah dibuat
        $this->getJson('/api/v1/mobile/staff/sippm/proposal')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pengembangan Sistem Mobile Terpadu Kampus');

        // 4. GET list luaran
        $this->getJson('/api/v1/mobile/staff/sippm/luaran')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
    }

    public function test_mobile_staff_sikeu_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        Pegawai::firstOrCreate(
            ['user_id' => $this->dosenUser->id],
            [
                'nip' => '198801012015011001',
                'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
                'status_kepegawaian' => 'tetap',
                'status' => 'aktif',
            ]
        );

        // 1. GET riwayat payroll
        $this->getJson('/api/v1/mobile/staff/sikeu/payroll')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);

        // 2. GET list pengajuan (awal kosong)
        $this->getJson('/api/v1/mobile/staff/sikeu/pengajuan')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta'])
            ->assertJsonCount(0, 'data');

        // 3. POST pengajuan keuangan baru
        $res = $this->postJson('/api/v1/mobile/staff/sikeu/pengajuan', [
            'judul_pengajuan' => 'Reimbursement Pembelian Perangkat Praktikum',
            'deskripsi' => 'Penggantian dana pembelian modul Arduino untuk perkuliahan IoT.',
            'jenis_pengajuan' => 'reimbursement',
            'nominal_diajukan' => 750000,
            'nama_bank_penerima' => 'Bank Mandiri',
            'nomor_rekening_penerima' => '1234567890',
            'nama_rekening_penerima' => 'Dr. Budi Santoso',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Reimbursement Pembelian Perangkat Praktikum');

        $this->assertDatabaseHas('sikeu_pengajuan_pencairan_kas', [
            'pemohon_id' => $this->dosenUser->id,
            'judul_pengajuan' => 'Reimbursement Pembelian Perangkat Praktikum',
        ]);

        // 4. GET list pengajuan setelah create
        $this->getJson('/api/v1/mobile/staff/sikeu/pengajuan')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_mobile_staff_sinapra_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        // 1. POST laporan kerusakan sarana baru
        $res = $this->postJson('/api/v1/mobile/staff/sinapra/tiket', [
            'judul' => 'AC Lab Komputer 1 Rusak',
            'deskripsi_kerusakan' => 'AC tidak mengeluarkan udara dingin dan meneteskan air.',
            'prioritas' => 'tinggi',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'AC Lab Komputer 1 Rusak');

        $this->assertDatabaseHas('sinapra_maintenance_log', [
            'pelapor_id' => $this->dosenUser->id,
            'judul' => 'AC Lab Komputer 1 Rusak',
        ]);

        // 2. GET list tiket sarana
        $this->getJson('/api/v1/mobile/staff/sinapra/tiket')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'AC Lab Komputer 1 Rusak');

        // 3. GET list peminjaman sarana
        $this->getJson('/api/v1/mobile/staff/sinapra/peminjaman')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
    }

    public function test_mobile_staff_arsip_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        $this->getJson('/api/v1/mobile/staff/arsip/e-file')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/mobile/staff/arsip/surat')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
    }

    public function test_mobile_staff_lms_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        $this->getJson('/api/v1/mobile/staff/lms/bahan-ajar')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/mobile/staff/lms/pengumuman')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
    }

    public function test_mobile_staff_spmb_referral_endpoints(): void
    {
        Passport::actingAs($this->dosenUser);

        // 1. GET referral (memastikan kode referral dan format MobileFeatureItem ada)
        $res = $this->getJson('/api/v1/mobile/staff/spmb/referral');
        $res->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'meta',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'status',
                        'date',
                        'amount',
                        'referral_code',
                        'kode_referral',
                        'share_url',
                        'raw',
                    ],
                ],
            ]);

        // 2. POST referral (generate / share)
        $postRes = $this->postJson('/api/v1/mobile/staff/spmb/referral');
        $postRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'referral_code',
                    'kode_referral',
                    'share_url',
                    'qr_payload',
                    'stats',
                ],
            ]);

        $expectedBase = rtrim((string) (config('spmb.public_url') ?: config('app.frontend_url') ?: config('app.url')), '/');
        $this->assertStringStartsWith($expectedBase . '/spmb/daftar?ref=', $postRes->json('data.share_url'));
        $this->assertEquals($postRes->json('data.share_url'), $postRes->json('data.qr_payload'));

        // 3. Verifikasi konfigurasi dinamis dari env RAG (FRONTEND_URL / SPMB_PUBLIC_URL)
        config(['spmb.public_url' => 'https://pmb.poltekindonusa.ac.id']);
        $customRes = $this->postJson('/api/v1/mobile/staff/spmb/referral');
        $this->assertStringStartsWith('https://pmb.poltekindonusa.ac.id/spmb/daftar?ref=', $customRes->json('data.share_url'));
    }

    public function test_mobile_staff_simpeg_self_service_gateway(): void
    {
        Passport::actingAs($this->dosenUser);

        $pegawai = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);

        $jenisCuti = MasterJenisCuti::firstOrCreate(
            ['kode' => 'CUTI_TAHUNAN'],
            ['nama' => 'Cuti Tahunan', 'tipe_durasi' => 'fleksibel', 'is_active' => true]
        );
        $jenisIzin = MasterJenisIzinJamKerja::firstOrCreate(
            ['kode' => 'PRIBADI'],
            ['nama' => 'Keperluan Pribadi', 'is_active' => true]
        );
        $kategori = MasterKategoriKegiatanTugas::firstOrCreate(
            ['nama' => 'Kegiatan Dinas'],
            ['is_active' => true]
        );
        $transportasi = MasterJenisTransportasi::firstOrCreate(
            ['kode' => 'PRIBADI'],
            ['nama' => 'Kendaraan Pribadi', 'is_active' => true]
        );
        $kategoriSk = MasterKategoriSk::firstOrCreate(
            ['kode' => 'PENUGASAN'],
            ['nama' => 'SK Penugasan', 'is_active' => true]
        );

        // 1. Cuti: daftar, pengajuan berkas privat, dan filter.
        $this->getJson('/api/v1/mobile/staff/simpeg/cuti')
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);

        $cutiRes = $this->postJson('/api/v1/mobile/staff/simpeg/cuti', [
            'master_jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-03',
            'alasan' => 'Keperluan keluarga mendesak',
            'file_pendukung' => UploadedFile::fake()->create('pendukung.pdf', 100, 'application/pdf'),
        ]);
        $cutiRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.raw.jumlah_hari', 3);
        $this->assertStringContainsString('/api/files/view?', (string) $cutiRes->json('data.file_url'));
        $this->assertDatabaseHas('simpeg_pengajuan_cuti', [
            'pegawai_id' => $pegawai->id,
            'master_jenis_cuti_id' => $jenisCuti->id,
            'jenis_cuti' => 'tahunan',
            'status_approval' => 'pending',
        ]);
        $cutiPath = (string) PengajuanCuti::where('pegawai_id', $pegawai->id)->value('file_pendukung');
        $this->assertStringStartsWith('simpeg/cuti/', $cutiPath);
        $this->assertStringNotContainsString('storage/', $cutiPath);

        $this->getJson('/api/v1/mobile/staff/simpeg/cuti?status=pending&search=keluarga')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');

        // 2. Izin jam kerja.
        $izinRes = $this->postJson('/api/v1/mobile/staff/simpeg/izin-kerja', [
            'master_jenis_izin_id' => $jenisIzin->id,
            'tanggal' => '2026-12-04',
            'jam_mulai' => '08:00',
            'jam_selesai' => '11:00',
            'alasan' => 'Mengurus urusan pribadi di luar kampus',
        ]);
        $izinRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'menunggu');
        $this->assertDatabaseHas('simpeg_izin_jam_kerja', [
            'pegawai_id' => $pegawai->id,
            'master_jenis_izin_id' => $jenisIzin->id,
            'status' => 'menunggu',
        ]);
        $this->getJson('/api/v1/mobile/staff/simpeg/izin-kerja?search=pribadi')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 3. Surat tugas + unggah LPJ (hanya setelah disetujui).
        $suratRes = $this->postJson('/api/v1/mobile/staff/simpeg/surat-tugas', [
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Koordinasi program dengan kampus mitra',
            'tempat_berangkat' => 'Kampus',
            'lokasi_tujuan' => 'Bandung',
            'tanggal_berangkat' => '2026-12-10',
            'tanggal_kembali' => '2026-12-11',
            'tanggal_mulai' => '2026-12-10',
            'tanggal_selesai' => '2026-12-11',
            'maksud_tujuan' => 'Koordinasi implementasi kurikulum',
            'estimasi_biaya' => 750000,
            'file_surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
        ]);
        $suratRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'diajukan');
        $suratId = (int) $suratRes->json('data.id');
        $this->assertDatabaseHas('simpeg_surat_tugas', [
            'id' => $suratId,
            'pegawai_id' => $pegawai->id,
            'status' => 'diajukan',
        ]);

        $this->postJson("/api/v1/mobile/staff/simpeg/surat-tugas/{$suratId}/lpj", [
            'file_lpj' => UploadedFile::fake()->create('lpj.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonPath('status', 'error');

        SuratTugas::whereKey($suratId)->update(['status' => 'disetujui']);
        $lpjRes = $this->postJson("/api/v1/mobile/staff/simpeg/surat-tugas/{$suratId}/lpj", [
            'file_lpj' => UploadedFile::fake()->create('lpj.pdf', 100, 'application/pdf'),
            'laporan_kegiatan' => 'Kegiatan koordinasi selesai dilaksanakan.',
            'biaya_realisasi' => 700000,
        ]);
        $lpjRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'disetujui');
        $this->assertStringContainsString('/api/files/view?', (string) $lpjRes->json('data.file_lpj_url'));
        $this->assertDatabaseHas('simpeg_surat_tugas', [
            'id' => $suratId,
            'status_pencairan' => 'lpj_diunggah',
        ]);
        $this->getJson('/api/v1/mobile/staff/simpeg/surat-tugas?search=kampus+mitra')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 4. SK pegawai.
        $skRes = $this->postJson('/api/v1/mobile/staff/simpeg/sk-pegawai', [
            'kategori_sk_id' => $kategoriSk->id,
            'nomor_sk' => 'SK/123/2026',
            'judul_sk' => 'SK Penugasan Dosen',
            'tanggal_sk' => '2026-12-01',
            'tmt_sk' => '2026-12-01',
            'pejabat_penetap' => 'Rektor',
            'file_sk' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
        ]);
        $skRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('simpeg_sk_pegawai', [
            'pegawai_id' => $pegawai->id,
            'kategori_sk_id' => $kategoriSk->id,
            'nomor_sk' => 'SK/123/2026',
            'status_verifikasi' => 'pending',
        ]);
        $this->getJson('/api/v1/mobile/staff/simpeg/sk-pegawai?search=SK%2F123')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 5. Kompetensi: sertifikasi / tes / pelatihan milik pegawai login.
        $jenisSertifikasi = MasterJenisSertifikasi::firstOrCreate(
            ['kode' => 'SERDOS'],
            ['nama' => 'Sertifikasi Dosen', 'is_active' => true]
        );
        SertifikasiDosen::create([
            'pegawai_id' => $pegawai->id,
            'jenis_sertifikasi_id' => $jenisSertifikasi->id,
            'nama_sertifikat' => 'Sertifikasi Dosen Alexander',
            'bidang_studi' => 'Ilmu Komputer',
            'penyelenggara' => 'Kementerian Pendidikan',
            'tahun_sertifikasi' => 2025,
            'file_path' => 'simpeg/kompetensi/alexander.pdf',
        ]);
        $this->getJson('/api/v1/mobile/staff/simpeg/kompetensi?jenis=sertifikasi&search=Alexander')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sertifikasi Dosen Alexander');
        $this->getJson('/api/v1/mobile/staff/simpeg/kompetensi?jenis=tes')
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
        $this->getJson('/api/v1/mobile/staff/simpeg/kompetensi?jenis=pelatihan')
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data', 'meta']);
    }

    public function test_mobile_staff_simpeg_scope_and_rbac(): void
    {
        $otherUser = User::create([
            'username' => 'staff.lain',
            'email' => 'staff.lain@kampus.ac.id',
            'name' => 'Staff Lain',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $otherPegawai = Pegawai::create([
            'user_id' => $otherUser->id,
            'nip' => '199002022018012002',
            'nama_lengkap' => 'Staff Lain',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);
        $jenisCuti = MasterJenisCuti::firstOrCreate(
            ['kode' => 'CUTI_TAHUNAN'],
            ['nama' => 'Cuti Tahunan', 'is_active' => true]
        );
        $otherCuti = PengajuanCuti::create([
            'pegawai_id' => $otherPegawai->id,
            'master_jenis_cuti_id' => $jenisCuti->id,
            'jenis_cuti' => 'tahunan',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-02',
            'jumlah_hari' => 2,
            'alasan' => 'Cuti pegawai lain',
        ]);

        // Pengajuan pegawai lain tidak boleh muncul pada daftar milik dosen.
        Passport::actingAs($this->dosenUser);
        $cutiList = $this->getJson('/api/v1/mobile/staff/simpeg/cuti')->assertOk();
        $this->assertFalse(collect($cutiList->json('data'))->contains('id', (string) $otherCuti->id));

        // LPJ milik pegawai lain tidak boleh diakses penanggung jawab lain.
        $kategori = MasterKategoriKegiatanTugas::firstOrCreate(
            ['nama' => 'Dinas'],
            ['is_active' => true]
        );
        $transportasi = MasterJenisTransportasi::firstOrCreate(
            ['kode' => 'PRIBADI'],
            ['nama' => 'Pribadi', 'is_active' => true]
        );
        $otherSurat = SuratTugas::create([
            'pegawai_id' => $otherPegawai->id,
            'kategori_kegiatan_id' => $kategori->id,
            'jenis_transportasi_id' => $transportasi->id,
            'nama_kegiatan' => 'Kegiatan pegawai lain',
            'tempat_berangkat' => 'Kampus',
            'lokasi_tujuan' => 'Surabaya',
            'tanggal_berangkat' => '2026-12-10',
            'tanggal_kembali' => '2026-12-11',
            'tanggal_mulai' => '2026-12-10',
            'tanggal_selesai' => '2026-12-11',
            'status' => 'disetujui',
        ]);
        $this->postJson("/api/v1/mobile/staff/simpeg/surat-tugas/{$otherSurat->id}/lpj", [
            'file_lpj' => UploadedFile::fake()->create('lpj.pdf', 100, 'application/pdf'),
        ])->assertStatus(403)->assertJsonPath('status', 'error');

        // Akun tanpa permission ditolak untuk seluruh gateway baru.
        $outsider = User::create([
            'username' => 'tanpa.akses',
            'email' => 'tanpa.akses@kampus.ac.id',
            'name' => 'Tanpa Akses',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Passport::actingAs($outsider);

        foreach ([
            '/api/v1/mobile/staff/simpeg/cuti',
            '/api/v1/mobile/staff/simpeg/izin-kerja',
            '/api/v1/mobile/staff/simpeg/surat-tugas',
            '/api/v1/mobile/staff/simpeg/sk-pegawai',
            '/api/v1/mobile/staff/simpeg/kompetensi',
            '/api/v1/mobile/staff/sikeu/payroll/1',
            '/api/v1/mobile/staff/arsip/e-file/1',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertStatus(403)->assertJsonPath('status', 'error');
        }
        $this->postJson('/api/v1/mobile/staff/simpeg/cuti', [])->assertStatus(403);
        $this->postJson('/api/v1/mobile/staff/arsip/e-file', [])->assertStatus(403);
    }

    public function test_mobile_staff_payroll_detail_and_pdf_slip(): void
    {
        Passport::actingAs($this->dosenUser);

        $pegawai = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);
        $gaji = GajiPegawai::create([
            'pegawai_id' => $pegawai->id,
            'periode_bulan_tahun' => '2026-11',
            'gaji_pokok' => 5000000,
            'total_tunjangan' => 1000000,
            'total_potongan' => 500000,
            'gaji_bersih' => 5500000,
            'status_transfer' => 'paid',
        ]);
        GajiDetail::create([
            'gaji_pegawai_id' => $gaji->id,
            'nama_komponen' => 'Tunjangan Fungsional',
            'jenis' => 'pendapatan',
            'nominal' => 1000000,
        ]);
        GajiDetail::create([
            'gaji_pegawai_id' => $gaji->id,
            'nama_komponen' => 'BPJS Kesehatan',
            'jenis' => 'potongan',
            'nominal' => 500000,
        ]);

        $detail = $this->getJson("/api/v1/mobile/staff/sikeu/payroll/{$gaji->id}");
        $detail->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', (string) $gaji->id)
            ->assertJsonPath('data.amount', 'Rp 5.500.000')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.pendapatan.0.komponen', 'Tunjangan Fungsional')
            ->assertJsonPath('data.potongan.0.komponen', 'BPJS Kesehatan');

        $slip = $this->get("/api/v1/mobile/staff/sikeu/payroll/{$gaji->id}/slip");
        $slip->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $slip->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $slip->getContent());
        $this->assertStringContainsString('attachment', (string) $slip->headers->get('Content-Disposition'));

        // Gaji milik pegawai lain tidak boleh diakses.
        $otherUser = User::create([
            'username' => 'pegawai.lain',
            'email' => 'pegawai.lain@kampus.ac.id',
            'name' => 'Pegawai Lain',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $otherPegawai = Pegawai::create([
            'user_id' => $otherUser->id,
            'nip' => '199002022018012003',
            'nama_lengkap' => 'Pegawai Lain',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);
        $otherGaji = GajiPegawai::create([
            'pegawai_id' => $otherPegawai->id,
            'periode_bulan_tahun' => '2026-11',
            'gaji_bersih' => 4000000,
        ]);
        $this->getJson("/api/v1/mobile/staff/sikeu/payroll/{$otherGaji->id}")->assertNotFound();
        $this->get("/api/v1/mobile/staff/sikeu/payroll/{$otherGaji->id}/slip")->assertNotFound();
    }

    public function test_mobile_staff_arsip_efile_detail_download_upload(): void
    {
        Passport::actingAs($this->dosenUser);

        $pegawai = Pegawai::create([
            'user_id' => $this->dosenUser->id,
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);

        $this->postJson('/api/v1/mobile/staff/arsip/e-file', [
            'nama_dokumen' => 'KTP Pegawai',
            'jenis_dokumen' => 'ktp',
        ])->assertStatus(422)->assertJsonPath('status', 'error');

        $upload = $this->postJson('/api/v1/mobile/staff/arsip/e-file', [
            'nama_dokumen' => 'KTP Pegawai',
            'jenis_dokumen' => 'ktp',
            'file' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
        ]);
        $upload->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.jenis_dokumen', 'ktp')
            ->assertJsonPath('data.status', 'pending');
        $this->assertStringContainsString('/api/files/view?', (string) $upload->json('data.file_url'));

        $dokumenId = (int) $upload->json('data.id');
        $this->assertDatabaseHas('simpeg_dokumen_pegawai', [
            'id' => $dokumenId,
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => 'KTP Pegawai',
            'jenis_dokumen' => 'ktp',
            'status_verifikasi' => 'pending',
        ]);
        $filePath = (string) DokumenPegawai::findOrFail($dokumenId)->file_path;
        $this->assertStringStartsWith('simpeg/e-file/', $filePath);
        $this->assertStringNotContainsString('storage/', $filePath);
        $this->assertStringNotContainsString('http', $filePath);

        $this->getJson("/api/v1/mobile/staff/arsip/e-file/{$dokumenId}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', (string) $dokumenId);

        $download = $this->getJson("/api/v1/mobile/staff/arsip/e-file/{$dokumenId}/download");
        $download->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', (string) $dokumenId)
            ->assertJsonPath('data.expires_in_seconds', 900);
        $this->assertStringContainsString('/api/files/view?', (string) $download->json('data.file_url'));

        $otherUser = User::create([
            'username' => 'arsip.lain',
            'email' => 'arsip.lain@kampus.ac.id',
            'name' => 'Arsip Lain',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $otherPegawai = Pegawai::create([
            'user_id' => $otherUser->id,
            'nip' => '199002022018012004',
            'nama_lengkap' => 'Arsip Lain',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
        ]);
        $otherDokumen = DokumenPegawai::create([
            'pegawai_id' => $otherPegawai->id,
            'nama_dokumen' => 'Ijazah Lain',
            'jenis_dokumen' => 'ijazah',
            'file_path' => 'simpeg/e-file/2026/11/lain.pdf',
        ]);
        $this->getJson("/api/v1/mobile/staff/arsip/e-file/{$otherDokumen->id}")->assertNotFound();
        $this->getJson("/api/v1/mobile/staff/arsip/e-file/{$otherDokumen->id}/download")->assertNotFound();
    }

    public function test_mobile_staff_spmb_referral_count_aliases(): void
    {
        Passport::actingAs($this->dosenUser);

        // Data pendaftar riil dibutuhkan karena spmb_referral_usages.pendaftaran_id wajib.
        $jalurId = DB::table('spmb_jalur_masuk')->insertGetId([
            'kode' => 'REG',
            'nama' => 'Reguler',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gelombangId = DB::table('spmb_gelombang_penerimaan')->insertGetId([
            'jalur_masuk_id' => $jalurId,
            'nama' => 'Gelombang Reguler 2026',
            'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-12-31',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ReferralUsage::STATUS_CLAIMED,
            ReferralUsage::STATUS_QUALIFIED,
            ReferralUsage::STATUS_QUALIFIED,
            ReferralUsage::STATUS_REWARDED,
            ReferralUsage::STATUS_CANCELLED,
        ] as $index => $status) {
            $sequence = $index + 1;
            $pendaftaranId = DB::table('spmb_pendaftaran_calon_mhs')->insertGetId([
                'gelombang_id' => $gelombangId,
                'user_id' => $this->dosenUser->id,
                'program_studi_id' => $this->dosen->program_studi_id,
                'no_pendaftaran' => 'SPMB-REF-' . $sequence,
                'nama_lengkap' => 'Calon Mahasiswa ' . $sequence,
                'nik' => '1234567890123' . $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            ReferralUsage::create([
                'pendaftaran_id' => $pendaftaranId,
                'referee_user_id' => $this->dosenUser->id,
                'referrer_user_id' => $this->dosenUser->id,
                'referral_code' => $this->dosenUser->referral_code,
                'status' => $status,
            ]);
        }

        $stats = $this->postJson('/api/v1/mobile/staff/spmb/referral')
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->json('data.stats');

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(5, $stats['mahasiswa_baru']);
        $this->assertEquals(2, $stats['qualified']);
        $this->assertEquals(2, $stats['dapat_direimburse']);
        $this->assertEquals(1, $stats['rewarded']);
        $this->assertEquals(1, $stats['sudah_direimburse']);
    }

    private function buatKelasDanMulaiSesi(string $kode): int
    {
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->dosen->program_studi_id,
            'kode' => 'KUR-' . $kode,
            'nama' => 'Kurikulum Uji ' . $kode,
            'tahun_berlaku' => 2026,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'MK-' . $kode,
            'nama' => 'Mata Kuliah Uji ' . $kode,
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 6,
            'tipe' => 'wajib',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $this->dosen->program_studi_id,
            'mata_kuliah_id' => $mk->id,
            'nama_kelas' => 'Kelas Uji ' . $kode,
            'kode_kelas' => $kode,
            'kuota' => 35,
            'is_active' => true,
        ]);

        DosenPengampu::create([
            'kelas_id' => $kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran' => 'Pengampu Utama',
        ]);

        Passport::actingAs($this->dosenUser);
        $res = $this->postJson("/api/v1/mobile/staff/perkuliahan/kelas/{$kelas->id}/mulai", [
            'pertemuan_ke' => 1,
            'materi' => 'Sesi uji validasi presensi',
        ])->assertOk();

        return (int) $res->json('data.id');
    }

    public function test_presensi_manual_menolak_mahasiswa_dari_kelas_lain(): void
    {
        $pertemuanId = $this->buatKelasDanMulaiSesi('TI-PRES-LINTAS');

        $mhsUser = User::create([
            'username' => 'mhs.lintas.kelas',
            'email' => 'mhs.lintas@kampus.ac.id',
            'name' => 'Mahasiswa Kelas Lain',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'nim' => '2026997',
            'nama' => 'Mahasiswa Kelas Lain',
            'nama_lengkap' => 'Mahasiswa Kelas Lain',
            'program_studi_id' => $this->dosen->program_studi_id,
            'angkatan' => '2026',
            'status' => 'A',
        ]);

        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi", [
            'items' => [
                ['mahasiswa_id' => $mhs->id, 'status' => 'hadir'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.mahasiswa_id']);

        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $pertemuanId,
            'mahasiswa_id' => $mhs->id,
        ]);
    }

    public function test_feature_flag_presensi_menolak_endpoint_mobile_staff(): void
    {
        $pertemuanId = $this->buatKelasDanMulaiSesi('TI-PRES-FLAG');

        SystemSetting::set('lms_fitur_presensi', '0');
        LmsSetting::flushCache();

        $this->getJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi")
            ->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/presensi", [
            'items' => [],
        ])->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/rotate-token")
            ->assertForbidden();
        $this->postJson("/api/v1/mobile/staff/perkuliahan/pertemuan/{$pertemuanId}/selesai")
            ->assertForbidden();
    }
}
