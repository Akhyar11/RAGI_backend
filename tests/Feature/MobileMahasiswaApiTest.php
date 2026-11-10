<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\Sikeu\Beasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\MahasiswaBeasiswa;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileMahasiswaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mahasiswaUser;

    private Mahasiswa $mahasiswa;

    private User $dosenUser;

    private User $superAdminUser;

    private TahunAkademik $taAktif;

    private Kelas $kelas;

    private Pertemuan $pertemuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $permManage = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.manage'],
            ['name' => 'siakad.kelas.manage', 'module' => 'siakad', 'action' => 'update']
        );
        $permRead = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.read'],
            ['name' => 'siakad.kelas.read', 'module' => 'siakad', 'action' => 'read']
        );
        $permKrsRead = Permission::firstOrCreate(
            ['slug' => 'siakad.krs.read'],
            ['name' => 'siakad.krs.read', 'module' => 'siakad', 'action' => 'read']
        );
        $permNilaiRead = Permission::firstOrCreate(
            ['slug' => 'siakad.nilai.read'],
            ['name' => 'siakad.nilai.read', 'module' => 'siakad', 'action' => 'read']
        );
        $permTagihanRead = Permission::firstOrCreate(
            ['slug' => 'sikeu.tagihan.read'],
            ['name' => 'sikeu.tagihan.read', 'module' => 'sikeu', 'action' => 'read']
        );
        $permDispensasiRead = Permission::firstOrCreate(
            ['slug' => 'sikeu.dispensasi.read'],
            ['name' => 'sikeu.dispensasi.read', 'module' => 'sikeu', 'action' => 'read']
        );
        $permPresensiCreate = Permission::firstOrCreate(
            ['slug' => 'lms.presensi.create'],
            ['name' => 'lms.presensi.create', 'module' => 'lms', 'action' => 'create']
        );
        $permForumRead = Permission::firstOrCreate(
            ['slug' => 'lms.forum.read'],
            ['name' => 'lms.forum.read', 'module' => 'lms', 'action' => 'read']
        );

        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $roleMhs->permissions()->syncWithoutDetaching([
            $permRead->id,
            $permKrsRead->id,
            $permNilaiRead->id,
            $permTagihanRead->id,
            $permDispensasiRead->id,
            $permPresensiCreate->id,
            $permForumRead->id,
        ]);

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen Pengajar']);
        $roleDosen->permissions()->syncWithoutDetaching([$permManage->id, $permRead->id]);

        $roleSuper = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin']);

        $this->mahasiswaUser = User::factory()->create([
            'username' => '2026009001',
            'email' => 'mhs.mobile@kampus.ac.id',
        ]);
        $this->mahasiswaUser->roles()->sync([$roleMhs->id]);

        $this->dosenUser = User::factory()->create([
            'username' => 'dosen.mobile',
            'email' => 'dosen.mobile@kampus.ac.id',
        ]);
        $this->dosenUser->roles()->sync([$roleDosen->id]);

        $this->superAdminUser = User::factory()->create([
            'username' => 'superadmin.mobile',
            'email' => 'super.mobile@kampus.ac.id',
        ]);
        $this->superAdminUser->roles()->sync([$roleSuper->id]);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-MOB',
            'nama' => 'Informatika Mobile',
            'jenjang' => 'S1',
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode' => 'KUR-MOB-2026',
            'nama' => 'Kurikulum Mobile 2026',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);

        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF-MOB-101',
            'nama' => 'Pemrograman Mobile Terpadu',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'is_active' => true,
        ]);

        $this->taAktif = TahunAkademik::create([
            'kode' => '20261',
            'nama' => 'Ganjil 2026/2027',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'program_studi_id' => $prodi->id,
            'nidn' => '0411223344',
            'nama_lengkap' => 'Dr. Dosen Mobile, M.Kom.',
            'status_aktif' => 'aktif',
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $this->mahasiswaUser->id,
            'program_studi_id' => $prodi->id,
            'dosen_wali_id' => $dosen->id,
            'nim' => '2026009001',
            'nama_lengkap' => 'Mahasiswa Mobile Satu',
            'email' => 'mhs.mobile@kampus.ac.id',
            'angkatan' => 2026,
            'kelas' => 'Reguler',
            'status' => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'program_studi_id' => $prodi->id,
            'kode_kelas' => 'IF-MOB-A',
            'nama_kelas' => 'Kelas Mobile A',
            'kapasitas' => 40,
            'kuota_krs' => 40,
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $dosen->id,
            'peran' => 'pengampu_utama',
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'status' => 'disetujui',
            'total_sks_diambil' => 3,
        ]);

        $krsDetail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status' => 'aktif',
        ]);

        NilaiMahasiswa::create([
            'krs_detail_id' => $krsDetail->id,
            'nilai_akhir' => 90,
            'nilai_huruf' => 'A',
            'bobot_mutu' => 4.00,
            'is_final' => true,
        ]);

        Khs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'ips' => 4.00,
            'ipk' => 4.00,
            'total_sks_semester' => 3,
            'sks_kumulatif' => 3,
            'is_locked' => true,
        ]);

        $this->pertemuan = Pertemuan::create([
            'kelas_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-10-01',
            'materi' => 'Pengenalan Flutter & REST API',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
            'status_pertemuan' => 'belum',
        ]);

        $now = now();
        DB::table('spmb_master_referensi')->updateOrInsert(
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'hadir'],
            ['modul' => 'siakad', 'tipe' => 'status_absensi', 'kode' => 'hadir', 'nama' => 'Hadir', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );

        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan' => 'INV-MOB-2026-001',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->taAktif->id,
            'total_tagihan' => 5000000,
            'total_potongan' => 1000000,
            'total_denda' => 0,
            'total_bayar' => 1500000,
            'status' => 'sebagian',
            'jatuh_tempo' => '2026-12-01',
        ]);

        $beasiswa = Beasiswa::create([
            'kode' => 'BEA-PRESTASI',
            'nama' => 'Beasiswa Prestasi Akademik',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 1000000,
            'is_active' => true,
        ]);

        MahasiswaBeasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'beasiswa_id' => $beasiswa->id,
            'nim' => $this->mahasiswa->nim,
            'nama_mahasiswa' => $this->mahasiswa->nama_lengkap,
            'status' => 'aktif',
        ]);

        DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'tipe_dispensasi' => 'cicilan',
            'jatuh_tempo_baru' => '2026-12-31',
            'jumlah_cicilan' => 2,
            'nominal_per_cicilan' => 1250000,
            'alasan' => 'Cicilan UKT Semester Ganjil',
            'allow_krs' => true,
            'status' => 'approved',
        ]);
    }

    public function test_mobile_mahasiswa_get_endpoints_home_lms_siakad_presensi_dan_sikeu(): void
    {
        // Dosen membuka sesi presensi 30 detik terlebih dahulu
        Passport::actingAs($this->dosenUser);
        $bukaSesiRes = $this->postJson("/api/v1/lms/pertemuan/{$this->pertemuan->id}/buka-sesi", [
            'materi' => 'Integrasi Mobile API & QR Scanner 30 Detik',
            'catatan_pertemuan' => 'Simulasi scan QR dari aplikasi mobile mahasiswa.',
        ]);
        $bukaSesiRes->assertStatus(200);
        $qrPayload = $bukaSesiRes->json('data.qr_payload');

        // Login sebagai Mahasiswa di Aplikasi Mobile
        Passport::actingAs($this->mahasiswaUser);

        // 1. GET /api/v1/mobile/mahasiswa/home
        $homeRes = $this->getJson('/api/v1/mobile/mahasiswa/home');
        $homeRes->assertStatus(200)
            ->assertJsonPath('data.mahasiswa.nim', '2026009001')
            ->assertJsonPath('data.periode_aktif.kode', '20261')
            ->assertJsonPath('data.sesi_presensi_aktif.0.pertemuan_id', $this->pertemuan->id)
            ->assertJsonPath('data.sikeu_ringkasan.total_tagihan', 5000000)
            ->assertJsonPath('data.sikeu_ringkasan.total_potongan_beasiswa', 1000000)
            ->assertJsonPath('data.sikeu_ringkasan.total_sisa_bayar', 2500000);

        // 2. GET /api/v1/mobile/mahasiswa/periode
        $this->getJson('/api/v1/mobile/mahasiswa/periode')
            ->assertStatus(200)
            ->assertJsonPath('data.periode_aktif.id', $this->taAktif->id);

        // 3. POST /api/v1/mobile/mahasiswa/presensi/scan & GET /api/v1/mobile/mahasiswa/presensi/rekap
        $this->postJson('/api/v1/mobile/mahasiswa/presensi/scan', [
            'qr_payload' => $qrPayload,
            'device_info' => 'RAG-Mobile/1.0 (Android 15)',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'hadir');

        $this->getJson('/api/v1/mobile/mahasiswa/presensi/rekap')
            ->assertStatus(200)
            ->assertJsonPath('data.rekap_kelas.0.hadir', 1);

        // 4. GET /api/v1/mobile/mahasiswa/lms/*
        $this->getJson('/api/v1/mobile/mahasiswa/lms/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'mahasiswa');

        $this->getJson('/api/v1/mobile/mahasiswa/lms/kelas')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/mobile/mahasiswa/lms/kelas/{$this->kelas->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.viewer_context.viewer_mode', 'mahasiswa');

        $this->getJson("/api/v1/mobile/mahasiswa/lms/pertemuan/{$this->pertemuan->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.my_absensi.status', 'hadir');

        $this->getJson('/api/v1/mobile/mahasiswa/lms/tugas')->assertStatus(200);
        $this->getJson('/api/v1/mobile/mahasiswa/lms/tryout')->assertStatus(200);
        $this->getJson('/api/v1/mobile/mahasiswa/lms/forum')->assertStatus(200);

        // 5. GET /api/v1/mobile/mahasiswa/siakad/*
        $this->getJson('/api/v1/mobile/mahasiswa/siakad/profil')
            ->assertStatus(200)
            ->assertJsonPath('data.nim', '2026009001');

        $this->getJson('/api/v1/mobile/mahasiswa/siakad/jadwal')
            ->assertStatus(200)
            ->assertJsonPath('data.total_kelas', 1);

        $this->getJson('/api/v1/mobile/mahasiswa/siakad/krs')
            ->assertStatus(200)
            ->assertJsonPath('data.total_sks', 3);

        $this->getJson('/api/v1/mobile/mahasiswa/siakad/khs')
            ->assertStatus(200)
            ->assertJsonPath('data.ipk_terakhir', 4);

        $this->getJson('/api/v1/mobile/mahasiswa/siakad/transkrip')
            ->assertStatus(200)
            ->assertJsonPath('data.ringkasan.total_sks_lulus', 3);

        // 6. GET /api/v1/mobile/mahasiswa/sikeu/*
        $this->getJson('/api/v1/mobile/mahasiswa/sikeu/ringkasan')
            ->assertStatus(200)
            ->assertJsonPath('data.total_sisa_bayar', 2500000);

        $this->getJson('/api/v1/mobile/mahasiswa/sikeu/tagihan')
            ->assertStatus(200)
            ->assertJsonPath('data.tagihan.0.nomor_tagihan', 'INV-MOB-2026-001');

        $this->getJson('/api/v1/mobile/mahasiswa/sikeu/pembayaran')
            ->assertStatus(200);

        $this->getJson('/api/v1/mobile/mahasiswa/sikeu/beasiswa')
            ->assertStatus(200)
            ->assertJsonPath('data.beasiswa.0.program_beasiswa.kode', 'BEA-PRESTASI');

        $this->getJson('/api/v1/mobile/mahasiswa/sikeu/dispensasi')
            ->assertStatus(200)
            ->assertJsonPath('data.memiliki_tunggakan_dispensasi_sebelumnya', true);
    }

    public function test_superadmin_tanpa_profil_mahasiswa_ditolak_mengakses_endpoint_mobile_mahasiswa(): void
    {
        Passport::actingAs($this->superAdminUser);

        $this->getJson('/api/v1/mobile/mahasiswa/home')
            ->assertStatus(403);
    }
}
