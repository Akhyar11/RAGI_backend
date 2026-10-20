<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\RpsReferensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

/**
 * Uji isolasi data grup RPS agar data tidak bocor antar prodi.
 */
class SiakadRpsProdiIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiA;
    protected $prodiB;
    protected $userA;
    protected $userB;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $this->prodiA = ProgramStudi::create(['kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]);
        $this->prodiB = ProgramStudi::create(['kode_prodi' => 'SI02', 'nama' => 'Sistem Informasi', 'jenjang' => 'S1', 'is_active' => true]);

        // User A di prodi A
        $this->userA = User::factory()->create(['is_active' => true]);
        $roleA = \App\Models\Role::create(['name' => 'Tim Kurikulum TI', 'slug' => 'tim_ti', 'is_active' => true]);
        $roleA->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.kurikulum.read')->first()->id);
        $this->userA->roles()->attach($roleA->id);
        \App\Models\Siakad\Dosen::create(['user_id' => $this->userA->id, 'nidn' => '1001', 'nama_lengkap' => 'Dosen TI', 'program_studi_id' => $this->prodiA->id]);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert(['user_id' => $this->userA->id, 'program_studi_id' => $this->prodiA->id, 'jabatan' => 'Admin OBE', 'is_active' => true]);

        // User B di prodi B
        $this->userB = User::factory()->create(['is_active' => true]);
        $roleB = \App\Models\Role::create(['name' => 'Tim Kurikulum SI', 'slug' => 'tim_si', 'is_active' => true]);
        $roleB->permissions()->attach(\App\Models\Permission::where('slug', 'siakad.kurikulum.read')->first()->id);
        $this->userB->roles()->attach($roleB->id);
        \App\Models\Siakad\Dosen::create(['user_id' => $this->userB->id, 'nidn' => '2001', 'nama_lengkap' => 'Dosen SI', 'program_studi_id' => $this->prodiB->id]);
        \Illuminate\Support\Facades\DB::table('siakad_admin_prodi')->insert(['user_id' => $this->userB->id, 'program_studi_id' => $this->prodiB->id, 'jabatan' => 'Admin OBE', 'is_active' => true]);
    }

    public function test_data_referensi_rps_tidak_bocor_antar_prodi(): void
    {
        // User A membuat referensi Bentuk di prodi A
        Passport::actingAs($this->userA);
        $resA = $this->postJson('/api/v1/siakad/obe/rps-referensi', [
            'tipe' => 'bentuk',
            'kode' => 'BTK-TI',
            'nama' => 'Praktik Coding Studio TI',
            'deskripsi' => 'Studio koding prodi TI',
        ]);
        $resA->assertStatus(201);
        $this->assertSame($this->prodiA->id, (int)$resA->json('data.program_studi_id'));

        // User A melihat data miliknya
        $listA = $this->getJson('/api/v1/siakad/obe/rps-referensi?tipe=bentuk');
        $this->assertCount(1, $listA->json('data'));
        $this->assertSame('BTK-TI', $listA->json('data.0.kode'));

        // User B login: tidak boleh melihat data prodi A
        Passport::actingAs($this->userB);
        $listB = $this->getJson('/api/v1/siakad/obe/rps-referensi?tipe=bentuk');
        $this->assertCount(0, $listB->json('data'), 'Data referensi RPS prodi A tidak boleh bocor ke user prodi B');
    }

    public function test_user_ditolak_mengubah_atau_menghapus_referensi_rps_prodi_lain(): void
    {
        $refA = RpsReferensi::create([
            'program_studi_id' => $this->prodiA->id,
            'tipe' => 'metode',
            'kode' => 'MTD-TI',
            'nama' => 'PBL Algoritma TI',
        ]);

        Passport::actingAs($this->userB);

        // User B coba update data prodi A
        $this->putJson('/api/v1/siakad/obe/rps-referensi/' . $refA->id, [
            'nama' => 'Diubah oleh prodi B',
        ])->assertStatus(403);

        // User B coba delete data prodi A
        $this->deleteJson('/api/v1/siakad/obe/rps-referensi/' . $refA->id)->assertStatus(403);
    }

    public function test_show_rps_returns_eager_loaded_relations_for_print(): void
    {
        $kurikulum = \App\Models\Siakad\Kurikulum::create([
            'program_studi_id' => $this->prodiA->id,
            'kode' => 'K26-TI',
            'nama' => 'Kurikulum OBE TI 2026',
            'tahun_berlaku' => 2026,
            'is_active' => true,
        ]);

        $rumpun = \App\Models\Siakad\RumpunMataKuliah::create([
            'program_studi_id' => $this->prodiA->id,
            'kode_rumpun' => 'RMK-RPL',
            'nama_rumpun' => 'Rekayasa Perangkat Lunak',
            'is_active' => true,
        ]);

        $mk = \App\Models\Siakad\MataKuliah::create([
            'kurikulum_id' => $kurikulum->id,
            'rumpun_mk_id' => $rumpun->id,
            'kode_mk' => 'TI-101',
            'nama' => 'Pemrograman Web Lanjut',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'total_sks' => 3,
            'semester_anjuran' => 3,
            'jumlah_pertemuan' => 16,
            'is_active' => true,
        ]);

        $dosenA = \App\Models\Siakad\Dosen::where('user_id', $this->userA->id)->first();

        $rps = \App\Models\Siakad\Rps::create([
            'mata_kuliah_id' => $mk->id,
            'kode_rps' => 'RPS-TI-101-2026',
            'tahun_ajaran' => '2026/2027',
            'semester' => 3,
            'tanggal_penyusunan' => '2026-03-01',
            'dosen_pengembang_id' => $dosenA->id,
            'deskripsi_singkat' => 'Mata kuliah pengembangan web modern berbasis API dan Next.js.',
            'status' => 'disetujui',
        ]);

        \App\Models\Siakad\RpsMingguan::create([
            'rps_id' => $rps->id,
            'minggu_ke' => 1,
            'kemampuan_akhir' => 'Mampu memahami arsitektur web modern',
            'bahan_kajian' => 'Arsitektur Web & REST API',
            'topik_materi' => 'Pengenalan Web Modern',
            'bobot_penilaian' => 5,
        ]);

        Passport::actingAs($this->userA);
        $res = $this->getJson('/api/v1/siakad/obe/rps/' . $rps->id);
        $res->assertStatus(200);
        $res->assertJsonPath('data.kode_rps', 'RPS-TI-101-2026');
        $res->assertJsonPath('data.mata_kuliah.rumpun_mata_kuliah.nama_rumpun', 'Rekayasa Perangkat Lunak');
        $res->assertJsonPath('data.mingguan.0.minggu_ke', 1);
    }
}
