<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class MyModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        // Modul Uji
        Module::create(['name' => 'Modul A', 'code' => 'mod_a', 'is_active' => true]);
        Module::create(['name' => 'Modul B', 'code' => 'mod_b', 'is_active' => true]);
        Module::create(['name' => 'Modul Nonaktif', 'code' => 'mod_c', 'is_active' => false]);
        Module::create(['name' => 'SSO Core', 'code' => 'sso', 'is_active' => true]);

        // Permission & Role untuk Modul A saja
        $permA = Permission::create([
            'name' => 'Akses Modul A',
            'slug' => 'mod_a.read',
            'module' => 'mod_a',
            'action' => 'read',
        ]);

        $roleUser = Role::create(['name' => 'User Role', 'slug' => 'user_role', 'is_active' => true]);
        $roleUser->permissions()->attach($permA->id);

        $roleSuper = Role::create(['name' => 'Superadmin', 'slug' => 'superadmin', 'is_active' => true]);

        $this->superadmin = User::factory()->create(['username' => 'super_user']);
        $this->superadmin->roles()->attach($roleSuper->id);

        $this->user = User::factory()->create(['username' => 'regular_user']);
        $this->user->roles()->attach($roleUser->id);

        // Menu Modul A
        $menuA = Menu::create([
            'name' => 'Menu Modul A',
            'url' => '/mod-a/index',
            'module' => 'mod_a',
            'permission_id' => $permA->id,
            'order_index' => 1,
            'is_active' => true,
        ]);
        $menuA->roles()->attach($roleUser->id);

        // Menu Modul B (Hanya untuk Superadmin / tanpa role user)
        Menu::create([
            'name' => 'Menu Modul B',
            'url' => '/mod-b/index',
            'module' => 'mod_b',
            'permission_id' => null,
            'order_index' => 1,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_cannot_access_my_modules(): void
    {
        $response = $this->getJson('/api/modules/my-modules');
        $response->assertStatus(401);
    }

    public function test_superadmin_gets_all_active_modules(): void
    {
        $this->actingAs($this->superadmin, 'api');

        $response = $this->getJson('/api/modules/my-modules');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('mod_a', $codes);
        $this->assertContains('mod_b', $codes);
        $this->assertContains('sso', $codes);
        $this->assertNotContains('mod_c', $codes); // Nonaktif tidak boleh muncul
    }

    public function test_regular_user_only_gets_modules_they_have_access_to(): void
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson('/api/modules/my-modules');
        $response->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('mod_a', $codes);
        $this->assertNotContains('mod_b', $codes); // Tidak punya menu di mod_b
        $this->assertNotContains('mod_c', $codes); // Nonaktif
    }
}
