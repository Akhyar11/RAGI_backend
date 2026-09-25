<?php

namespace Tests\Feature;

use App\Models\Lms\MateriPertemuan;
use App\Models\Lms\Tugas;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadLmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected Dosen $dosen;
    protected Mahasiswa $mahasiswa;
    protected ProgramStudi $prodi;
    protected TahunAkademik $ta;
    protected Kurikulum $kurikulum;
    protected MataKuliah $mataKuliah;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;
    protected KrsDetail $krsDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('local');

        // 1. Buat User Dosen & Mahasiswa
        $this->userDosen = User::factory()->create([
            'username' => 'dosen.test',
            'email'    => 'dosen@test.ac.id',
        ]);
        $this->userMhs = User::factory()->create([
            'username' => 'mhs.test',
            'email'    => 'mhs@test.ac.id',
        ]);

        // 2. Data Master Akademik
        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-LMS',
            'nama'       => 'Informatika LMS',
            'jenjang'    => 'S1',
        ]);

        $this->ta = TahunAkademik::create([
            'kode'         => '20261',
            'nama'         => '2026/2027 Ganjil',
            'semester'     => 'ganjil',
            'tahun_mulai'  => 2026,
            'tahun_selesai'=> 2027,
            'is_aktif'     => true,
        ]);

        $this->kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode'             => 'KUR-LMS',
            'nama'             => 'Kurikulum LMS 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $this->mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $this->kurikulum->id,
            'kode_mk'          => 'IF201',
            'nama'             => 'Pemrograman Web LMS',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 3,
            'is_active'        => true,
        ]);

        $this->dosen = Dosen::create([
            'user_id'          => $this->userDosen->id,
            'program_studi_id' => $this->prodi->id,
            'nidn'             => '0011223344',
            'nama_lengkap'     => 'Dosen Pengampu M.Kom',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $this->prodi->id,
            'nim'              => '2026001001',
            'nama_lengkap'     => 'Mahasiswa LMS Testing',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id' => $this->prodi->id,
            'mata_kuliah_id'   => $this->mataKuliah->id,
            'tahun_akademik_id'=> $this->ta->id,
            'kode_kelas'       => 'IF-LMS-A',
            'nama_kelas'       => 'Kelas LMS A',
            'kapasitas'        => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran'    => 'pengampu_utama',
        ]);

        // Buat Pertemuan Ke-1
        $this->pertemuan = Pertemuan::create([
            'kelas_id'         => $this->kelas->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Pengenalan LMS Perkuliahan',
            'status_pertemuan' => 'berlangsung',
        ]);

        // Setup KRS Mahasiswa pada kelas ini
        $krs = Krs::create([
            'mahasiswa_id'     => $this->mahasiswa->id,
            'tahun_akademik_id'=> $this->ta->id,
            'status'           => 'disetujui',
            'total_sks'        => 3,
        ]);

        $this->krsDetail = KrsDetail::create([
            'krs_id'   => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status'   => 'aktif',
        ]);

        // 3. Setup Master Referensi Items
        $now = now();
        $refItems = [
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'teks', 'nama' => 'Artikel Teks', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'file', 'nama' => 'Dokumen File', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'link_eksternal', 'nama' => 'Link', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'video_embed', 'nama' => 'Video', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_izin', 'kode' => 'sakit', 'nama' => 'Sakit', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_izin', 'kode' => 'izin', 'nama' => 'Izin', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'hadir', 'nama' => 'Hadir', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'sakit', 'nama' => 'Sakit', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'izin', 'nama' => 'Izin', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'alfa', 'nama' => 'Alfa', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_persetujuan_izin', 'kode' => 'disetujui', 'nama' => 'Disetujui', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_persetujuan_izin', 'kode' => 'ditolak', 'nama' => 'Ditolak', 'is_active' => true],
        ];
        foreach ($refItems as $item) {
            \Illuminate\Support\Facades\DB::table('spmb_master_referensi')->updateOrInsert(
                ['modul' => $item['modul'], 'tipe' => $item['tipe'], 'kode' => $item['kode']],
                array_merge($item, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // 4. Setup Role & Permissions RBAC
        $roleDosen = \App\Models\Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = \App\Models\Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);

        $permList = [
            'siakad.kelas.read'   => 'read',
            'siakad.kelas.manage' => 'update',
            'siakad.nilai.manage' => 'update',
            'siakad.krs.read'     => 'read',
        ];
        $createdPerms = [];
        foreach ($permList as $slug => $act) {
            $createdPerms[$slug] = \App\Models\Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'siakad', 'action' => $act]
            );
        }

        $roleDosen->permissions()->syncWithoutDetaching([
            $createdPerms['siakad.kelas.read']->id,
            $createdPerms['siakad.kelas.manage']->id,
            $createdPerms['siakad.nilai.manage']->id,
        ]);
        $roleMhs->permissions()->syncWithoutDetaching([
            $createdPerms['siakad.kelas.read']->id,
            $createdPerms['siakad.krs.read']->id,
        ]);

        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
    }

    public function test_can_view_lms_overview_and_pertemuan(): void
    {
        Passport::actingAs($this->userDosen);

        $responseOverview = $this->getJson("/api/v1/siakad/lms/kelas/{$this->kelas->id}/overview");
        $responseOverview->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'kelas' => ['id', 'nama_kelas'],
                    'lms_setting',
                    'progress',
                    'statistik',
                ],
            ]);

        $responsePertemuan = $this->getJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}");
        $responsePertemuan->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['pertemuan', 'materi_list', 'tugas_list'],
            ]);
    }

    public function test_user_can_get_my_kelas_and_my_tugas_paginated(): void
    {
        // 1. Dosen view my kelas
        Passport::actingAs($this->userDosen);
        $resKelasDosen = $this->getJson('/api/v1/siakad/lms/kelas/my?per_page=10&sort_by=nama_kelas&sort_order=asc');
        $resKelasDosen->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'filters' => [
                    'search',
                    'sort_by',
                    'sort_order',
                ],
            ]);

        // 2. Mahasiswa view my kelas
        Passport::actingAs($this->userMhs);
        $resKelasMhs = $this->getJson('/api/v1/siakad/lms/kelas/my');
        $resKelasMhs->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'filters',
            ]);

        // 3. Mahasiswa view my tugas
        $resTugasMhs = $this->getJson('/api/v1/siakad/lms/tugas/my');
        $resTugasMhs->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'filters',
            ]);
    }

    public function test_dosen_can_manage_materi_dan_upload_file(): void
    {
        Passport::actingAs($this->userDosen);

        $fileMock = UploadedFile::fake()->create('slide_pertemuan_1.pdf', 1024, 'application/pdf');

        $refFile = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')
            ->where('tipe', 'tipe_konten_lms')
            ->where('kode', 'file')
            ->first();

        // 1. Tambah Materi Berkas
        $responseStore = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/materi", [
            'judul'          => 'Slide Pengantar Kuliah',
            'deskripsi'      => 'Silakan unduh dan pelajari materi presentasi.',
            'tipe_konten_id' => $refFile->id,
            'urutan'         => 1,
            'is_published'   => true,
            'file'           => $fileMock,
        ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $materiId = $responseStore->json('data.id');
        $this->assertDatabaseHas('lms_materi_pertemuan', [
            'id'    => $materiId,
            'judul' => 'Slide Pengantar Kuliah',
        ]);

        // 2. Upload Berkas Tambahan ke Materi
        $fileExtra = UploadedFile::fake()->create('source_code.zip', 2048, 'application/zip');
        $responseUpload = $this->postJson("/api/v1/siakad/lms/materi/{$materiId}/file", [
            'file' => $fileExtra,
        ]);

        $responseUpload->assertStatus(201);
        $fileId = $responseUpload->json('data.id');
        $this->assertDatabaseHas('lms_materi_file', ['id' => $fileId]);

        // 3. Hapus File Tambahan
        $responseDeleteFile = $this->deleteJson("/api/v1/siakad/lms/materi-file/{$fileId}");
        $responseDeleteFile->assertStatus(200);
        $this->assertDatabaseMissing('lms_materi_file', ['id' => $fileId]);

        // 4. Update Materi
        $responseUpdate = $this->putJson("/api/v1/siakad/lms/materi/{$materiId}", [
            'judul'          => 'Slide Pengantar Kuliah (Revisi)',
            'deskripsi'      => 'Deskripsi revisi terbaru.',
            'tipe_konten_id' => $refFile->id,
            'is_published'   => true,
        ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('lms_materi_pertemuan', [
            'id'    => $materiId,
            'judul' => 'Slide Pengantar Kuliah (Revisi)',
        ]);
    }

    public function test_tugas_creation_submission_and_obe_sync(): void
    {
        Passport::actingAs($this->userDosen);

        // Buat Komponen Penilaian OBE
        $komponenObe = KomponenPenilaian::create([
            'kelas_id'         => $this->kelas->id,
            'nama_komponen'    => 'Tugas 1 - LMS',
            'teknik_penilaian' => 'tugas',
            'bobot'            => 15.00,
            'urutan'           => 1,
            'is_aktif'         => true,
        ]);

        // 1. Dosen membuat tugas terhubung OBE
        $responseTugas = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/tugas", [
            'judul'                 => 'Tugas 1: Framework MVC',
            'deskripsi'             => 'Implementasikan konsep MVC dalam sebuah program mini.',
            'deadline_at'           => now()->addDays(7)->toDateTimeString(),
            'komponen_penilaian_id' => $komponenObe->id,
            'max_file_size_mb'      => 10,
            'is_published'          => true,
        ]);

        $responseTugas->assertStatus(201);
        $tugasId = $responseTugas->json('data.id');

        // 2. Mahasiswa mengumpulkan tugas
        Passport::actingAs($this->userMhs);
        $fileTugas = UploadedFile::fake()->create('tugas_mvc_2026001001.zip', 512, 'application/zip');

        $responseKumpul = $this->postJson("/api/v1/siakad/lms/tugas/{$tugasId}/kumpul", [
            'catatan_mahasiswa' => 'Tugas sudah selesai dikerjakan sesuai spesifikasi.',
            'file'              => $fileTugas,
        ]);

        $responseKumpul->assertStatus(200);
        $pengumpulanId = $responseKumpul->json('data.id');
        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'id'           => $pengumpulanId,
            'mahasiswa_id' => $this->mahasiswa->id,
        ]);

        // 3. Dosen memberi nilai & auto-sync OBE
        Passport::actingAs($this->userDosen);
        $responseNilai = $this->putJson("/api/v1/siakad/lms/pengumpulan/{$pengumpulanId}/nilai", [
            'nilai'          => 88.50,
            'feedback_dosen' => 'Bagus sekali, kode rapi dan modular.',
        ]);

        $responseNilai->assertStatus(200);
        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'id'    => $pengumpulanId,
            'nilai' => 88.50,
        ]);

        // Verifikasi ter-sync ke tabel OBE siakad_nilai_komponen_mhs
        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'krs_detail_id'         => $this->krsDetail->id,
            'komponen_penilaian_id' => $komponenObe->id,
            'nilai_angka'           => 88.50,
        ]);
    }

    public function test_token_absensi_realtime_and_student_input(): void
    {
        // 1. Dosen generate token
        Passport::actingAs($this->userDosen);
        $responseToken = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/token");
        $responseToken->assertStatus(200);

        $tokenGenerated = $responseToken->json('data.token');
        $this->assertNotEmpty($tokenGenerated);
        $this->assertEquals(6, strlen($tokenGenerated));

        // 2. Mahasiswa input token valid
        Passport::actingAs($this->userMhs);
        $responseInput = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/input-token", [
            'token' => $tokenGenerated,
        ]);
        $responseInput->assertStatus(200);

        // Verifikasi kehadiran tercatat 'hadir'
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'hadir',
        ]);

        // 3. Percobaan input token salah oleh mahasiswa lain (atau token acak)
        $responseInvalid = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/input-token", [
            'token' => '999999',
        ]);
        $responseInvalid->assertStatus(422);
    }

    public function test_pengajuan_izin_dan_persetujuan_dosen(): void
    {
        // 1. Mahasiswa mengajukan izin sakit
        Passport::actingAs($this->userMhs);
        $fileSurat = UploadedFile::fake()->create('surat_dokter.pdf', 300, 'application/pdf');

        $refSakit = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')
            ->where('tipe', 'tipe_izin')
            ->where('kode', 'sakit')
            ->first();

        $responseIzin = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/izin", [
            'tipe_izin_id' => $refSakit->id,
            'alasan'       => 'Sakit tifus rawat inap di RS.',
            'file_surat'   => $fileSurat,
        ]);

        $responseIzin->assertStatus(201);
        $izinId = $responseIzin->json('data.id');
        $this->assertDatabaseHas('lms_izin_absensi', [
            'id'     => $izinId,
            'status' => 'pending',
        ]);

        // 2. Dosen memproses izin -> disetujui
        Passport::actingAs($this->userDosen);
        $refDisetujui = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')
            ->where('tipe', 'status_persetujuan_izin')
            ->where('kode', 'disetujui')
            ->first();

        $responseProses = $this->patchJson("/api/v1/siakad/lms/izin/{$izinId}/proses", [
            'status_id'     => $refDisetujui->id,
            'catatan_dosen' => 'Semoga lekas pulih.',
        ]);

        $responseProses->assertStatus(200);
        $this->assertDatabaseHas('lms_izin_absensi', [
            'id'     => $izinId,
            'status' => 'disetujui',
        ]);

        // Verifikasi kehadiran di siakad_absensi_mahasiswa otomatis tercatat 'sakit'
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'sakit',
        ]);
    }

    public function test_dosen_can_input_bulk_absensi_and_view_rekap(): void
    {
        Passport::actingAs($this->userDosen);

        $refHadir = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')
            ->where('tipe', 'status_absensi')
            ->where('kode', 'hadir')
            ->first();

        $responseBulk = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/bulk-absensi", [
            'absensi' => [
                [
                    'mahasiswa_id' => $this->mahasiswa->id,
                    'status_id'    => $refHadir->id,
                    'catatan'      => 'Hadir aktif di kelas.',
                ],
            ],
        ]);

        $responseBulk->assertStatus(200);
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'hadir',
        ]);

        // Cek Rekapitulasi Absensi Kelas
        $responseRekap = $this->getJson("/api/v1/siakad/lms/kelas/{$this->kelas->id}/rekap-absensi");
        $responseRekap->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'kelas_id',
                    'total_pertemuan',
                    'batas_min_hadir_persen',
                    'rekapitulasi',
                ],
            ]);
    }
}
