<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Database\Seeders\IAM\LaboranProdiRoleSeeder;
use Database\Seeders\IAM\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboranProdiRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_laboran_prodi_role_seeder_creates_all_10_roles_and_attaches_permissions()
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LaboranProdiRoleSeeder::class);

        $expectedSlugs = [
            'laboran_d3_farmasi',
            'laboran_d3_perhotelan',
            'laboran_d3_sistem_informasi',
            'laboran_d4_akuntansi_perpajakan',
            'laboran_d4_bisnis_manajemen_ritel',
            'laboran_d4_manajemen_informasi_kesehatan',
            'laboran_d4_produksi_media',
            'laboran_d4_teknologi_laboratorium_medis',
            'laboran_d4_teknologi_rekayasa_otomotif',
            'laboran_d4_trpl',
        ];

        foreach ($expectedSlugs as $slug) {
            $role = Role::where('slug', $slug)->first();
            $this->assertNotNull($role, "Role dengan slug '{$slug}' harus ada.");
            $this->assertTrue((bool) $role->is_active);
            $this->assertTrue(
                $role->permissions()->where('slug', 'sinapra.dashboard.read')->exists(),
                "Role '{$slug}' harus memiliki permission sinapra.dashboard.read."
            );
            $this->assertTrue(
                $role->permissions()->where('slug', 'sinapra.peminjaman_ruangan.approve_laboran')->exists(),
                "Role '{$slug}' harus memiliki permission approve laboran."
            );
        }
    }

    public function test_laboran_prodi_role_seeder_is_idempotent()
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LaboranProdiRoleSeeder::class);
        $this->seed(LaboranProdiRoleSeeder::class); // Run second time

        $count = Role::where('slug', 'like', 'laboran%')->count();
        $this->assertEquals(10, $count);
    }

    public function test_laboran_prodi_roles_only_get_operational_menus_excluding_master_and_pengadaan()
    {
        $this->seed(PermissionSeeder::class);

        // Buat menu simulasi SINAPRA
        \App\Models\Menu::create(['name' => 'Dashboard', 'url' => '/sinapra', 'module' => 'sinapra', 'order' => 1]);
        \App\Models\Menu::create(['name' => 'Inventaris Aset', 'url' => '/sinapra/aset', 'module' => 'sinapra', 'order' => 2]);
        \App\Models\Menu::create(['name' => 'Pengadaan', 'url' => '/sinapra/pengadaan', 'module' => 'sinapra', 'order' => 3]);
        \App\Models\Menu::create(['name' => 'Master Vendor', 'url' => '/sinapra/master/vendor', 'module' => 'sinapra', 'order' => 4]);

        $this->seed(LaboranProdiRoleSeeder::class);

        $role = Role::where('slug', 'laboran_d4_trpl')->first();
        $this->assertNotNull($role);

        $attachedUrls = $role->menus()->pluck('url')->toArray();

        // Operasional harus ada
        $this->assertContains('/sinapra', $attachedUrls);
        $this->assertContains('/sinapra/aset', $attachedUrls);

        // Master data dan pengadaan DILARANG ada
        $this->assertNotContains('/sinapra/pengadaan', $attachedUrls);
        $this->assertNotContains('/sinapra/master/vendor', $attachedUrls);
    }
}
