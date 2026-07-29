<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Passport\Passport;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    private function createAuthorizedUser()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role->id);

        // Beri permission secara manual via DB query untuk menghindari masalah seeder/factory
        $permissions = ['roles.read', 'roles.create', 'roles.update', 'roles.delete'];
        foreach ($permissions as $slug) {
            $perm = Permission::factory()->create(['slug' => $slug]);
            $role->permissions()->attach($perm->id);
        }

        return $user;
    }

    public function test_unauthenticated_user_cannot_access_roles()
    {
        $response = $this->getJson('/api/roles');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_roles()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->getJson('/api/roles');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_list_roles()
    {
        $user = $this->createAuthorizedUser();
        Passport::actingAs($user);

        Role::factory()->count(3)->create();

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         '*' => ['id', 'name', 'slug', 'description', 'is_active']
                     ],
                     'meta' => ['current_page', 'per_page', 'total'],
                     'filters' => ['search', 'sort_by', 'sort_order']
                 ]);
    }

    public function test_authorized_user_can_create_role()
    {
        $user = $this->createAuthorizedUser();
        Passport::actingAs($user);

        $permission = Permission::factory()->create();

        $payload = [
            'name' => 'Test Role Baru',
            'slug' => 'test-role-baru',
            'description' => 'Deskripsi test',
            'is_active' => true,
            'permissions' => [$permission->id]
        ];

        $response = $this->postJson('/api/roles', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.name', 'Test Role Baru');

        $this->assertDatabaseHas('roles', [
            'slug' => 'test-role-baru'
        ]);

        $roleId = $response->json('data.id');
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permission->id
        ]);
    }

    public function test_authorized_user_can_update_role()
    {
        $user = $this->createAuthorizedUser();
        Passport::actingAs($user);

        $role = Role::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
        
        $payload = [
            'name' => 'New Name',
            'slug' => 'old-name',
        ];

        $response = $this->putJson("/api/roles/{$role->id}", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'New Name'
        ]);
    }

    public function test_authorized_user_can_delete_role()
    {
        $user = $this->createAuthorizedUser();
        Passport::actingAs($user);

        $role = Role::factory()->create();

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('roles', [
            'id' => $role->id
        ]);
    }

    public function test_cannot_delete_super_admin_role()
    {
        $user = $this->createAuthorizedUser();
        Passport::actingAs($user);

        $role = Role::factory()->create(['slug' => 'super-admin']);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(403); // Forbidden dari Policy
        $this->assertDatabaseHas('roles', [
            'id' => $role->id
        ]);
    }
}
