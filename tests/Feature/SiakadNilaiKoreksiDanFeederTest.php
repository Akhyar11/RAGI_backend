<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Cpmk;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\KoreksiNilaiMahasiswa;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadNilaiKoreksiDanFeederTest extends TestCase
{
    use RefreshDatabase;

    private User $dosenUser;
    private User $reviewerUser;
    private User $mahasiswaUser;
    private ProgramStudi $programStudi;
    private TahunAkademik $tahunAkademik;
    private Kelas $kelas;
    private KrsDetail $krsDetail;
    private NilaiMahasiswa $nilai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $createPermission = Permission::firstOrCreate(
            ['slug' => 'siakad.nilai.koreksi.create'],
            ['name' => 'Ajukan Koreksi Nilai', 'module' => 'siakad', 'action' => 'create']
        );
        $approvePermission = Permission::firstOrCreate(
            ['slug' => 'siakad.nilai.koreksi.approve'],
            ['name' => 'Tinjau Koreksi Nilai', 'module' => 'siakad', 'action' => 'approve']
        );
        $dosenRole = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        $kaprodiRole = Role::firstOrCreate(['slug' => 'kaprodi'], ['name' => 'Kaprodi']);
        $dosenRole->permissions()->syncWithoutDetaching([$createPermission->id]);
        $kaprodiRole->permissions()->syncWithoutDetaching([$approvePermission->id]);

        $this->dosenUser = User::factory()->create(['username' => 'nilai.dosen', 'email' => 'nilai.dosen@test.ac.id']);
        $this->dosenUser->roles()->sync([$dosenRole->id]);
        $this->reviewerUser = User::factory()->create(['username' => 'nilai.kaprodi', 'email' => 'nilai.kaprodi@test.ac.id']);
        $this->reviewerUser->roles()->sync([$kaprodiRole->id]);
        $this->mahasiswaUser = User::factory()->create(['username' => 'nilai.mhs', 'email' => 'nilai.mhs@test.ac.id']);

        $this->programStudi = ProgramStudi::create([
            'kode_prodi' => 'IF-KOREKSI',
            'nama' => 'Informatika Koreksi',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $dosen = Dosen::create([
            'user_id' => $this->dosenUser->id,
            'program_studi_id' => $this->programStudi->id,
            'nidn' => '1199000101',
            'nama_lengkap' => 'Dosen Koreksi',
            'status_aktif' => 'aktif',
        ]);
        $kaprodi = Dosen::create([
            'user_id' => $this->reviewerUser->id,
            'program_studi_id' => $this->programStudi->id,
            'nidn' => '1199000102',
            'nama_lengkap' => 'Kaprodi Koreksi',
            'status_aktif' => 'aktif',
        ]);
        $this->programStudi->update(['kaprodi_id' => $kaprodi->id]);

        $this->tahunAkademik = TahunAkademik::create([
            'kode' => '20261',
            'nama' => '2026/2027 Ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'semester' => 'ganjil',
            'is_active' => true,
            'mode_penilaian' => 'konvensional',
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $this->programStudi->id,
            'kode' => 'KUR-KOREKSI',
            'nama' => 'Kurikulum Koreksi',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);
        $mataKuliah = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF-KOR-01',
            'nama' => 'Pengujian Koreksi Nilai',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);
        $this->kelas = Kelas::create([
            'program_studi_id' => $this->programStudi->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'kode_kelas' => 'IF-KOR-A',
            'nama_kelas' => 'Kelas Koreksi A',
            'kapasitas' => 30,
        ]);
        DosenPengampu::create([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $dosen->id,
            'peran' => 'pengampu_utama',
        ]);

        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->mahasiswaUser->id,
            'program_studi_id' => $this->programStudi->id,
            'nim' => '2026009001',
            'nama_lengkap' => 'Mahasiswa Koreksi',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);
        $krs = Krs::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'status' => 'disetujui',
            'total_sks_diambil' => 3,
        ]);
        $this->krsDetail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_id' => $this->kelas->id,
            'status' => 'aktif',
        ]);
        $this->nilai = NilaiMahasiswa::create([
            'krs_detail_id' => $this->krsDetail->id,
            'nilai_harian' => 70,
            'nilai_uts' => 70,
            'nilai_uas' => 70,
            'nilai_praktik' => 70,
            'nilai_akhir' => 70,
            'nilai_huruf' => 'B',
            'bobot_mutu' => 3,
            'is_final' => true,
            'diinput_oleh' => $this->dosenUser->id,
        ]);
    }

    public function test_koreksi_konvensional_perlu_persetujuan_dan_menghitung_kembali_nilai(): void
    {
        Passport::actingAs($this->dosenUser);

        $response = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'konvensional',
            'nilai_mahasiswa_id' => $this->nilai->id,
            'nilai_baru' => ['nilai_uas' => 90],
            'alasan_koreksi' => 'Perbaikan hasil input ujian akhir.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', KoreksiNilaiMahasiswa::STATUS_MENUNGGU);
        $this->assertDatabaseHas('siakad_nilai_mahasiswa', [
            'id' => $this->nilai->id,
            'nilai_uas' => 70,
            'nilai_akhir' => 70,
        ]);
        $this->assertDatabaseHas('siakad_nilai_koreksi', [
            'id' => $response->json('data.id'),
            'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
        ]);
        $correction = KoreksiNilaiMahasiswa::findOrFail($response->json('data.id'));
        $this->assertSame(70.0, (float) $correction->nilai_lama['nilai_uas']);
        $this->assertSame(90.0, (float) $correction->nilai_baru['nilai_uas']);

        Passport::actingAs($this->reviewerUser);
        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$response->json('data.id')}/approve", [
            'catatan_reviewer' => 'Disetujui setelah verifikasi dokumen.',
        ])->assertOk()
            ->assertJsonPath('data.status', KoreksiNilaiMahasiswa::STATUS_DISETUJUI);

        $this->assertDatabaseHas('siakad_nilai_mahasiswa', [
            'id' => $this->nilai->id,
            'nilai_uas' => 90,
            'nilai_akhir' => 77,
            'nilai_huruf' => 'B+',
            'is_final' => true,
        ]);
    }

    public function test_pengaju_tidak_dapat_menyetujui_koreksinya_sendiri(): void
    {
        Passport::actingAs($this->dosenUser);
        $correctionId = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'konvensional',
            'nilai_mahasiswa_id' => $this->nilai->id,
            'nilai_baru' => ['nilai_uas' => 90],
            'alasan_koreksi' => 'Perbaikan hasil input ujian akhir.',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$correctionId}/approve", [
            'catatan_reviewer' => 'Persetujuan mandiri tidak diizinkan.',
        ])->assertForbidden();

        $this->assertDatabaseHas('siakad_nilai_koreksi', [
            'id' => $correctionId,
            'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
        ]);
    }

    public function test_reviewer_tidak_dapat_memutuskan_koreksi_di_luar_prodinya(): void
    {
        Passport::actingAs($this->dosenUser);
        $correctionId = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'konvensional',
            'nilai_mahasiswa_id' => $this->nilai->id,
            'nilai_baru' => ['nilai_uas' => 90],
            'alasan_koreksi' => 'Perbaikan hasil input ujian akhir.',
        ])->assertCreated()->json('data.id');

        $otherProgram = ProgramStudi::create([
            'kode_prodi' => 'IF-KOREKSI-OTHER',
            'nama' => 'Informatika Lain',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);
        $this->kelas->mataKuliah->kurikulum->update(['program_studi_id' => $otherProgram->id]);

        Passport::actingAs($this->reviewerUser);
        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$correctionId}/approve", [
            'catatan_reviewer' => 'Tidak berwenang pada program studi ini.',
        ])->assertForbidden();

        $this->assertDatabaseHas('siakad_nilai_koreksi', [
            'id' => $correctionId,
            'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
        ]);
    }

    public function test_daftar_koreksi_mendukung_pencarian_filter_dan_sorting(): void
    {
        $attributes = [
            'krs_detail_id' => $this->krsDetail->id,
            'jenis_nilai' => KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL,
            'nilai_mahasiswa_id' => $this->nilai->id,
            'nilai_lama' => ['nilai_uas' => 70],
            'nilai_baru' => ['nilai_uas' => 80],
            'alasan_koreksi' => 'Perbaikan hasil input ujian.',
            'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
            'diajukan_oleh' => $this->dosenUser->id,
        ];
        $older = KoreksiNilaiMahasiswa::create($attributes);
        $newer = KoreksiNilaiMahasiswa::create([
            ...$attributes,
            'nilai_baru' => ['nilai_uas' => 85],
            'alasan_koreksi' => 'Validasi ulang ujian.',
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $newer->forceFill(['created_at' => now()])->saveQuietly();

        Passport::actingAs($this->dosenUser);
        $this->getJson('/api/v1/siakad/perkuliahan/nilai-koreksi?search=ujian&status=menunggu&sort_by=created_at&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id)
            ->assertJsonPath('meta.filters.search', 'ujian')
            ->assertJsonPath('meta.filters.sort_by', 'created_at')
            ->assertJsonPath('meta.filters.sort_order', 'asc');

        $this->getJson('/api/v1/siakad/perkuliahan/nilai-koreksi?search=tidak-ada')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_dosen_di_luar_penugasan_kelas_tidak_dapat_mengajukan_koreksi(): void
    {
        $otherUser = User::factory()->create(['username' => 'nilai.dosen.luar', 'email' => 'nilai.dosen.luar@test.ac.id']);
        $otherUser->roles()->sync([$this->dosenUser->roles()->first()->id]);
        Dosen::create([
            'user_id' => $otherUser->id,
            'program_studi_id' => $this->programStudi->id,
            'nidn' => '1199000103',
            'nama_lengkap' => 'Dosen Luar',
            'status_aktif' => 'aktif',
        ]);

        Passport::actingAs($otherUser);
        $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'konvensional',
            'nilai_mahasiswa_id' => $this->nilai->id,
            'nilai_baru' => ['nilai_uas' => 90],
            'alasan_koreksi' => 'Perbaikan hasil input ujian akhir.',
        ])->assertForbidden();

        $this->assertDatabaseCount('siakad_nilai_koreksi', 0);
    }

    public function test_koreksi_obe_menghitung_ulang_nilai_akhir(): void
    {
        $this->tahunAkademik->update(['mode_penilaian' => 'semi_obe']);
        $component = KomponenPenilaian::create([
            'kelas_id' => $this->kelas->id,
            'nama_komponen' => 'Proyek',
            'teknik_penilaian' => 'proyek',
            'bobot' => 100,
            'urutan' => 1,
            'is_aktif' => true,
        ]);
        $componentGrade = NilaiKomponenMahasiswa::create([
            'krs_detail_id' => $this->krsDetail->id,
            'komponen_penilaian_id' => $component->id,
            'nilai_angka' => 60,
            'diinput_oleh' => $this->dosenUser->id,
        ]);
        $this->nilai->update(['nilai_akhir' => 60, 'nilai_huruf' => 'C']);

        Passport::actingAs($this->dosenUser);
        $correctionId = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'komponen_obe',
            'nilai_komponen_mahasiswa_id' => $componentGrade->id,
            'nilai_baru' => ['nilai_angka' => 90],
            'alasan_koreksi' => 'Perbaikan hasil penilaian proyek.',
        ])->assertCreated()->json('data.id');

        Passport::actingAs($this->reviewerUser);
        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$correctionId}/approve", [
            'catatan_reviewer' => 'Disetujui setelah memeriksa rubrik.',
        ])->assertOk();

        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'id' => $componentGrade->id,
            'nilai_angka' => 90,
        ]);
        $this->assertDatabaseHas('siakad_nilai_mahasiswa', [
            'id' => $this->nilai->id,
            'nilai_akhir' => 90,
            'nilai_huruf' => 'A',
            'is_final' => true,
        ]);
    }

    public function test_koreksi_komponen_ditolak_di_luar_mode_semi_obe(): void
    {
        $component = KomponenPenilaian::create([
            'kelas_id' => $this->kelas->id,
            'nama_komponen' => 'Proyek',
            'teknik_penilaian' => 'proyek',
            'bobot' => 100,
            'urutan' => 1,
            'is_aktif' => true,
        ]);
        $componentGrade = NilaiKomponenMahasiswa::create([
            'krs_detail_id' => $this->krsDetail->id,
            'komponen_penilaian_id' => $component->id,
            'nilai_angka' => 60,
            'diinput_oleh' => $this->dosenUser->id,
        ]);

        Passport::actingAs($this->dosenUser);
        $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'komponen_obe',
            'nilai_komponen_mahasiswa_id' => $componentGrade->id,
            'nilai_baru' => ['nilai_angka' => 90],
            'alasan_koreksi' => 'Perbaikan hasil penilaian proyek.',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('siakad_nilai_koreksi', 0);
    }

    public function test_koreksi_komponen_tidak_diterapkan_jika_mode_berubah(): void
    {
        $this->tahunAkademik->update(['mode_penilaian' => 'semi_obe']);
        $component = KomponenPenilaian::create([
            'kelas_id' => $this->kelas->id,
            'nama_komponen' => 'Proyek',
            'teknik_penilaian' => 'proyek',
            'bobot' => 100,
            'urutan' => 1,
            'is_aktif' => true,
        ]);
        $componentGrade = NilaiKomponenMahasiswa::create([
            'krs_detail_id' => $this->krsDetail->id,
            'komponen_penilaian_id' => $component->id,
            'nilai_angka' => 60,
            'diinput_oleh' => $this->dosenUser->id,
        ]);

        Passport::actingAs($this->dosenUser);
        $correctionId = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'komponen_obe',
            'nilai_komponen_mahasiswa_id' => $componentGrade->id,
            'nilai_baru' => ['nilai_angka' => 90],
            'alasan_koreksi' => 'Perbaikan hasil penilaian proyek.',
        ])->assertCreated()->json('data.id');

        $this->tahunAkademik->update(['mode_penilaian' => 'full_obe']);
        Passport::actingAs($this->reviewerUser);
        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$correctionId}/approve", [
            'catatan_reviewer' => 'Disetujui setelah memeriksa rubrik.',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('siakad_nilai_komponen_mhs', [
            'id' => $componentGrade->id,
            'nilai_angka' => 60,
        ]);
        $this->assertDatabaseHas('siakad_nilai_koreksi', [
            'id' => $correctionId,
            'status' => KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
        ]);
    }

    public function test_koreksi_cpmk_obe_memperbarui_status_capaian_dan_nilai_akhir(): void
    {
        $this->tahunAkademik->update(['mode_penilaian' => 'full_obe']);
        $cpmk = Cpmk::create([
            'mata_kuliah_id' => $this->kelas->mata_kuliah_id,
            'kode_cpmk' => 'CPMK-KOR-1',
            'deskripsi' => 'Capaian pengujian nilai.',
            'bobot_persentase' => 100,
        ]);
        $attainment = KetercapaianCpmkMahasiswa::create([
            'krs_detail_id' => $this->krsDetail->id,
            'cpmk_id' => $cpmk->id,
            'skor_ketercapaian' => 50,
            'status_ketercapaian' => 'belum_tercapai',
        ]);
        $this->nilai->update(['nilai_akhir' => 50, 'nilai_huruf' => 'D']);

        Passport::actingAs($this->dosenUser);
        $correctionId = $this->postJson('/api/v1/siakad/perkuliahan/nilai-koreksi', [
            'jenis_nilai' => 'cpmk_obe',
            'ketercapaian_cpmk_mahasiswa_id' => $attainment->id,
            'nilai_baru' => ['nilai_angka' => 85],
            'alasan_koreksi' => 'Koreksi evaluasi capaian CPMK.',
        ])->assertCreated()->json('data.id');

        Passport::actingAs($this->reviewerUser);
        $this->patchJson("/api/v1/siakad/perkuliahan/nilai-koreksi/{$correctionId}/approve", [
            'catatan_reviewer' => 'Disetujui setelah verifikasi portofolio.',
        ])->assertOk();

        $this->assertDatabaseHas('siakad_ketercapaian_cpmk_mhs', [
            'id' => $attainment->id,
            'skor_ketercapaian' => 85,
            'status_ketercapaian' => 'tercapai',
        ]);
        $this->assertDatabaseHas('siakad_nilai_mahasiswa', [
            'id' => $this->nilai->id,
            'nilai_akhir' => 85,
            'nilai_huruf' => 'A',
            'is_final' => true,
        ]);
    }
}