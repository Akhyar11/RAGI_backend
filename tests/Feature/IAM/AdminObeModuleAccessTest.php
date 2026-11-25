<?php

namespace Tests\Feature\IAM;

use App\Models\Menu;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Admin OBE tetap harus melihat modul LMS di module switcher.
 *
 * Pembatasan menu khusus OBE hanya berlaku saat modul SIAKAD diminta.
 * `getMyMenus('all')` dipakai ModuleController untuk menyusun daftar modul,
 * sehingga tidak boleh menyaring modul lain berdasarkan plotting OBE.
 */
class AdminObeModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Role $role;
    private Menu $siakadMenu;
    private Menu $lmsMenu;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        // User pertama adalah fallback superadmin pada database kosong.
        User::factory()->create(['username' => 'dummy_satu']);

        $this->user = User::factory()->create(['username' => 'dosen_admin_obe_uji']);
        $this->role = Role::create([
            'name' => 'Dosen Admin OBE Uji',
            'slug' => 'dosen_admin_obe_uji',
            'is_active' => true,
        ]);

        $permission = Permission::where('slug', 'siakad.kelas.read')->firstOrFail();
        $this->role->permissions()->attach($permission->id);
        $this->user->roles()->attach($this->role->id);

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'OBE-TEST-01',
            'nama' => 'Prodi Uji Admin OBE',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        DB::table('siakad_admin_prodi')->insert([
            'user_id' => $this->user->id,
            'program_studi_id' => $prodi->id,
            'jabatan' => 'Admin OBE / Tim Kurikulum',
            'can_approve_rps' => true,
            'is_active' => true,
            'assigned_by' => User::first()->id,
        ]);

        Module::firstOrCreate(['code' => 'siakad'], [
            'name' => 'SIAKAD Uji',
            'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'lms'], [
            'name' => 'LMS Uji',
            'is_active' => true,
        ]);
        Module::firstOrCreate(['code' => 'sippm'], [
            'name' => 'SIPPM Uji',
            'is_active' => true,
        ]);

        $this->siakadMenu = Menu::create([
            'name' => 'Menu OBE SIAKAD Uji',
            'url' => '/siakad/obe-uji',
            'module' => 'siakad',
            'permission_id' => $permission->id,
            'order_index' => 900,
            'is_active' => true,
        ]);
        $this->siakadMenu->roles()->attach($this->role->id);

        // Menu SIAKAD lain yang tidak di-plot sebagai menu OBE.
        $unplotted = Menu::create([
            'name' => 'Menu SIAKAD Non-OBE Uji',
            'url' => '/siakad/non-obe-uji',
            'module' => 'siakad',
            'permission_id' => $permission->id,
            'order_index' => 901,
            'is_active' => true,
        ]);
        $unplotted->roles()->attach($this->role->id);

        $this->lmsMenu = Menu::create([
            'name' => 'Menu LMS Uji',
            'url' => '/lms/uji',
            'module' => 'lms',
            'permission_id' => $permission->id,
            'order_index' => 902,
            'is_active' => true,
        ]);
        $this->lmsMenu->roles()->attach($this->role->id);

        // Modul SIPPM hanya terlihat lewat plotting OBE (parent & child tidak
        // di-mapping ke role user), seperti kondisi pada database lokal.
        $sippmPermission = Permission::where('slug', 'sippm.dashboard.read')->firstOrFail();
        $otherRole = Role::create([
            'name' => 'Role SIPPM Lain',
            'slug' => 'role_sippm_lain',
            'is_active' => true,
        ]);

        $sippmRoot = Menu::create([
            'name' => 'Menu SIPPM Uji',
            'url' => '#sippm_uji',
            'module' => 'sippm',
            'permission_id' => $sippmPermission->id,
            'order_index' => 903,
            'is_active' => true,
        ]);
        $sippmRoot->roles()->attach($otherRole->id);

        $sippmChild = Menu::create([
            'parent_id' => $sippmRoot->id,
            'name' => 'Rubrik SIPPM Uji',
            'url' => '/sippm/rubrik-uji',
            'module' => 'sippm',
            'permission_id' => $sippmPermission->id,
            'order_index' => 904,
            'is_active' => true,
        ]);
        $sippmChild->roles()->attach($otherRole->id);

        // Plotting OBE: menu SIAKAD & SIPPM. Menu LMS sengaja dikecualikan.
        DB::table('siakad_admin_obe_role_menus')->insert([
            ['role_id' => $this->role->id, 'menu_id' => $this->siakadMenu->id],
            ['role_id' => $this->role->id, 'menu_id' => $sippmChild->id],
        ]);
    }

    public function test_admin_obe_masih_melihat_modul_lms_di_module_switcher(): void
    {
        Passport::actingAs($this->user);

        $res = $this->getJson('/api/modules/my-modules');
        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['status', 'message', 'data']);

        $codes = collect($res->json('data'))->pluck('code')->all();

        $this->assertContains('siakad', $codes);
        $this->assertContains('lms', $codes);
        $this->assertContains('sippm', $codes);
    }

    public function test_pembatasan_menu_obe_tetap_berlaku_pada_modul_siakad(): void
    {
        Passport::actingAs($this->user);

        $siakad = $this->getJson('/api/menus/my-menus?module=siakad');
        $siakad->assertStatus(200)->assertJsonPath('status', 'success');
        $siakadUrls = collect($siakad->json('data'))->pluck('url')->all();

        $this->assertContains('/siakad/obe-uji', $siakadUrls);
        $this->assertNotContains('/siakad/non-obe-uji', $siakadUrls);

        $lms = $this->getJson('/api/menus/my-menus?module=lms');
        $lms->assertStatus(200)->assertJsonPath('status', 'success');
        $lmsUrls = collect($lms->json('data'))->pluck('url')->all();

        $this->assertContains('/lms/uji', $lmsUrls);
    }
}
