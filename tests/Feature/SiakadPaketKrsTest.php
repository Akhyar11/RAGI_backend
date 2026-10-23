<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\HakaksesKrs;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\PaketMkRombel;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadPaketKrsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $prodi;
    protected $ta;
    protected $rombel;
    protected $mk1;
    protected $mk2;
    protected $mhs;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $role = Role::where('slug', 'superadmin')->first();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->roles()->attach($role->id);

        $this->prodi = ProgramStudi::create(['kode' => 'TI01', 'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]);
        $this->ta = TahunAkademik::create(['kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true]);

        $kurikulum = Kurikulum::create(['program_studi_id' => $this->prodi->id, 'kode' => 'KUR-PKT', 'nama' => 'Kurikulum Paket 2026', 'tahun_berlaku' => 2026, 'is_active' => true]);
        $this->mk1 = MataKuliah::create(['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF201', 'nama' => 'Basis Data', 'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true]);
        $this->mk2 = MataKuliah::create(['kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF202', 'nama' => 'Jaringan Komputer', 'sks_teori' => 3, 'sks_praktik' => 0, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true]);

        $this->rombel = MasterKelas::create(['program_studi_id' => $this->prodi->id, 'nama_kelas' => '24A', 'tahun_angkatan' => 2024, 'is_active' => true]);

        $this->mhs = Mahasiswa::create([
            'program_studi_id' => $this->prodi->id,
            'nim' => '2401001',
            'nama_lengkap' => 'Mahasiswa Paket',
            'angkatan' => 2024,
            'kelas' => '24A',
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);
    }

    public function test_crud_paket_mk_rombel(): void
    {
        $create = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk', [
            'master_kelas_id' => $this->rombel->id,
            'mata_kuliah_id' => $this->mk1->id,
            'semester_anjuran' => 3,
        ]);
        $create->assertStatus(201);
        $create->assertJsonStructure(['status', 'message', 'data' => ['id', 'master_kelas_id', 'mata_kuliah_id']]);
        $this->assertDatabaseHas('siakad_paket_mk_rombel', ['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id]);

        // Duplikat: 200 + baris yang sama, tanpa baris ganda
        $dup = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk', [
            'master_kelas_id' => $this->rombel->id,
            'mata_kuliah_id' => $this->mk1->id,
        ]);
        $dup->assertStatus(200);
        $dup->assertJsonPath('data.id', $create->json('data.id'));
        $this->assertEquals(1, PaketMkRombel::where('master_kelas_id', $this->rombel->id)->where('mata_kuliah_id', $this->mk1->id)->count());

        $list = $this->getJson("/api/v1/siakad/perkuliahan/paket-mk?master_kelas_id={$this->rombel->id}");
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $id = $create->json('data.id');
        $update = $this->putJson("/api/v1/siakad/perkuliahan/paket-mk/{$id}", ['is_wajib' => false]);
        $update->assertStatus(200);
        $this->assertDatabaseHas('siakad_paket_mk_rombel', ['id' => $id, 'is_wajib' => false]);

        $delete = $this->deleteJson("/api/v1/siakad/perkuliahan/paket-mk/{$id}");
        $delete->assertStatus(200);
        $this->assertDatabaseMissing('siakad_paket_mk_rombel', ['id' => $id]);
    }

    public function test_bulk_sync_mengganti_isi_paket(): void
    {
        $sk = \App\Models\Arsip\NomorSurat::create([
            'nomor_surat' => '12/SK/TI/X/2026',
            'nomor_urut' => 12,
            'kode_unit' => 'TI',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Penetapan Paket Rombel 24A',
            'tujuan' => 'Rombel 24A TI',
            'status' => 'terpakai',
            'file_path' => 'arsip/dokumen-surat/sk-paket-24a.pdf',
            'created_by' => $this->user->id,
        ]);

        PaketMkRombel::create(['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id, 'nomor_sk' => $sk->nomor_surat, 'semester_anjuran' => 3]);

        $res = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'nomor_sk' => $sk->nomor_surat,
            'items' => [['mata_kuliah_id' => $this->mk2->id, 'semester_anjuran' => 3]],
        ]);
        $res->assertStatus(200);
        $this->assertDatabaseMissing('siakad_paket_mk_rombel', ['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id]);
        $this->assertDatabaseHas('siakad_paket_mk_rombel', ['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk2->id, 'nomor_sk' => $sk->nomor_surat]);
    }

    public function test_bulk_sync_validasi_sk_arsip_dan_berkas_fisik(): void
    {
        // 1. Gagal jika nomor_sk tidak diisi
        $resNoSk = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'items' => [['mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]],
        ]);
        $resNoSk->assertStatus(422);
        $resNoSk->assertJsonValidationErrors('nomor_sk');

        // 2. Gagal jika nomor_sk tidak ada di Arsip
        $resNotFound = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'nomor_sk' => 'SK-PALSU-TIDAK-ADA',
            'items' => [['mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]],
        ]);
        $resNotFound->assertStatus(422);
        $this->assertStringContainsString('tidak terdaftar di modul Arsip', $resNotFound->json('message'));

        // 3. Gagal jika nomor_sk diarsipkan tetapi dibatalkan
        $skDibatalkan = \App\Models\Arsip\NomorSurat::create([
            'nomor_surat' => '99/SK/BATAL/X/2026',
            'nomor_urut' => 99,
            'kode_unit' => 'TI',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Paket Dibatalkan',
            'status' => 'dibatalkan',
            'file_path' => 'arsip/dokumen-surat/sk-batal.pdf',
            'created_by' => $this->user->id,
        ]);
        $resBatal = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'nomor_sk' => $skDibatalkan->nomor_surat,
            'items' => [['mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]],
        ]);
        $resBatal->assertStatus(422);
        $this->assertStringContainsString('DIBATALKAN', $resBatal->json('message'));

        // 4. Gagal jika nomor_sk ada di Arsip tetapi belum diunggah berkas fisiknya (has_dokumen = false)
        $skTanpaBerkas = \App\Models\Arsip\NomorSurat::create([
            'nomor_surat' => '100/SK/NOFILE/X/2026',
            'nomor_urut' => 100,
            'kode_unit' => 'TI',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Paket Belum Upload',
            'status' => 'terpakai',
            'file_path' => null,
            'file_surat_path' => null,
            'created_by' => $this->user->id,
        ]);
        $resTanpaBerkas = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'nomor_sk' => $skTanpaBerkas->nomor_surat,
            'items' => [['mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]],
        ]);
        $resTanpaBerkas->assertStatus(422);
        $this->assertStringContainsString('berkas fisik dokumen SK belum diunggah', $resTanpaBerkas->json('message'));

        // 5. Berhasil jika nomor_sk ada di Arsip dan berkas fisiknya ada
        $skValid = \App\Models\Arsip\NomorSurat::create([
            'nomor_surat' => '101/SK/OK/X/2026',
            'nomor_urut' => 101,
            'kode_unit' => 'TI',
            'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X',
            'tahun' => 2026,
            'tanggal_surat' => '2026-10-01',
            'perihal' => 'SK Paket Rombel Sah',
            'status' => 'terpakai',
            'file_path' => 'arsip/dokumen-surat/sk-ok.pdf',
            'created_by' => $this->user->id,
        ]);
        $resOk = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'nomor_sk' => $skValid->nomor_surat,
            'items' => [['mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]],
        ]);
        $resOk->assertStatus(200);
        $this->assertDatabaseHas('siakad_paket_mk_rombel', [
            'master_kelas_id' => $this->rombel->id,
            'mata_kuliah_id' => $this->mk1->id,
            'nomor_sk' => $skValid->nomor_surat,
        ]);
    }

    public function test_hakakses_terkunci_menolak_ambil_paket(): void
    {
        PaketMkRombel::create(['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]);
        HakaksesKrs::create(['mahasiswa_id' => $this->mhs->id, 'tahun_akademik_id' => $this->ta->id, 'is_dibuka' => false]);

        $res = $this->postJson('/api/v1/siakad/perkuliahan/krs/ambil-paket', [
            'mahasiswa_id' => $this->mhs->id,
            'tahun_akademik_id' => $this->ta->id,
        ]);
        $res->assertStatus(422);
        $this->assertStringContainsString('BAAK', $res->json('message'));
    }

    public function test_paket_saya_memuat_rombel_dan_item(): void
    {
        PaketMkRombel::create(['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]);

        $res = $this->getJson("/api/v1/siakad/perkuliahan/krs/paket-saya?mahasiswa_id={$this->mhs->id}&tahun_akademik_id={$this->ta->id}");
        $res->assertStatus(200);
        $res->assertJsonStructure(['status', 'data' => ['rombel', 'is_krs_dibuka', 'items']]);
        $this->assertEquals('24A', $res->json('data.rombel.nama_kelas'));
        $this->assertTrue($res->json('data.is_krs_dibuka'));
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_hakakses_rombel_bulk(): void
    {
        $res = $this->postJson('/api/v1/siakad/perkuliahan/krs/hakakses-rombel', [
            'master_kelas_id' => $this->rombel->id,
            'tahun_akademik_id' => $this->ta->id,
            'is_dibuka' => false,
        ]);
        $res->assertStatus(200);
        $res->assertJsonStructure(['status', 'message', 'data' => ['dibuka', 'dikunci', 'total']]);
        $this->assertEquals(1, $res->json('data.total'));
        $this->assertDatabaseHas('siakad_hakakses_krs', ['mahasiswa_id' => $this->mhs->id, 'is_dibuka' => false]);
    }

    public function test_paket_saya_idor_ditolak_antar_mahasiswa(): void
    {
        $roleMhs = Role::where('slug', 'mahasiswa')->first();
        $userA = User::factory()->create(['is_active' => true]);
        $userA->roles()->attach($roleMhs->id);
        Mahasiswa::create([
            'user_id' => $userA->id,
            'program_studi_id' => $this->prodi->id,
            'nim' => '2401002',
            'nama_lengkap' => 'Mahasiswa A',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);
        $mhsB = Mahasiswa::create([
            'program_studi_id' => $this->prodi->id,
            'nim' => '2401003',
            'nama_lengkap' => 'Mahasiswa B',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($userA);

        // Milik sendiri: boleh
        $ok = $this->getJson('/api/v1/siakad/perkuliahan/krs/paket-saya');
        $ok->assertStatus(200);

        // IDOR: mahasiswa A mengoper mahasiswa_id milik B → parameter diabaikan,
        // yang dikembalikan tetap paket milik A (resolveMahasiswa mengutamakan
        // relasi user login), bukan data B.
        $ng = $this->getJson("/api/v1/siakad/perkuliahan/krs/paket-saya?mahasiswa_id={$mhsB->id}");
        $ng->assertStatus(200);
        $this->assertNull($ng->json('data.rombel'));

        $amb = $this->postJson('/api/v1/siakad/perkuliahan/krs/ambil-paket', ['mahasiswa_id' => $mhsB->id]);
        // Tidak ada KRS terbentuk untuk B; hanya A yang boleh terbentuk
        $this->assertDatabaseMissing('siakad_krs', ['mahasiswa_id' => $mhsB->id]);
    }

    public function test_non_manajerial_tanpa_relasi_ditolak_guard(): void
    {
        // Role khusus hanya berizin baca KRS (tanpa relasi mahasiswa/dosen/
        // manajerial) yang memaksa mahasiswa_id orang lain → 403 via guard
        // (gate krs.read lolos, guard yang menolak).
        $role = \App\Models\Role::create(['name' => 'Petugas Baca', 'slug' => 'petugas-baca']);
        $perm = \App\Models\Permission::where('slug', 'siakad.krs.read')->first();
        $role->permissions()->attach($perm->id);
        $userD = User::factory()->create(['is_active' => true]);
        $userD->roles()->attach($role->id);
        $mhsB = Mahasiswa::create([
            'program_studi_id' => $this->prodi->id,
            'nim' => '2401004',
            'nama_lengkap' => 'Mahasiswa B2',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($userD);
        $ng = $this->getJson("/api/v1/siakad/perkuliahan/krs/paket-saya?mahasiswa_id={$mhsB->id}");
        $ng->assertStatus(403);
    }

    public function test_obe_admin_dosen_dapat_mengecek_nomor_sk_di_arsip(): void
    {
        $dosenRole = Role::where('slug', 'dosen')->first();
        $userObe = User::factory()->create(['is_active' => true]);
        if ($dosenRole) {
            $userObe->roles()->attach($dosenRole->id);
        }

        // Plot sebagai admin OBE di prodi
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert([
            'user_id' => $userObe->id,
            'program_studi_id' => $this->prodi->id,
            'jabatan' => 'Admin OBE Prodi',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($userObe->isObeAdmin());
        $this->assertFalse($userObe->isAdmin());

        Passport::actingAs($userObe);

        // Pengecekan nomor SK tidak boleh 403 Forbidden
        $res = $this->getJson('/api/arsip/nomor-surat/check?nomor_surat=SK-OBE-TEST/2026');
        $res->assertStatus(200);
        $res->assertJson([
            'status' => 'success',
            'data' => [
                'is_found' => false,
                'is_valid' => false,
            ],
        ]);
    }
}

