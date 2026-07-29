<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Passport\Passport;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    public function test_authorized_user_can_list_permissions()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role->id);

        $perm = Permission::factory()->create(['slug' => 'roles.read']);
        $role->permissions()->attach($perm->id);

        Passport::actingAs($user);

        Permission::factory()->count(5)->create(['module' => 'TESTING']);

        $response = $this->getJson('/api/admin/permissions?module=TESTING');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         '*' => ['id', 'name', 'slug', 'module', 'action', 'description']
                     ]
                 ]);
                 
        $this->assertCount(5, $response->json('data'));
    }

    public function test_unauthorized_user_cannot_list_permissions()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->getJson('/api/admin/permissions');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_permission()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        Passport::actingAs($admin);

        $payload = [
            'module' => 'TEST',
            'action' => 'create',
            'name' => 'Test Create',
            'slug' => 'test.create',
            'description' => 'A test permission'
        ];

        $response = $this->postJson('/api/admin/permissions', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.slug', 'test.create');

        $this->assertDatabaseHas('permissions', ['slug' => 'test.create']);
    }

    public function test_admin_can_update_permission()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        Passport::actingAs($admin);

        $permission = Permission::factory()->create(['slug' => 'old.slug']);

        $payload = [
            'module' => 'TEST',
            'action' => 'update',
            'name' => 'Updated Name',
            'slug' => 'new.slug'
        ];

        $response = $this->putJson("/api/admin/permissions/{$permission->id}", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.slug', 'new.slug');

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'slug' => 'new.slug']);
    }

    public function test_admin_can_delete_permission()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        Passport::actingAs($admin);

        $permission = Permission::factory()->create();

        $response = $this->deleteJson("/api/admin/permissions/{$permission->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }
}
