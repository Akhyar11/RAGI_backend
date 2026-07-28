<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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
        
        $this->admin = User::factory()->create([
            'user_type' => 'admin',
        ]);
        
        $this->nonAdmin = User::factory()->create([
            'user_type' => 'mahasiswa',
        ]);
    }

    public function test_admin_can_list_users()
    {
        User::factory()->count(5)->create();
        
        $response = $this->actingAs($this->admin)->getJson('/api/users');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_non_admin_cannot_list_users()
    {
        $response = $this->actingAs($this->nonAdmin)->getJson('/api/users');
        
        $response->assertStatus(403);
    }

    public function test_admin_can_create_user()
    {
        $payload = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0812345678',
            'user_type' => 'dosen',
            'is_active' => true,
        ];
        
        $response = $this->actingAs($this->admin)->postJson('/api/users', $payload);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'user_type' => 'dosen',
        ]);
    }

    public function test_admin_can_update_user()
    {
        $userToUpdate = User::factory()->create();
        
        $payload = [
            'username' => 'updateduser',
            'user_type' => 'tendik',
        ];
        
        $response = $this->actingAs($this->admin)->putJson('/api/users/' . $userToUpdate->id, $payload);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $userToUpdate->id,
            'username' => 'updateduser',
            'user_type' => 'tendik',
        ]);
    }

    public function test_admin_can_delete_user()
    {
        $userToDelete = User::factory()->create();
        
        $response = $this->actingAs($this->admin)->deleteJson('/api/users/' . $userToDelete->id);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
            'deleted_at' => null
        ]);
    }
}
