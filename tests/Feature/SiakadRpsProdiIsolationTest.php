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
}
