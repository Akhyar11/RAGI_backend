<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    private function getAdminToken()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $role = Role::factory()->create();
        $permViewAnyRole = Permission::factory()->create(['slug' => 'roles.read']);
        $permUpdateRole = Permission::factory()->create(['slug' => 'roles.update']);
        $permCreateRole = Permission::factory()->create(['slug' => 'roles.create']);
        $role->permissions()->attach([$permViewAnyRole->id, $permUpdateRole->id, $permCreateRole->id]);
        $admin->roles()->attach($role->id);

        return $admin->createToken('admin_token')->accessToken ?? clone $admin->createToken('admin_token')->plainTextToken;
    }

    public function test_admin_can_assign_roles_to_user()
    {
        $token = $this->getAdminToken();
        $user = User::factory()->create();
        $role1 = Role::factory()->create();
        $role2 = Role::factory()->create();

        $response = $this->withToken($token)->postJson("/api/admin/users/{$user->id}/roles", [
            'roles' => [$role1->id, $role2->id]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role1->id
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role2->id
        ]);
    }

    public function test_admin_can_assign_permissions_to_role()
    {
        $token = $this->getAdminToken();
        $role = Role::factory()->create();
        $perm1 = Permission::factory()->create();
        $perm2 = Permission::factory()->create();

        $response = $this->withToken($token)->postJson("/api/admin/roles/{$role->id}/permissions", [
            'permissions' => [$perm1->id, $perm2->id]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $perm1->id
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $perm2->id
        ]);
    }

    public function test_admin_can_get_user_roles_mapping()
    {
        $token = $this->getAdminToken();
        
        $response = $this->withToken($token)->getJson("/api/admin/user-roles");

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [['roles']]]);
    }

    public function test_admin_can_get_role_permissions_mapping()
    {
        $token = $this->getAdminToken();
        
        $response = $this->withToken($token)->getJson("/api/admin/role-permissions");

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [['permissions']]]);
    }
}
