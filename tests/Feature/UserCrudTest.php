<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->admin = User::factory()->create(['user_type' => 'admin']);
        $this->nonAdmin = User::factory()->create(['user_type' => 'mahasiswa']);
    }

    public function test_admin_can_list_users()
    {
        User::factory()->count(5)->create();
        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_non_admin_cannot_list_users()
    {
        Passport::actingAs($this->nonAdmin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user()
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'username'              => 'newuser',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone'                 => '0812345678',
            'user_type'             => 'dosen',
            'is_active'             => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username'  => 'newuser',
            'email'     => 'newuser@example.com',
            'user_type' => 'dosen',
        ]);
    }

    public function test_admin_can_update_user()
    {
        Passport::actingAs($this->admin);
        $userToUpdate = User::factory()->create();

        $response = $this->putJson('/api/users/' . $userToUpdate->id, [
            'username'  => 'updateduser',
            'user_type' => 'tendik',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id'        => $userToUpdate->id,
            'username'  => 'updateduser',
            'user_type' => 'tendik',
        ]);
    }

    public function test_admin_can_delete_user()
    {
        Passport::actingAs($this->admin);
        $userToDelete = User::factory()->create();

        $response = $this->deleteJson('/api/users/' . $userToDelete->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', [
            'id'         => $userToDelete->id,
            'deleted_at' => null,
        ]);
    }
}
