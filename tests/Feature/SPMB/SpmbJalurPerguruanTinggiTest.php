<?php

namespace Tests\Feature\SPMB;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Spmb\JalurMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SpmbJalurPerguruanTinggiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $role = Role::firstOrCreate(['slug' => 'admin_spmb'], ['name' => 'Admin SPMB', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['slug' => 'spmb.manage'],
            ['name' => 'Kelola Admin SPMB', 'module' => 'spmb', 'action' => 'update'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->admin = User::create([
            'username' => 'admin_pt', 'email' => 'admin_pt@test.com',
            'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->admin->roles()->syncWithoutDetaching([$role->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'kode' => 'PT1', 'nama' => 'Jalur PT', 'ada_wawancara' => false, 'is_active' => true,
        ], $override);
    }

    public function test_jalur_baru_default_tidak_mengaktifkan_pilihan_perguruan_tinggi(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/jalur', $this->payload())
            ->assertCreated();

        $this->assertFalse(JalurMasuk::where('kode', 'PT1')->firstOrFail()->pilih_perguruan_tinggi);
    }

    public function test_jalur_dapat_dibuat_dengan_pilihan_perguruan_tinggi_aktif(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/jalur', $this->payload(['pilih_perguruan_tinggi' => true]))
            ->assertCreated()
            ->assertJsonPath('data.pilih_perguruan_tinggi', true);
    }

    public function test_update_mengubah_dan_mempertahankan_flag(): void
    {
        $jalur = JalurMasuk::create(['kode' => 'PT2', 'nama' => 'Jalur', 'is_active' => true, 'pilih_perguruan_tinggi' => true]);
        $base = ['kode' => 'PT2', 'nama' => 'Jalur Baru', 'ada_wawancara' => false, 'is_active' => true];

        // Tidak dikirim: nilai tidak berubah.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/jalur/{$jalur->id}", $base)->assertOk();
        $this->assertTrue($jalur->fresh()->pilih_perguruan_tinggi);

        // Dikirim false: dinonaktifkan.
        $this->actingAs($this->admin, 'api')
            ->putJson("/api/spmb/jalur/{$jalur->id}", $base + ['pilih_perguruan_tinggi' => false])->assertOk();
        $this->assertFalse($jalur->fresh()->pilih_perguruan_tinggi);
    }

    public function test_daftar_dan_detail_jalur_memuat_flag(): void
    {
        $jalur = JalurMasuk::create(['kode' => 'PT3', 'nama' => 'Jalur', 'is_active' => true, 'pilih_perguruan_tinggi' => true]);

        $list = $this->actingAs($this->admin, 'api')->getJson('/api/spmb/jalur?status=aktif')->assertOk()->json('data');
        $this->assertTrue(collect($list)->firstWhere('id', $jalur->id)['pilih_perguruan_tinggi']);

        $this->actingAs($this->admin, 'api')->getJson("/api/spmb/jalur/{$jalur->id}")
            ->assertOk()->assertJsonPath('data.pilih_perguruan_tinggi', true);
    }

    public function test_nilai_bukan_boolean_ditolak(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/jalur', $this->payload(['pilih_perguruan_tinggi' => 'mungkin']))
            ->assertStatus(422);
    }
}
