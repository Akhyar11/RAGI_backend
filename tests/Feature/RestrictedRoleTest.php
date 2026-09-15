<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestrictedRoleTest extends TestCase
{
    protected User $manager;
    protected User $viewer;
    protected Role $restrictedRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $readPermission = Permission::firstOrCreate(
            ['slug' => 'roles.read'],
            ['name' => 'Lihat Roles', 'module' => 'IAM', 'action' => 'read']
        );
        $createPermission = Permission::firstOrCreate(
            ['slug' => 'roles.create'],
            ['name' => 'Buat Role', 'module' => 'IAM', 'action' => 'create']
        );

        $managerRole = Role::firstOrCreate(
            ['slug' => 'pengelola_iam_test'],
            ['name' => 'Pengelola IAM Test', 'description' => 'Role pengelola untuk pengujian', 'is_active' => true]
        );
        $managerRole->permissions()->sync([$readPermission->id, $createPermission->id]);

        $viewerRole = Role::firstOrCreate(
            ['slug' => 'penampil_role_test'],
            ['name' => 'Penampil Role Test', 'description' => 'Role read-only untuk pengujian', 'is_active' => true]
        );
        $viewerRole->permissions()->sync([$readPermission->id]);

        $this->restrictedRole = Role::firstOrCreate(
            ['slug' => 'role_terbatas_test'],
            ['name' => 'Role Terbatas Test', 'description' => 'Role yang direstriksi untuk pengujian', 'is_active' => true]
        );

        $this->manager = User::firstOrCreate(
            ['email' => 'manager.restricted@kampus.ac.id'],
            ['username' => 'managerrestricted', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
        $this->manager->roles()->syncWithoutDetaching([$managerRole->id]);

        $this->viewer = User::firstOrCreate(
            ['email' => 'viewer.restricted@kampus.ac.id'],
            ['username' => 'viewerrestricted', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
        $this->viewer->roles()->syncWithoutDetaching([$viewerRole->id]);

        SystemSetting::set('restricted_role_ids', json_encode([$this->restrictedRole->id]));
    }

    protected function tokenFor(User $user): string
    {
        $result = $user->createToken('test-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokenFor($user)];
    }

    public function test_viewer_tidak_melihat_restricted_roles(): void
    {
        $response = $this->withHeaders($this->authHeader($this->viewer))
            ->getJson('/api/admin/roles?per_page=100');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta', 'filters'])
            ->assertJsonPath('filters.restricted_roles_excluded', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->restrictedRole->id, $ids);
    }

    public function test_pengelola_iam_tetap_melihat_semua_roles(): void
    {
        $response = $this->withHeaders($this->authHeader($this->manager))
            ->getJson('/api/admin/roles?per_page=100');

        $response->assertStatus(200)
            ->assertJsonPath('filters.restricted_roles_excluded', false);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->restrictedRole->id, $ids);
    }

    public function test_update_setting_restricted_roles_menyimpan_hanya_id_valid(): void
    {
        $extraRole = Role::firstOrCreate(
            ['slug' => 'role_terbatas_dua_test'],
            ['name' => 'Role Terbatas Dua Test', 'is_active' => true]
        );

        $response = $this->withHeaders($this->authHeader($this->manager))
            ->postJson('/api/admin/system-settings', [
                'settings' => [
                    ['key' => 'restricted_role_ids', 'value' => json_encode([$this->restrictedRole->id, $extraRole->id, 999999])],
                ],
            ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('core_system_settings', [
            'key' => 'restricted_role_ids',
            'value' => json_encode([$this->restrictedRole->id, $extraRole->id]),
        ]);
    }

    public function test_update_setting_format_bukan_json_array_ditolak(): void
    {
        $response = $this->withHeaders($this->authHeader($this->manager))
            ->postJson('/api/admin/system-settings', [
                'settings' => [
                    ['key' => 'restricted_role_ids', 'value' => 'bukan-json'],
                ],
            ]);

        $response->assertStatus(422)->assertJsonPath('status', 'error');
    }
}
