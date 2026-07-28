<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'mahasiswa',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['access_token']);
                 
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com'
        ]);
    }
    
    public function test_user_can_login()
    {
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token']);
    }
}
