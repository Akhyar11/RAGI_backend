<?php

namespace Tests\Feature;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * FASE 5 — Verifikasi Menyeluruh E2E Modul LMS.
 *
 * Menguji 4 alur penuh sesuai plan_lms_absensi.md:
 *  1. Dosen input materi -> Mahasiswa download file.
 *  2. Token absensi: generate -> mahasiswa input -> absensi tersimpan (+ token salah & kedaluwarsa ditolak).
 *  3. Tugas & OBE sync: buat tugas link OBE -> submit -> nilai -> masuk siakad_nilai_komponen_mhs (+ overwrite + standalone).
 *  4. Izin: ajukan -> approve -> absensi terupdate otomatis (+ tolak tidak mengubah absensi).
 */
class SiakadLmsE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected Dosen $dosen;
    protected Mahasiswa $mahasiswa;
    protected Kelas $kelas;
    protected Pertemuan $pertemuan;
    protected KrsDetail $krsDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('local');

        $this->userDosen = User::factory()->create(['username' => 'dosen.e2e', 'email' => 'dosen.e2e@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.e2e', 'email' => 'mhs.e2e@test.ac.id']);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-E2E',
            'nama'       => 'Informatika E2E',
            'jenjang'    => 'S1',
        ]);

        $ta = TahunAkademik::create([
            'kode'          => '20261',
            'nama'          => '2026/2027 Ganjil',
            'semester'      => 'ganjil',
            'tahun_mulai'   => 2026,
            'tahun_selesai' => 2027,
            'is_aktif'      => true,
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->prodi->id,
            'kode'             => 'KUR-E2E',
            'nama'             => 'Kurikulum E2E 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF901',
            'nama'             => 'Pengujian E2E LMS',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 3,
            'is_active'        => true,
        ]);

        $this->dosen = Dosen::create([
            'user_id'          => $this->userDosen->id,
            'program_studi_id' => $this->prodi->id,
            'nidn'             => '0099887766',
            'nama_lengkap'     => 'Dosen E2E M.Kom',
            'status_aktif'     => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->userMhs->id,
            'program_studi_id' => $this->prodi->id,
            'nim'              => '2026009001',
            'nama_lengkap'     => 'Mahasiswa E2E Testing',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id'  => $this->prodi->id,
            'mata_kuliah_id'    => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas'        => 'IF-E2E-A',
            'nama_kelas'        => 'Kelas E2E A',
            'kapasitas'         => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $this->dosen->id,
            'peran'    => 'pengampu_utama',
        ]);

        $this->pertemuan = Pertemuan::create([
            'kelas_id'         => $this->kelas->id,
            'pertemuan_ke'     => 1,
            'tanggal'          => '2026-10-01',
            'materi'           => 'Materi E2E Perkuliahan',
            'status_pertemuan' => 'berlangsung',
        ]);

        $krs = Krs::create([
            'mahasiswa_id'      => $this->mahasiswa->id,
            'tahun_akademik_id' => $ta->id,
            'status'            => 'disetujui',
            'total_sks'         => 3,
        ]);

        $this->krsDetail = KrsDetail::create([
            'krs_id'   => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status'   => 'aktif',
        ]);

        $now = now();
        $refItems = [
            ['modul' => 'siakad', 'tipe' => 'tipe_konten_lms', 'kode' => 'file', 'nama' => 'Dokumen File', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_izin', 'kode' => 'sakit', 'nama' => 'Sakit', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'tipe_izin', 'kode' => 'izin', 'nama' => 'Izin', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'hadir', 'nama' => 'Hadir', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'alfa', 'nama' => 'Alfa', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'sakit', 'nama' => 'Sakit', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_persetujuan_izin', 'kode' => 'disetujui', 'nama' => 'Disetujui', 'is_active' => true],
            ['modul' => 'siakad', 'tipe' => 'status_persetujuan_izin', 'kode' => 'ditolak', 'nama' => 'Ditolak', 'is_active' => true],
        ];
        foreach ($refItems as $item) {
            \Illuminate\Support\Facades\DB::table('spmb_master_referensi')->updateOrInsert(
                ['modul' => $item['modul'], 'tipe' => $item['tipe'], 'kode' => $item['kode']],
                array_merge($item, ['created_at' => $now, 'updated_at' => $now])
            );
        }

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

    protected ProgramStudi $prodi;

    public function test_e2e_materi_upload_hingga_mahasiswa_download(): void
    {
        // 1. Dosen input materi + file
        Passport::actingAs($this->userDosen);

        $refFile = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')->where('tipe', 'tipe_konten_lms')->where('kode', 'file')->first();

        $resStore = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/materi", [
            'judul'          => 'Slide E2E: Pengantar Testing',
            'deskripsi'      => 'Materi alur penuh untuk diunduh mahasiswa.',
            'tipe_konten_id' => $refFile->id,
            'urutan'         => 1,
            'is_published'   => true,
            'file'           => UploadedFile::fake()->create('slide_e2e.pdf', 1024, 'application/pdf'),
        ]);

        $resStore->assertStatus(201)->assertJsonPath('status', 'success');
        $materiId = $resStore->json('data.id');

        $this->assertDatabaseHas('lms_materi_pertemuan', ['id' => $materiId, 'judul' => 'Slide E2E: Pengantar Testing']);

        // Ambil file_id hasil upload bawaan
        $fileId = $resStore->json('data.files.0.id') ?? \App\Models\Lms\MateriFile::where('materi_id', $materiId)->value('id');
        $this->assertNotEmpty($fileId);

        // Path di DB harus relatif (tanpa domain) sesuai standar file-upload
        $storedPath = \App\Models\Lms\MateriFile::where('id', $fileId)->value('file_path');
        $this->assertStringNotContainsString('http', (string) $storedPath);

        // 2. Mahasiswa download file via endpoint download aman
        Passport::actingAs($this->userMhs);

        $resDownload = $this->getJson("/api/v1/siakad/lms/download/materi/{$fileId}");
        $resDownload->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['nama_file', 'download_url', 'disk'],
            ]);

        $this->assertSame('slide_e2e.pdf', $resDownload->json('data.nama_file'));
        $this->assertNotEmpty($resDownload->json('data.download_url'));
    }

    public function test_e2e_token_generate_input_dan_kedaluwarsa(): void
    {
        // 1. Dosen generate token
        Passport::actingAs($this->userDosen);
        $resToken = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/token");
        $resToken->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['token', 'expired_at']]);

        $token = $resToken->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertEquals(6, strlen($token));

        // 2. Mahasiswa input token valid -> absensi tersimpan 'hadir'
        Passport::actingAs($this->userMhs);
        $resInput = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/input-token", [
            'token' => $token,
        ]);
        $resInput->assertStatus(200);

        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'hadir',
        ]);

        // 3. Token salah ditolak
        $resInvalid = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/input-token", [
            'token' => '000000',
        ]);
        $resInvalid->assertStatus(422);

        // 4. Token kedaluwarsa ditolak
        $this->pertemuan->update(['token_expired_at' => now()->subMinute()]);
        $resExpired = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/input-token", [
            'token' => $token,
        ]);
        $resExpired->assertStatus(422);
    }

    public function test_e2e_tugas_obe_sync_dan_overwrite(): void
    {
        Passport::actingAs($this->userDosen);

        $komponenObe = KomponenPenilaian::create([
            'kelas_id'         => $this->kelas->id,
            'nama_komponen'    => 'Tugas E2E - OBE',
            'teknik_penilaian' => 'tugas',
            'bobot'            => 15.00,
            'urutan'           => 1,
            'is_aktif'         => true,
        ]);

        // 1. Dosen buat tugas link OBE
        $resTugas = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/tugas", [
            'judul'                 => 'Tugas E2E: Studi Kasus',
            'deskripsi'             => 'Kerjakan studi kasus berikut.',
            'deadline_at'           => now()->addDays(7)->toDateTimeString(),
            'komponen_penilaian_id' => $komponenObe->id,
            'max_file_size_mb'      => 10,
            'is_published'          => true,
        ]);
        $resTugas->assertStatus(201);
        $tugasId = $resTugas->json('data.id');

        // 2. Mahasiswa submit
        Passport::actingAs($this->userMhs);
        $resKumpul = $this->postJson("/api/v1/siakad/lms/tugas/{$tugasId}/kumpul", [
            'catatan_mahasiswa' => 'Pengumpulan E2E.',
            'file'              => UploadedFile::fake()->create('tugas_e2e.zip', 512, 'application/zip'),
        ]);
        $resKumpul->assertStatus(200);
        $pengumpulanId = $resKumpul->json('data.id');

        // 3. Dosen nilai -> masuk OBE
        Passport::actingAs($this->userDosen);
        $this->putJson("/api/v1/siakad/lms/pengumpulan/{$pengumpulanId}/nilai", [
            'nilai'          => 80.00,
            'feedback_dosen' => 'Cukup baik.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'krs_detail_id'         => $this->krsDetail->id,
            'komponen_penilaian_id' => $komponenObe->id,
            'nilai_angka'           => 80.00,
        ]);

        // 4. Dosen revisi nilai -> OBE ter-overwrite (sumber kebenaran = LMS)
        $this->putJson("/api/v1/siakad/lms/pengumpulan/{$pengumpulanId}/nilai", [
            'nilai'          => 95.00,
            'feedback_dosen' => 'Revisi: sangat baik.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'krs_detail_id'         => $this->krsDetail->id,
            'komponen_penilaian_id' => $komponenObe->id,
            'nilai_angka'           => 95.00,
        ]);

        // 5. Tugas standalone (tanpa link OBE) -> nilai TIDAK masuk OBE
        $resStandalone = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/tugas", [
            'judul'            => 'Tugas E2E Standalone',
            'deadline_at'      => now()->addDays(7)->toDateTimeString(),
            'max_file_size_mb' => 10,
            'is_published'     => true,
        ]);
        $resStandalone->assertStatus(201);
        $standaloneId = $resStandalone->json('data.id');

        Passport::actingAs($this->userMhs);
        $resKumpul2 = $this->postJson("/api/v1/siakad/lms/tugas/{$standaloneId}/kumpul", [
            'catatan_mahasiswa' => 'Standalone.',
            'file'              => UploadedFile::fake()->create('standalone.zip', 256, 'application/zip'),
        ]);
        $resKumpul2->assertStatus(200);

        Passport::actingAs($this->userDosen);
        $this->putJson("/api/v1/siakad/lms/pengumpulan/{$resKumpul2->json('data.id')}/nilai", [
            'nilai' => 70.00,
        ])->assertStatus(200);

        $this->assertDatabaseMissing('siakad_nilai_komponen_mhs', [
            'krs_detail_id' => $this->krsDetail->id,
            'nilai_angka'   => 70.00,
        ]);
    }

    public function test_e2e_izin_disetujui_update_absensi_ditolak_tidak(): void
    {
        $refSakit = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')->where('tipe', 'tipe_izin')->where('kode', 'sakit')->first();
        $refSetuju = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')->where('tipe', 'status_persetujuan_izin')->where('kode', 'disetujui')->first();
        $refTolak = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')->where('tipe', 'status_persetujuan_izin')->where('kode', 'ditolak')->first();

        // 1. Mahasiswa ajukan izin -> dosen approve -> absensi otomatis 'sakit'
        Passport::actingAs($this->userMhs);
        $resIzin = $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/izin", [
            'tipe_izin_id' => $refSakit->id,
            'alasan'       => 'Sakit demam, istirahat di rumah.',
            'file_surat'   => UploadedFile::fake()->create('surat_e2e.pdf', 300, 'application/pdf'),
        ]);
        $resIzin->assertStatus(201);
        $izinId = $resIzin->json('data.id');
        $this->assertDatabaseHas('lms_izin_absensi', ['id' => $izinId, 'status' => 'pending']);

        Passport::actingAs($this->userDosen);
        $this->patchJson("/api/v1/siakad/lms/izin/{$izinId}/proses", [
            'status_id'     => $refSetuju->id,
            'catatan_dosen' => 'Semoga lekas sembuh.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('lms_izin_absensi', ['id' => $izinId, 'status' => 'disetujui']);
        $this->assertDatabaseHas('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'sakit',
        ]);

        // 2. Pertemuan kedua: ajukan -> DITOLAK -> absensi TIDAK berubah menjadi sakit/izin
        $pertemuan2 = Pertemuan::create([
            'kelas_id'         => $this->kelas->id,
            'pertemuan_ke'     => 2,
            'tanggal'          => '2026-10-08',
            'materi'           => 'Materi E2E Pertemuan 2',
            'status_pertemuan' => 'berlangsung',
        ]);

        Passport::actingAs($this->userMhs);
        $resIzin2 = $this->postJson("/api/v1/siakad/lms/pertemuan/{$pertemuan2->id}/izin", [
            'tipe_izin_id' => $refSakit->id,
            'alasan'       => 'Pengajuan kedua untuk skenario tolak.',
        ]);
        $resIzin2->assertStatus(201);
        $izinId2 = $resIzin2->json('data.id');

        Passport::actingAs($this->userDosen);
        $this->patchJson("/api/v1/siakad/lms/izin/{$izinId2}/proses", [
            'status_id'     => $refTolak->id,
            'catatan_dosen' => 'Surat tidak valid.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('lms_izin_absensi', ['id' => $izinId2, 'status' => 'ditolak']);
        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $pertemuan2->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'sakit',
        ]);
        $this->assertDatabaseMissing('siakad_absensi_mahasiswa', [
            'pertemuan_id' => $pertemuan2->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'status'       => 'izin',
        ]);
    }

    public function test_mahasiswa_hanya_melihat_data_kehadiran_sendiri(): void
    {
        // Mahasiswa kedua di kelas yang sama
        $userMhs2 = User::factory()->create(['username' => 'mhs.e2e.2', 'email' => 'mhs2.e2e@test.ac.id']);
        $mhs2 = Mahasiswa::create([
            'user_id'          => $userMhs2->id,
            'program_studi_id' => $this->prodi->id,
            'nim'              => '2026009002',
            'nama_lengkap'     => 'Mahasiswa E2E Kedua',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
        ]);
        $roleMhs = \App\Models\Role::where('slug', 'mahasiswa')->firstOrFail();
        $userMhs2->roles()->attach($roleMhs->id);

        $krs2 = Krs::create([
            'mahasiswa_id'      => $mhs2->id,
            'tahun_akademik_id' => $this->kelas->tahun_akademik_id,
            'status'            => 'disetujui',
            'total_sks'         => 3,
        ]);
        KrsDetail::create(['krs_id' => $krs2->id, 'kelas_id' => $this->kelas->id, 'status' => 'aktif']);

        // Dosen catat hadir untuk keduanya
        Passport::actingAs($this->userDosen);
        $refHadir = \Illuminate\Support\Facades\DB::table('spmb_master_referensi')
            ->where('modul', 'siakad')->where('tipe', 'status_absensi')->where('kode', 'hadir')->first();
        $this->postJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}/bulk-absensi", [
            'absensi' => [
                ['mahasiswa_id' => $this->mahasiswa->id, 'status_id' => $refHadir->id],
                ['mahasiswa_id' => $mhs2->id, 'status_id' => $refHadir->id],
            ],
        ])->assertStatus(200);

        // Mahasiswa 1: rekap hanya 1 baris miliknya
        Passport::actingAs($this->userMhs);
        $resRekap = $this->getJson("/api/v1/siakad/lms/kelas/{$this->kelas->id}/rekap-absensi");
        $resRekap->assertStatus(200);
        $rows = $resRekap->json('data.rekapitulasi');
        $this->assertCount(1, $rows);
        $this->assertEquals($this->mahasiswa->id, $rows[0]['mahasiswa_id']);

        // Mahasiswa 1: detail pertemuan hanya memuat absensi miliknya
        $resPertemuan = $this->getJson("/api/v1/siakad/lms/pertemuan/{$this->pertemuan->id}");
        $resPertemuan->assertStatus(200);
        $absensiList = $resPertemuan->json('data.absensi_list');
        $this->assertCount(1, $absensiList);
        $this->assertEquals($this->mahasiswa->id, $absensiList[0]['mahasiswa_id']);
        $this->assertNotNull($resPertemuan->json('data.my_absensi'));

        // Dosen: tetap melihat semua baris
        Passport::actingAs($this->userDosen);
        $resRekapDosen = $this->getJson("/api/v1/siakad/lms/kelas/{$this->kelas->id}/rekap-absensi");
        $resRekapDosen->assertStatus(200);
        $this->assertCount(2, $resRekapDosen->json('data.rekapitulasi'));
    }

    public function test_my_kelas_hanya_menampilkan_kelas_yang_berhak(): void
    {
        // Kelas kedua yang TIDAK diampu dosen penguji
        $kelasLain = Kelas::create([
            'program_studi_id'  => $this->prodi->id,
            'mata_kuliah_id'    => $this->kelas->mata_kuliah_id,
            'tahun_akademik_id' => $this->kelas->tahun_akademik_id,
            'kode_kelas'        => 'IF-LAIN-B',
            'nama_kelas'        => 'Kelas Lain B',
            'kapasitas'         => 30,
        ]);

        // Dosen pengampu: hanya kelasnya sendiri
        Passport::actingAs($this->userDosen);
        $resDosen = $this->getJson('/api/v1/siakad/lms/kelas/my');
        $resDosen->assertStatus(200);
        $idsDosen = collect($resDosen->json('data'))->pluck('id')->all();
        $this->assertContains($this->kelas->id, $idsDosen);
        $this->assertNotContains($kelasLain->id, $idsDosen);

        // Akun ber-role dosen TANPA relasi siakad_dosen: tidak boleh melihat semua
        $userTaktertaut = User::factory()->create(['username' => 'dosen.taktertaut']);
        $roleDosen = \App\Models\Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        $permRead = \App\Models\Permission::where('slug', 'siakad.kelas.read')->firstOrFail();
        $roleDosen->permissions()->syncWithoutDetaching([$permRead->id]);
        $userTaktertaut->roles()->attach($roleDosen->id);

        Passport::actingAs($userTaktertaut);
        $resTaktertaut = $this->getJson('/api/v1/siakad/lms/kelas/my');
        $resTaktertaut->assertStatus(200);
        $this->assertCount(0, $resTaktertaut->json('data'));

        // Admin: tetap melihat semua kelas
        $userAdmin = User::factory()->create(['username' => 'admin.lms.my']);
        $roleAdmin = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id]);
        $userAdmin->roles()->attach($roleAdmin->id);

        Passport::actingAs($userAdmin);
        $resAdmin = $this->getJson('/api/v1/siakad/lms/kelas/my');
        $resAdmin->assertStatus(200);
        $idsAdmin = collect($resAdmin->json('data'))->pluck('id')->all();
        $this->assertContains($this->kelas->id, $idsAdmin);
        $this->assertContains($kelasLain->id, $idsAdmin);
    }

    public function test_my_kelas_default_periode_aktif_dan_bisa_filter_tahun(): void
    {
        TahunAkademik::where('id', $this->kelas->tahun_akademik_id)->update(['is_active' => true]);
        $taLalu = TahunAkademik::create([
            'kode'          => '20252',
            'nama'          => '2025/2026 Genap',
            'semester'      => 'genap',
            'tahun_mulai'   => 2025,
            'tahun_selesai' => 2026,
            'is_active'     => false,
        ]);

        $kelasLalu = Kelas::create([
            'program_studi_id'  => $this->prodi->id,
            'mata_kuliah_id'    => $this->kelas->mata_kuliah_id,
            'tahun_akademik_id' => $taLalu->id,
            'kode_kelas'        => 'IF-LALU-A',
            'nama_kelas'        => 'Kelas Semester Lalu',
            'kapasitas'         => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $kelasLalu->id,
            'dosen_id' => $this->dosen->id,
            'peran'    => 'pengampu_utama',
        ]);

        Passport::actingAs($this->userDosen);

        // Default: hanya periode aktif
        $resDefault = $this->getJson('/api/v1/siakad/lms/kelas/my');
        $resDefault->assertStatus(200);
        $idsDefault = collect($resDefault->json('data'))->pluck('id')->all();
        $this->assertContains($this->kelas->id, $idsDefault);
        $this->assertNotContains($kelasLalu->id, $idsDefault);

        // Eksplisit tahun lalu: hanya kelas tahun lalu
        $resLalu = $this->getJson("/api/v1/siakad/lms/kelas/my?tahun_akademik_id={$taLalu->id}");
        $resLalu->assertStatus(200);
        $idsLalu = collect($resLalu->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->kelas->id, $idsLalu);
        $this->assertContains($kelasLalu->id, $idsLalu);
    }
}
