<?php

namespace Tests\Feature;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\AdminProdi;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KonversiTransferDetail;
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
    protected User $mahasiswaUser;
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

        $this->mahasiswaUser = User::factory()->create(['username' => 'mhs.knv']);
        $this->mahasiswa = Mahasiswa::create([
            'user_id'          => $this->mahasiswaUser->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026007001',
            'nama_lengkap'     => 'Mahasiswa Konversi',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
            'dosen_wali_id'    => $dosenPa->id,
        ]);

        $roleAdmin = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $roleDosen = \App\Models\Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        $konversiPermission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'siakad.konversi.manage'],
            [
                'name' => 'Kelola Konversi Transfer',
                'module' => 'siakad',
                'action' => 'update',
                'description' => 'Input dan persetujuan penyetaraan nilai transfer',
            ]
        );
        $roleAdmin->permissions()->syncWithoutDetaching([$konversiPermission->id]);
        $roleDosen->permissions()->syncWithoutDetaching([$konversiPermission->id]);

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

    public function test_deleting_draft_konversi_records_audit_log(): void
    {
        $konversi = $this->buatKonversi();

        Passport::actingAs($this->admin);
        $this->deleteJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'delete',
            'table_name' => 'siakad_konversi_transfer',
            'record_id' => $konversi->id,
        ]);
    }

    public function test_dosen_without_program_assignment_cannot_transition_konversi(): void
    {
        $konversi = $this->buatKonversi();

        Passport::actingAs($this->dosenLainUser);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'menunggu_pa',
        ])->assertForbidden();
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

    public function test_student_can_submit_for_self_but_cannot_choose_another_student(): void
    {
        Passport::actingAs($this->mahasiswaUser);
        $payload = [
            'kampus_asal' => 'Universitas Asal',
            'prodi_asal' => 'Teknik Komputer',
            'status' => 'diajukan',
            'details' => [[
                'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                'kode_mk_asal' => 'CS101',
                'nama_mk_asal' => 'Dasar Pemrograman',
                'sks_asal' => 3,
                'nilai_huruf_asal' => 'A',
            ]],
        ];

        $this->postJson('/api/v1/siakad/mahasiswa/konversi', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.mahasiswa_id', $this->mahasiswa->id)
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.details.0.status', 'menunggu');

        $mahasiswaLain = Mahasiswa::create([
            'user_id' => User::factory()->create()->id,
            'program_studi_id' => $this->mahasiswa->program_studi_id,
            'nim' => '2026007002',
            'nama_lengkap' => 'Mahasiswa Lain',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);

        $this->postJson('/api/v1/siakad/mahasiswa/konversi', $payload + [
            'mahasiswa_id' => $mahasiswaLain->id,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('siakad_konversi_transfer', [
            'mahasiswa_id' => $mahasiswaLain->id,
        ]);
    }

    public function test_conversion_requires_program_mapping_pa_approval_and_kaprodi_decision(): void
    {
        $konversi = $this->buatKonversi();
        $detailId = $konversi->details()->firstOrFail()->id;

        Passport::actingAs($this->admin);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'menunggu_pa',
            'details' => [['id' => $detailId, 'status' => 'diakui']],
        ])->assertOk()->assertJsonPath('data.status', 'menunggu_pa');

        Passport::actingAs($this->dosenPaUser);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'disetujui',
            'details' => [['id' => $detailId, 'status' => 'diakui']],
        ])->assertStatus(403);

        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'menunggu_kaprodi',
            'details' => [['id' => $detailId, 'status' => 'diakui']],
        ])->assertOk()->assertJsonPath('data.status', 'menunggu_kaprodi');

        Passport::actingAs($this->dosenLainUser);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'disetujui',
        ])->assertStatus(403);

        Passport::actingAs($this->admin);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'disetujui',
        ])->assertOk()->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('siakad_konversi_transfer', [
            'id' => $konversi->id,
            'status' => 'disetujui',
            'disetujui_pa_oleh' => $this->dosenPaUser->id,
            'disetujui_kaprodi_oleh' => $this->admin->id,
        ]);
    }

    public function test_directly_assigned_admin_prodi_can_manage_only_assigned_program(): void
    {
        $konversiProdiSaya = $this->buatKonversi();
        $prodiLain = ProgramStudi::create([
            'kode_prodi' => 'SI-KNV',
            'nama' => 'Sistem Informasi Konversi',
            'jenjang' => 'S1',
        ]);
        $mahasiswaLain = Mahasiswa::create([
            'user_id' => User::factory()->create()->id,
            'program_studi_id' => $prodiLain->id,
            'nim' => '2026007003',
            'nama_lengkap' => 'Mahasiswa Prodi Lain',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);

        Passport::actingAs($this->admin);
        $responseLain = $this->postJson('/api/v1/siakad/mahasiswa/konversi', [
            'mahasiswa_id' => $mahasiswaLain->id,
            'kampus_asal' => 'Universitas Asal',
            'prodi_asal' => 'Sistem Informasi',
            'status' => 'diajukan',
            'details' => [[
                'mata_kuliah_diakui_id' => $this->mataKuliah->id,
                'kode_mk_asal' => 'SI101',
                'nama_mk_asal' => 'Pengantar Sistem Informasi',
                'sks_asal' => 3,
                'nilai_huruf_asal' => 'A',
            ]],
        ])->assertCreated();
        $konversiProdiLain = KonversiTransfer::findOrFail($responseLain->json('data.id'));

        $adminProdi = User::factory()->create(['username' => 'admin.prodi.knv']);
        $roleAdminProdi = \App\Models\Role::firstOrCreate(
            ['slug' => 'admin_prodi'],
            ['name' => 'Admin Program Studi']
        );
        $roleAdminProdi->permissions()->syncWithoutDetaching([
            \App\Models\Permission::where('slug', 'siakad.konversi.manage')->firstOrFail()->id,
        ]);
        $adminProdi->roles()->attach($roleAdminProdi->id);
        AdminProdi::create([
            'program_studi_id' => $this->mahasiswa->program_studi_id,
            'user_id' => $adminProdi->id,
            'is_active' => true,
        ]);

        Passport::actingAs($adminProdi);
        $this->getJson('/api/v1/siakad/mahasiswa/konversi')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $konversiProdiSaya->id);

        $this->getJson("/api/v1/siakad/mahasiswa/konversi/{$konversiProdiLain->id}")
            ->assertStatus(403);

        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversiProdiSaya->id}/status", [
            'status' => 'menunggu_pa',
        ])->assertOk()->assertJsonPath('data.status', 'menunggu_pa');
    }

    public function test_only_accepted_courses_from_final_conversion_block_krs_addition(): void
    {
        $kurikulum = Kurikulum::firstOrFail();
        $mataKuliahDitolak = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => 'IF102',
            'nama' => 'Matematika Diskrit',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'total_sks' => 3,
            'semester_anjuran' => 1,
            'is_active' => true,
        ]);

        $konversi = $this->buatKonversi();
        $konversi->details()->firstOrFail()->update(['status' => 'diakui']);
        KonversiTransferDetail::create([
            'konversi_id' => $konversi->id,
            'mata_kuliah_diakui_id' => $mataKuliahDitolak->id,
            'kode_mk_asal' => 'MA101',
            'nama_mk_asal' => 'Matematika Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'C',
            'status' => 'ditolak',
        ]);

        Passport::actingAs($this->admin);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'menunggu_pa',
        ])->assertOk();
        Passport::actingAs($this->dosenPaUser);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'menunggu_kaprodi',
        ])->assertOk();
        Passport::actingAs($this->admin);
        $this->patchJson("/api/v1/siakad/mahasiswa/konversi/{$konversi->id}/status", [
            'status' => 'disetujui',
        ])->assertOk();

        $service = app(\App\Services\Siakad\KrsService::class);
        try {
            $service->assertMataKuliahBelumDiakuiKonversi($this->mahasiswa, new Kelas(['mata_kuliah_id' => $this->mataKuliah->id]));
            $this->fail('KRS harus ditolak untuk mata kuliah yang sudah diakui.');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
        }

        $service->assertMataKuliahBelumDiakuiKonversi($this->mahasiswa, new Kelas(['mata_kuliah_id' => $mataKuliahDitolak->id]));
    }
}
