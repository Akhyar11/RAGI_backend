<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
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
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cetak PDF LMS: route terpisah dari export CSV/XLSX, inline response,
 * pembatasan kelas/prodi, dan jejak audit `action=export`.
 */
class SiakadLmsPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $userAdmin;
    protected User $userDosen;
    protected User $userDosenLain;
    protected User $userMhs;
    protected ProgramStudi $prodiA;
    protected ProgramStudi $prodiB;
    protected TahunAkademik $tahunAkademik;
    protected Kelas $kelasA;
    protected Kelas $kelasB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userAdmin = User::factory()->create(['username' => 'admin.pdf', 'email' => 'admin.pdf@test.ac.id']);
        $this->userDosen = User::factory()->create(['username' => 'dosen.pdf', 'email' => 'dosen.pdf@test.ac.id']);
        $this->userDosenLain = User::factory()->create(['username' => 'dosen.pdf.lain', 'email' => 'dosen.pdf.lain@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.pdf', 'email' => 'mhs.pdf@test.ac.id']);

        $this->prodiA = ProgramStudi::create([
            'kode_prodi' => 'IF-PDF',
            'nama' => 'Informatika PDF',
            'jenjang' => 'S1',
        ]);
        $this->prodiB = ProgramStudi::create([
            'kode_prodi' => 'SI-PDF',
            'nama' => 'Sistem Informasi PDF',
            'jenjang' => 'S1',
        ]);

        $this->tahunAkademik = TahunAkademik::create([
            'kode' => '20263',
            'nama' => '2026/2027 Ganjil',
            'semester' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_aktif' => true,
        ]);

        $kurikulumA = Kurikulum::create([
            'program_studi_id' => $this->prodiA->id,
            'kode' => 'KUR-IF-PDF',
            'nama' => 'Kurikulum Informatika PDF',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);
        $kurikulumB = Kurikulum::create([
            'program_studi_id' => $this->prodiB->id,
            'kode' => 'KUR-SI-PDF',
            'nama' => 'Kurikulum Sistem Informasi PDF',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);

        $mkA = MataKuliah::create([
            'kurikulum_id' => $kurikulumA->id,
            'kode_mk' => 'IF601',
            'nama' => 'Pemrograman Web PDF',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 6,
            'is_active' => true,
        ]);
        $mkB = MataKuliah::create([
            'kurikulum_id' => $kurikulumB->id,
            'kode_mk' => 'SI601',
            'nama' => 'Manajemen Basis Data PDF',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 6,
            'is_active' => true,
        ]);

        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id,
            'program_studi_id' => $this->prodiA->id,
            'nidn' => '0011445501',
            'nama_lengkap' => 'Dosen Pengampu PDF',
            'status_aktif' => 'aktif',
        ]);
        $dosenLain = Dosen::create([
            'user_id' => $this->userDosenLain->id,
            'program_studi_id' => $this->prodiB->id,
            'nidn' => '0011445502',
            'nama_lengkap' => 'Dosen Pengampu Lain PDF',
            'status_aktif' => 'aktif',
        ]);

        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id,
            'program_studi_id' => $this->prodiA->id,
            'nim' => '2026009001',
            'nama_lengkap' => 'Mahasiswa PDF',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);

        $this->kelasA = Kelas::create([
            'program_studi_id' => $this->prodiA->id,
            'mata_kuliah_id' => $mkA->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'kode_kelas' => 'IF-PDF-A',
            'nama_kelas' => 'Kelas PDF A',
            'kapasitas' => 30,
        ]);
        $this->kelasB = Kelas::create([
            'program_studi_id' => $this->prodiB->id,
            'mata_kuliah_id' => $mkB->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'kode_kelas' => 'SI-PDF-A',
            'nama_kelas' => 'Kelas PDF B',
            'kapasitas' => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelasA->id,
            'dosen_id' => $dosen->id,
            'peran' => 'pengampu_utama',
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelasB->id,
            'dosen_id' => $dosenLain->id,
            'peran' => 'pengampu_utama',
        ]);

        foreach ([[$mahasiswa, $this->kelasA], [$mahasiswa, $this->kelasB]] as [$mhs, $kelas]) {
            $pertemuan = Pertemuan::create([
                'kelas_id' => $kelas->id,
                'pertemuan_ke' => 1,
                'tanggal' => '2026-11-10',
                'materi' => 'Pertemuan PDF',
                'status_pertemuan' => Pertemuan::STATUS_BELUM,
            ]);
            AbsensiMahasiswa::create([
                'pertemuan_id' => $pertemuan->id,
                'mahasiswa_id' => $mhs->id,
                'status' => AbsensiMahasiswa::STATUS_HADIR,
                'is_submitted' => true,
            ]);
        }

        $krs = Krs::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'status' => 'disetujui',
            'total_sks' => 6,
        ]);
        foreach ([$this->kelasA, $this->kelasB] as $kelas) {
            KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_id' => $kelas->id,
                'status' => 'aktif',
            ]);
        }

        $this->setupRbac();
    }

    protected function setupRbac(): void
    {
        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);

        $permRead = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.read'],
            ['name' => 'Lihat Kelas', 'module' => 'siakad', 'action' => 'read']
        );
        $permManage = Permission::firstOrCreate(
            ['slug' => 'siakad.kelas.manage'],
            ['name' => 'Kelola Kelas', 'module' => 'siakad', 'action' => 'update']
        );

        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleDosen->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleMhs->permissions()->syncWithoutDetaching([$permRead->id]);

        $this->userAdmin->roles()->attach($roleAdmin->id);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userDosenLain->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
    }

    protected function assertPdfResponse($response, string $filenamePart): void
    {
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline;', (string) $disposition);
        $this->assertStringContainsString('.pdf', (string) $disposition);
        $this->assertStringContainsString($filenamePart, (string) $disposition);
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    protected function latestExportAudit(): ?AuditLog
    {
        return AuditLog::where('module', 'LMS')
            ->where('action', 'export')
            ->latest('id')
            ->first();
    }

    public function test_dosen_pengampu_dapat_cetak_pdf_kehadiran_kelasnya(): void
    {
        Passport::actingAs($this->userDosen);

        $response = $this->get("/api/v1/lms/kelas/{$this->kelasA->id}/cetak/kehadiran");

        $this->assertPdfResponse($response, 'Kehadiran_Kelas_IF-PDF-A');
        $response->assertHeader('Cache-Control', 'no-store, private');

        $audit = $this->latestExportAudit();
        $this->assertNotNull($audit);
        $this->assertSame('cetak_kehadiran_kelas', $audit->new_values['jenis_laporan']);
        $this->assertSame('pdf', $audit->new_values['format']);
        $this->assertSame(1, $audit->new_values['jumlah_baris']);
        $this->assertSame('siakad_kelas', $audit->table_name);
        $this->assertSame($this->kelasA->id, $audit->record_id);
    }

    public function test_dosen_luar_kelas_ditolak_dan_tidak_mencatat_audit_export(): void
    {
        Passport::actingAs($this->userDosenLain);

        $this->get("/api/v1/lms/kelas/{$this->kelasA->id}/cetak/kehadiran")
            ->assertStatus(422);

        $this->assertNull($this->latestExportAudit());
    }

    public function test_mahasiswa_tidak_memiliki_akses_cetak(): void
    {
        Passport::actingAs($this->userMhs);

        $this->get("/api/v1/lms/kelas/{$this->kelasA->id}/cetak/kehadiran")
            ->assertStatus(403);
        $this->get('/api/v1/lms/cetak/rekap-prodi?tahun_akademik_id=' . $this->tahunAkademik->id)
            ->assertStatus(403);
    }

    public function test_admin_dapat_cetak_rekap_seluruh_prodi(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->get('/api/v1/lms/cetak/rekap-prodi?tahun_akademik_id=' . $this->tahunAkademik->id);

        $this->assertPdfResponse($response, 'Rekap_Kehadiran_Prodi_Semua');

        $audit = $this->latestExportAudit();
        $this->assertNotNull($audit);
        $this->assertSame('rekap_kehadiran_prodi', $audit->new_values['jenis_laporan']);
        $this->assertSame('pdf', $audit->new_values['format']);
        $this->assertSame(2, $audit->new_values['jumlah_baris']);
        $this->assertSame('siakad_tahun_akademik', $audit->table_name);
        $this->assertSame($this->tahunAkademik->id, $audit->record_id);
    }

    public function test_rekap_prodi_dosen_hanya_memuat_kelas_yang_diampu(): void
    {
        Passport::actingAs($this->userDosen);

        $response = $this->get('/api/v1/lms/cetak/rekap-prodi?tahun_akademik_id=' . $this->tahunAkademik->id);

        $this->assertPdfResponse($response, 'Rekap_Kehadiran_Prodi_Semua');
        $audit = $this->latestExportAudit();
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->new_values['jumlah_baris']);
    }

    public function test_rekap_prodi_dapat_difilter_menurut_id_prodi(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->get('/api/v1/lms/cetak/rekap-prodi?' . http_build_query([
            'program_studi_id' => $this->prodiB->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
        ]));

        $this->assertPdfResponse($response, 'Rekap_Kehadiran_Prodi_SI-PDF');
        $audit = $this->latestExportAudit();
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->new_values['jumlah_baris']);
        $this->assertSame('siakad_program_studi', $audit->table_name);
        $this->assertSame($this->prodiB->id, $audit->record_id);
    }

    public function test_validasi_parameter_cetak_rekap_prodi(): void
    {
        Passport::actingAs($this->userAdmin);

        $this->get('/api/v1/lms/cetak/rekap-prodi')
            ->assertStatus(422)
            ->assertJsonStructure(['status', 'message', 'errors' => ['tahun_akademik_id']]);

        $this->get('/api/v1/lms/cetak/rekap-prodi?' . http_build_query([
            'program_studi_id' => 999999,
            'tahun_akademik_id' => $this->tahunAkademik->id,
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['status', 'message', 'errors' => ['program_studi_id']]);

        $this->assertNull($this->latestExportAudit());
    }
}
