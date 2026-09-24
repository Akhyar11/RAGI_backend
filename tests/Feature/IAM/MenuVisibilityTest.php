<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        $permission = Permission::create([
            'name' => 'Kelola Menu Uji',
            'slug' => 'test.menu.manage',
            'module' => 'testmod',
            'action' => 'update',
        ]);

        $role = Role::create(['name' => 'Role Uji', 'slug' => 'role_uji', 'is_active' => true]);
        $otherRole = Role::create(['name' => 'Role Lain', 'slug' => 'role_lain', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        // User id=1 dianggap superadmin oleh fallback User::isSuperAdmin(),
        // jadi user uji harus BUKAN user pertama agar filter berlaku.
        User::factory()->create(['username' => 'dummy_satu']);
        $this->user = User::factory()->create(['username' => 'menu_uji']);
        $this->user->roles()->attach($role->id);

        $group = Menu::create([
            'name' => 'Grup Uji', 'url' => '#grp_test', 'module' => 'testmod',
            'permission_id' => null, 'order_index' => 1, 'is_active' => true,
        ]);

        $child = function (string $name, string $url, ?int $permissionId, int $order) use ($group) {
            return Menu::create([
                'parent_id' => $group->id, 'name' => $name, 'url' => $url,
                'module' => 'testmod', 'permission_id' => $permissionId,
                'order_index' => $order, 'is_active' => true,
            ]);
        };

        // A: permission cocok + pivot ke role sendiri -> terlihat.
        $child('Menu A Ter-mapping', '/testmod/a', $permission->id, 1)->roles()->attach($role->id);
        // B: permission cocok + pivot HANYA ke role lain -> disembunyikan (kasus admin_spmb).
        $child('Menu B Dimatikan', '/testmod/b', $permission->id, 2)->roles()->attach($otherRole->id);
        // C: permission cocok + tanpa pivot -> tetap terlihat (kompatibilitas mundur).
        $child('Menu C Tanpa Mapping', '/testmod/c', $permission->id, 3);
        // D: publik (tanpa permission, tanpa pivot) -> tetap terlihat.
        $child('Menu D Publik', '/testmod/d', null, 4);
        // E: tanpa permission + pivot ke role sendiri -> terlihat.
        $child('Menu E Role Saja', '/testmod/e', null, 5)->roles()->attach($role->id);
        // F: tanpa permission + pivot HANYA ke role lain -> disembunyikan.
        $child('Menu F Role Lain', '/testmod/f', null, 6)->roles()->attach($otherRole->id);
    }

    private function visibleUrls(): array
    {
        Passport::actingAs($this->user);
        $res = $this->getJson('/api/menus/my-menus?module=testmod');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $urls = [];
        foreach ($res->json('data') as $menu) {
            if (!empty($menu['url']) && !str_starts_with($menu['url'], '#')) {
                $urls[] = $menu['url'];
            }
            foreach ($menu['children'] ?? [] as $child) {
                $urls[] = $child['url'];
            }
        }

        return $urls;
    }

    public function test_unmapped_permission_menu_is_hidden_but_others_stay(): void
    {
        $urls = $this->visibleUrls();

        $this->assertContains('/testmod/a', $urls);
        $this->assertNotContains('/testmod/b', $urls);
        $this->assertContains('/testmod/c', $urls);
        $this->assertContains('/testmod/d', $urls);
        $this->assertContains('/testmod/e', $urls);
        $this->assertNotContains('/testmod/f', $urls);
    }

    public function test_remapping_menu_restores_visibility(): void
    {
        $this->assertNotContains('/testmod/b', $this->visibleUrls());

        $menuB = Menu::where('url', '/testmod/b')->firstOrFail();
        $roleId = $this->user->roles()->pluck('core_roles.id')->first();
        $menuB->roles()->attach($roleId);

        $this->assertContains('/testmod/b', $this->visibleUrls());
    }

    public function test_iam_baseline_menus_always_visible_without_role_mapping(): void
    {
        // Menu dasar IAM/SSO sengaja TIDAK di-attach ke role mana pun.
        Menu::create([
            'name' => 'Dashboard Utama', 'url' => '/dashboard', 'module' => 'sso',
            'permission_id' => null, 'order_index' => 1, 'is_active' => true,
        ]);

        $group = Menu::create([
            'name' => 'AKUN & KEAMANAN', 'url' => '#akun_keamanan', 'module' => 'sso',
            'permission_id' => null, 'order_index' => 999, 'is_active' => true,
        ]);

        $baselineChildren = [
            'Profil Saya' => '/profile',
            'Sesi Perangkat' => '/profile/sessions',
            'Autentikasi 2FA' => '/profile/mfa',
        ];

        $order = 1;
        foreach ($baselineChildren as $name => $url) {
            Menu::create([
                'parent_id' => $group->id, 'name' => $name, 'url' => $url,
                'module' => 'sso', 'permission_id' => null,
                'order_index' => $order++, 'is_active' => true,
            ]);
        }

        Passport::actingAs($this->user);
        $res = $this->getJson('/api/menus/my-menus?module=sso');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $urls = [];
        foreach ($res->json('data') as $menu) {
            if (!empty($menu['url']) && !str_starts_with($menu['url'], '#')) {
                $urls[] = $menu['url'];
            }
            foreach ($menu['children'] ?? [] as $child) {
                $urls[] = $child['url'];
            }
        }

        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/profile', $urls);
        $this->assertContains('/profile/sessions', $urls);
        $this->assertContains('/profile/mfa', $urls);
    }
}
