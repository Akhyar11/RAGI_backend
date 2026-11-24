<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Jurnal presensi lokal: satu sumber data pada `siakad_pertemuan`,
 * bukti privat via FileStorageService, dan RBAC dosen pengampu.
 */
class SiakadLmsJurnalTest extends TestCase
{
    use RefreshDatabase;

    protected User $userAdmin;
    protected User $userDosen;
    protected User $userMhs;
    protected User $userDosenLain;
    protected Kelas $kelas;
    protected Kelas $kelasLain;
    protected Pertemuan $pertemuan;
    protected Ruangan $ruangan;
    protected string $privateDisk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('public');
        $this->privateDisk = (string) config('filesystems.private_disk', 'public');

        $this->userAdmin = User::factory()->create(['username' => 'admin.jurnal', 'email' => 'admin.jurnal@test.ac.id']);
        $this->userDosen = User::factory()->create(['username' => 'dosen.jurnal', 'email' => 'dosen.jurnal@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.jurnal', 'email' => 'mhs.jurnal@test.ac.id']);
        $this->userDosenLain = User::factory()->create(['username' => 'dosen.lain', 'email' => 'dosen.lain@test.ac.id']);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-JUR',
            'nama' => 'Informatika Jurnal',
            'jenjang' => 'S1',
        ]);
        $ta = TahunAkademik::create([
            'kode' => '20263',
            'nama' => '2026/2027 Ganjil',
            'semester' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode' => 'KUR-JUR',
            'nama' => 'Kurikulum Jurnal 2026',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);
        $mataKuliah = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF501',
            'nama' => 'Jurnal Presensi',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 7,
            'is_active' => true,
        ]);

        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id,
            'program_studi_id' => $prodi->id,
            'nidn' => '0011223344',
            'nama_lengkap' => 'Dosen Jurnal',
            'status_aktif' => 'aktif',
        ]);
        $dosenLain = Dosen::create([
            'user_id' => $this->userDosenLain->id,
            'program_studi_id' => $prodi->id,
            'nidn' => '0011223355',
            'nama_lengkap' => 'Dosen Bukan Pengampu',
            'status_aktif' => 'aktif',
        ]);

        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id,
            'program_studi_id' => $prodi->id,
            'nim' => '2026005001',
            'nama_lengkap' => 'Mahasiswa Jurnal',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);

        $gedung = Gedung::create([
            'kode' => 'G-JUR',
            'nama' => 'Gedung Jurnal',
            'jumlah_lantai' => 2,
            'status' => 'aktif',
        ]);
        $this->ruangan = Ruangan::create([
            'gedung_id' => $gedung->id,
            'kode' => 'R-JUR-01',
            'nama' => 'Ruang Jurnal A',
            'status' => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas' => 'IF-JUR-A',
            'nama_kelas' => 'Kelas Jurnal A',
            'kapasitas' => 30,
        ]);
        $this->kelasLain = Kelas::create([
            'program_studi_id' => $prodi->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'tahun_akademik_id' => $ta->id,
            'kode_kelas' => 'IF-JUR-B',
            'nama_kelas' => 'Kelas Jurnal B',
            'kapasitas' => 30,
        ]);

        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $dosen->id,
            'peran' => 'pengampu_utama',
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelasLain->id,
            'dosen_id' => $dosenLain->id,
            'peran' => 'pengampu_utama',
        ]);

        $this->pertemuan = Pertemuan::create([
            'kelas_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-11-02',
            'materi' => 'Pengantar Jurnal',
            'status_pertemuan' => Pertemuan::STATUS_BELUM,
            'token_absensi' => 'ABC123',
            'token_expired_at' => now()->addMinutes(15),
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $ta->id,
            'status' => 'disetujui',
            'total_sks' => 3,
        ]);
        KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status' => 'aktif',
        ]);
        AbsensiMahasiswa::create([
            'pertemuan_id' => $this->pertemuan->id,
            'mahasiswa_id' => $mahasiswa->id,
            'status' => AbsensiMahasiswa::STATUS_HADIR,
            'is_submitted' => true,
        ]);

        // ── RBAC ──────────────────────────────────────────────────────
        $roleAdmin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);

        $permKelasRead = Permission::firstOrCreate(['slug' => 'siakad.kelas.read'], ['name' => 'Lihat Kelas', 'module' => 'siakad', 'action' => 'read']);
        $permKelasManage = Permission::firstOrCreate(['slug' => 'siakad.kelas.manage'], ['name' => 'Kelola Kelas', 'module' => 'siakad', 'action' => 'update']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permKelasRead->id, $permKelasManage->id]);
        $roleDosen->permissions()->syncWithoutDetaching([$permKelasRead->id, $permKelasManage->id]);
        $roleMhs->permissions()->syncWithoutDetaching([$permKelasRead->id]);

        $this->userAdmin->roles()->attach($roleAdmin->id);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userDosenLain->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
    }

    protected function jurnalUrl(?int $kelasId = null, ?int $pertemuanId = null): string
    {
        return sprintf(
            '/api/v1/lms/kelas/%d/pertemuan/%d/jurnal',
            $kelasId ?? $this->kelas->id,
            $pertemuanId ?? $this->pertemuan->id
        );
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'pertemuan_ke' => 1,
            'tanggal' => '2026-11-02',
            'materi' => 'Jurnal: Sistem Terdistribusi',
            'catatan_pertemuan' => 'Materi tersampaikan, mahasiswa aktif bertanya.',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:30',
            'status_pertemuan' => Pertemuan::STATUS_SELESAI,
            'jenis_kegiatan' => Pertemuan::JENIS_KEGIATAN_PRAKTIKUM,
            'ruangan_id' => $this->ruangan->id,
        ], $overrides);
    }

    public function test_dosen_pengampu_dapat_membaca_dan_menyimpan_jurnal_dengan_bukti_privat(): void
    {
        Passport::actingAs($this->userDosen);

        $get = $this->getJson($this->jurnalUrl())->assertStatus(200)->assertJsonStructure([
            'status',
            'data' => [
                'jurnal' => ['pertemuan_id', 'kelas_id', 'jenis_kegiatan', 'ruangan_id', 'bukti_pembelajaran_url'],
                'can_edit',
                'jenis_kegiatan_options',
            ],
        ]);
        $this->assertTrue($get->json('data.can_edit'));

        $response = $this->put($this->jurnalUrl(), array_merge($this->validPayload(), [
            'bukti_pembelajaran' => UploadedFile::fake()->create('bukti-jurnal.pdf', 120, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(200)->assertJsonPath('status', 'success');

        $path = $response->json('data.jurnal.bukti_pembelajaran_path');
        $this->assertNotNull($path);
        $this->assertStringStartsWith('lms/bukti_pembelajaran/', $path);
        $this->assertMatchesRegularExpression('/\/[0-9a-f-]{36}\.pdf$/', $path);
        $this->assertStringNotContainsString('http', $path);
        Storage::disk('public')->assertExists($path);

        $url = $response->json('data.jurnal.bukti_pembelajaran_url');
        $this->assertStringContainsString('/api/files/view?', $url);
        $this->assertStringContainsString('path=', $url);
        $this->assertStringContainsString('signature=', $url);

        $this->assertDatabaseHas('siakad_pertemuan', [
            'id' => $this->pertemuan->id,
            'jenis_kegiatan' => Pertemuan::JENIS_KEGIATAN_PRAKTIKUM,
            'ruangan_id' => $this->ruangan->id,
            'bukti_pembelajaran_disk' => $this->privateDisk,
            'status_pertemuan' => Pertemuan::STATUS_SELESAI,
        ]);

        $audit = $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'LMS',
            'action' => 'update',
            'table_name' => 'siakad_pertemuan',
            'record_id' => $this->pertemuan->id,
        ]);
        $record = \App\Models\AuditLog::where('record_id', $this->pertemuan->id)->latest('id')->first();
        $this->assertNotNull($record);
        $this->assertArrayNotHasKey('token_absensi', $record->old_values ?? []);
        $this->assertArrayNotHasKey('token_absensi', $record->new_values ?? []);
    }

    public function test_mahasiswa_melihat_ringkasan_tanpa_bukti_dan_tidak_bisa_menulis(): void
    {
        Passport::actingAs($this->userMhs);

        $response = $this->getJson($this->jurnalUrl())->assertStatus(200);
        $this->assertFalse($response->json('data.can_edit'));
        $this->assertNull($response->json('data.jurnal.bukti_pembelajaran_path'));
        $this->assertNull($response->json('data.jurnal.bukti_pembelajaran_url'));
        $this->assertSame('Pengantar Jurnal', $response->json('data.jurnal.materi'));

        $this->put($this->jurnalUrl(), $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_admin_pengelola_dapat_memantau_namun_tidak_bisa_menulis_jurnal(): void
    {
        Passport::actingAs($this->userAdmin);

        $response = $this->getJson($this->jurnalUrl())->assertStatus(200);
        $this->assertFalse($response->json('data.can_edit'));

        // Lolos gate route (punya siakad.kelas.manage), tapi ditolak service
        // karena bukan Dosen Pengampu kelas.
        $this->put($this->jurnalUrl(), $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_dosen_pengampu_kelas_lain_tidak_bisa_menulis_jurnal(): void
    {
        Passport::actingAs($this->userDosenLain);

        $this->put($this->jurnalUrl(), $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_pertemuan_dari_kelas_lain_ditolak(): void
    {
        Passport::actingAs($this->userDosen);
        $pertemuanLain = Pertemuan::create([
            'kelas_id' => $this->kelasLain->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2026-11-03',
            'materi' => 'Pertemuan kelas lain',
        ]);

        $this->getJson($this->jurnalUrl(null, $pertemuanLain->id))->assertStatus(404);
        $this->put(
            $this->jurnalUrl(null, $pertemuanLain->id),
            $this->validPayload(['pertemuan_ke' => 2]),
            ['Accept' => 'application/json']
        )->assertStatus(404);
    }

    public function test_validasi_menolak_jenis_kegiatan_dan_ruangan_tidak_valid(): void
    {
        Passport::actingAs($this->userDosen);

        $this->put($this->jurnalUrl(), $this->validPayload([
            'jenis_kegiatan' => 'tidak-terdaftar',
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis_kegiatan');

        $this->put($this->jurnalUrl(), $this->validPayload([
            'ruangan_id' => 999999,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ruangan_id');
    }

    public function test_upload_bukti_baru_menghapus_bukti_lama(): void
    {
        Passport::actingAs($this->userDosen);

        $first = $this->put($this->jurnalUrl(), array_merge($this->validPayload(), [
            'bukti_pembelajaran' => UploadedFile::fake()->create('bukti-pertama.pdf', 80, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(200);
        $oldPath = $first->json('data.jurnal.bukti_pembelajaran_path');
        Storage::disk('public')->assertExists($oldPath);

        $second = $this->put($this->jurnalUrl(), array_merge($this->validPayload(), [
            'bukti_pembelajaran' => UploadedFile::fake()->create('bukti-kedua.pdf', 90, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(200);
        $newPath = $second->json('data.jurnal.bukti_pembelajaran_path');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }
}
