<?php

namespace Tests\Feature\SPMB;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Spmb\MasterSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SpmbMasterSekolahTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $calon;

    private User $tanpaAkses;

    private function userWith(string $username, array $slugs): User
    {
        $role = Role::firstOrCreate(['slug' => "role_{$username}"], ['name' => $username, 'is_active' => true]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'spmb', 'action' => 'read'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::create([
            'username' => $username, 'email' => "{$username}@test.com",
            'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $this->admin = $this->userWith('admin_sek', ['spmb.manage']);
        $this->calon = $this->userWith('calon_sek', ['spmb.pendaftaran.read', 'spmb.pendaftaran.create']);
        $this->tanpaAkses = $this->userWith('tanpa_sek', []);
    }

    public function test_create_menyimpan_sekolah_dan_merapikan_nama(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/master-sekolah', ['nama' => '  SD   MALAKA ', 'alamat' => 'Jl. Malaka'])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'SD MALAKA')
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('spmb_master_sekolah', [
            'nama' => 'SD MALAKA', 'nama_normal' => 'sd malaka', 'alamat' => 'Jl. Malaka',
            'sumber' => MasterSekolah::SUMBER_MANUAL, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_create_idempoten_dan_hanya_mengisi_alamat_kosong(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master-sekolah', ['nama' => 'SD MALAKA'])->assertCreated();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/master-sekolah', ['nama' => 'sd  malaka', 'alamat' => 'Alamat Pertama'])
            ->assertOk()->assertJsonPath('data.created', false)->assertJsonPath('data.alamat', 'Alamat Pertama');

        // Alamat yang sudah terisi tidak ditimpa.
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/master-sekolah', ['nama' => 'SD MALAKA', 'alamat' => 'Alamat Lain'])
            ->assertOk()->assertJsonPath('data.alamat', 'Alamat Pertama');

        $this->assertSame(1, MasterSekolah::count());
    }

    public function test_pencarian_menemukan_sekolah_yang_sudah_dibuat(): void
    {
        MasterSekolah::create(['nama' => 'SD MALAKA', 'nama_normal' => 'sd malaka']);
        MasterSekolah::create(['nama' => 'SMA NEGERI 1', 'nama_normal' => 'sma negeri 1']);

        $response = $this->actingAs($this->admin, 'api')->getJson('/api/spmb/master-sekolah?search=malaka')->assertOk();

        $this->assertSame(['SD MALAKA'], collect($response->json('data'))->pluck('nama')->all());
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_karakter_wildcard_pada_pencarian_tidak_dianggap_pola(): void
    {
        MasterSekolah::create(['nama' => 'SD MALAKA', 'nama_normal' => 'sd malaka']);

        $this->actingAs($this->admin, 'api')->getJson('/api/spmb/master-sekolah?search=%25')
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_calon_mahasiswa_dapat_mencari_dan_menambah_sekolah(): void
    {
        $this->actingAs($this->calon, 'api')->postJson('/api/spmb/master-sekolah', ['nama' => 'SMK BARU'])->assertCreated();
        $this->actingAs($this->calon, 'api')->getJson('/api/spmb/master-sekolah?search=baru')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_pengguna_tanpa_izin_ditolak(): void
    {
        $this->actingAs($this->tanpaAkses, 'api')->getJson('/api/spmb/master-sekolah')->assertForbidden();
        $this->actingAs($this->tanpaAkses, 'api')->postJson('/api/spmb/master-sekolah', ['nama' => 'X'])->assertForbidden();
        $this->assertSame(0, MasterSekolah::count());
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/spmb/master-sekolah')->assertUnauthorized();
        $this->postJson('/api/spmb/master-sekolah', ['nama' => 'X'])->assertUnauthorized();
    }

    public function test_nama_wajib_diisi_dan_dibatasi_255_karakter(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master-sekolah', [])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master-sekolah', ['nama' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('nama');
    }
}
