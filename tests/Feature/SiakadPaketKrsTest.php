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
        PaketMkRombel::create(['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id, 'semester_anjuran' => 3]);

        $res = $this->postJson('/api/v1/siakad/perkuliahan/paket-mk/bulk-sync', [
            'master_kelas_id' => $this->rombel->id,
            'items' => [['mata_kuliah_id' => $this->mk2->id, 'semester_anjuran' => 3]],
        ]);
        $res->assertStatus(200);
        $this->assertDatabaseMissing('siakad_paket_mk_rombel', ['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk1->id]);
        $this->assertDatabaseHas('siakad_paket_mk_rombel', ['master_kelas_id' => $this->rombel->id, 'mata_kuliah_id' => $this->mk2->id]);
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
}
