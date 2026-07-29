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

        $response = $this->getJson('/api/permissions?module=TESTING');

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

        $response = $this->getJson('/api/permissions');
        $response->assertStatus(403);
    }
}
