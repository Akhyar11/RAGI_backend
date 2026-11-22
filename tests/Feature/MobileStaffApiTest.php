<?php

namespace Tests\Feature;

use App\Models\Gedung;
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
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sinapra\MaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'simpeg.surat_tugas.read',
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
}
