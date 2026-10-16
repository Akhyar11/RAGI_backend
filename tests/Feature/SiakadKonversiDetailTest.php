<?php

namespace Tests\Feature;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadKonversiDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $dosenPaUser;
    protected User $dosenLainUser;
    protected Mahasiswa $mahasiswa;
    protected MataKuliah $mataKuliah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-KNV',
            'nama'       => 'Informatika Konversi',
            'jenjang'    => 'S1',
        ]);

        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode'             => 'KUR-KNV',
            'nama'             => 'Kurikulum KNV 2026',
            'tahun_berlaku'    => 2026,
            'is_active'        => true,
        ]);

        $this->mataKuliah = MataKuliah::create([
            'kurikulum_id'     => $kurikulum->id,
            'kode_mk'          => 'IF101',
            'nama'             => 'Pengantar Informatika',
            'sks_teori'        => 2,
            'sks_praktik'      => 1,
            'total_sks'        => 3,
            'semester_anjuran' => 1,
            'is_active'        => true,
        ]);

        $this->admin = User::factory()->create(['username' => 'admin.knv']);
        $this->dosenPaUser = User::factory()->create(['username' => 'pa.knv']);
        $this->dosenLainUser = User::factory()->create(['username' => 'lain.knv']);

        $dosenPa = Dosen::create([
            'user_id'          => $this->dosenPaUser->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0011001100',
            'nama_lengkap'     => 'Dosen PA KNV',
            'status_aktif'     => 'aktif',
        ]);

        Dosen::create([
            'user_id'          => $this->dosenLainUser->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0022002200',
            'nama_lengkap'     => 'Dosen Lain KNV',
            'status_aktif'     => 'aktif',
        ]);

        $mhsUser = User::factory()->create(['username' => 'mhs.knv']);
        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $mhsUser->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026007001',
            'nama_lengkap'     => 'Mahasiswa Konversi',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
            'dosen_wali_id'    => $dosenPa->id,
        ]);

        $roleAdmin = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $roleDosen = \App\Models\Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);

        $this->admin->roles()->attach($roleAdmin->id);
        $this->dosenPaUser->roles()->attach($roleDosen->id);
        $this->dosenLainUser->roles()->attach($roleDosen->id);
    }

    protected function buatKonversi(): KonversiTransfer
    {
        Passport::actingAs($this->admin);

        $res = $this->postJson('/api/v1/siakad/mahasiswa/konversi', [
            'mahasiswa_id' => $this->mahasiswa->id,
            'kampus_asal'  => 'Universitas Nusantara',
            'prodi_asal'   => 'Teknik Komputer',
            'status'       => 'diajukan',
            'details'      => [
                [
                    'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                    'kode_mk_asal'          => 'CS101',
                    'nama_mk_asal'          => 'Dasar Pemrograman',
                    'sks_asal'              => 3,
                    'nilai_huruf_asal'      => 'A',
                ],
            ],
        ]);
        $res->assertStatus(201);

        return KonversiTransfer::findOrFail($res->json('data.id'));
    }

    public function test_admin_can_view_konversi_detail(): void
    {
        $konversi = $this->buatKonversi();

        Passport::actingAs($this->admin);
        $this->getJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}")
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['id', 'no_transaksi', 'mahasiswa', 'details'],
            ]);
    }

    public function test_dosen_pa_can_view_but_other_dosen_forbidden(): void
    {
        $konversi = $this->buatKonversi();

        Passport::actingAs($this->dosenPaUser);
        $this->getJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}")
            ->assertStatus(200);

        Passport::actingAs($this->dosenLainUser);
        $this->getJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_update_konversi_via_put(): void
    {
        $konversi = $this->buatKonversi();

        Passport::actingAs($this->admin);
        $this->putJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}", [
            'kampus_asal' => 'Universitas Nusantara (Revisi)',
            'prodi_asal'  => 'Teknik Komputer',
            'status'      => 'diajukan',
            'details'     => [
                [
                    'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                    'kode_mk_asal'          => 'CS102',
                    'nama_mk_asal'          => 'Pemrograman Dasar',
                    'sks_asal'              => 3,
                    'nilai_huruf_asal'      => 'B+',
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('siakad_konversi_transfer', [
            'id'          => $konversi->id,
            'kampus_asal' => 'Universitas Nusantara (Revisi)',
        ]);
        $this->assertDatabaseHas('siakad_konversi_transfer_detail', [
            'konversi_id'  => $konversi->id,
            'kode_mk_asal' => 'CS102',
        ]);
        $this->assertDatabaseMissing('siakad_konversi_transfer_detail', [
            'konversi_id'  => $konversi->id,
            'kode_mk_asal' => 'CS101',
        ]);
    }

    public function test_store_konversi_with_invalid_sk_rejected(): void
    {
        Passport::actingAs($this->admin);

        $res = $this->postJson('/api/v1/siakad/mahasiswa/konversi', [
            'mahasiswa_id' => $this->mahasiswa->id,
            'kampus_asal'  => 'Universitas Nusantara',
            'prodi_asal'   => 'Teknik Komputer',
            'nomor_sk'     => 'SK-FIKTIF/999',
            'status'       => 'diajukan',
            'details'      => [
                [
                    'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                    'kode_mk_asal'          => 'CS101',
                    'nama_mk_asal'          => 'Dasar Pemrograman',
                    'sks_asal'              => 3,
                    'nilai_huruf_asal'      => 'A',
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk']);
    }

    public function test_store_and_detail_konversi_with_valid_sk_from_arsip(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('sk_konversi.pdf', 100, 'application/pdf');
        $storedPath = app(\App\Services\Storage\FileStorageService::class)->store($file, 'arsip/dokumen', private: true);

        $surat = \App\Models\Arsip\NomorSurat::create([
            'nomor_surat'      => '12/SK-KONV/UTI/2026',
            'nomor_urut'       => 12,
            'kode_unit'        => 'BAAK',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi'     => 'X',
            'tahun'            => 2026,
            'tanggal_surat'    => '2026-10-01',
            'perihal'          => 'SK Penyetaraan Nilai Mahasiswa Transfer',
            'status'           => 'terpakai',
            'created_by'       => $this->admin->id,
            'file_path'        => $storedPath,
        ]);

        Passport::actingAs($this->admin);

        $res = $this->postJson('/api/v1/siakad/mahasiswa/konversi', [
            'mahasiswa_id' => $this->mahasiswa->id,
            'kampus_asal'  => 'Universitas Nusantara',
            'prodi_asal'   => 'Teknik Komputer',
            'nomor_sk'     => '12/SK-KONV/UTI/2026',
            'status'       => 'diajukan',
            'details'      => [
                [
                    'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                    'kode_mk_asal'          => 'CS101',
                    'nama_mk_asal'          => 'Dasar Pemrograman',
                    'sks_asal'              => 3,
                    'nilai_huruf_asal'      => 'A',
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.nomor_sk', '12/SK-KONV/UTI/2026');

        $this->assertDatabaseHas('siakad_konversi_transfer', [
            'nomor_sk' => '12/SK-KONV/UTI/2026',
        ]);

        $konversiId = $res->json('data.id');
        $detailRes = $this->getJson("/api/v1/siakad/mahasiswa/konversi/{$konversiId}");
        $detailRes->assertStatus(200)
            ->assertJsonPath('data.nomor_sk', '12/SK-KONV/UTI/2026')
            ->assertJsonPath('data.arsip_nomor_surat.nomor_surat', '12/SK-KONV/UTI/2026');
    }
}
