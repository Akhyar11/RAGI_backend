<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email'     => 'user@kampus.ac.id',
            'is_active' => true,
        ]);
    }

    public function test_forgot_password_returns_success_for_valid_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@kampus.ac.id',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_resets', [
            'user_id'  => $this->user->id,
            'is_used'  => false,
        ]);
    }

    public function test_forgot_password_returns_success_even_for_unknown_email()
    {
        // Mencegah email enumeration attack
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'tidakada@kampus.ac.id',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');
    }

    public function test_reset_password_with_valid_token()
    {
        // Buat token reset secara langsung
        $plainToken = 'valid-reset-token-abc';
        PasswordReset::create([
            'user_id'    => $this->user->id,
            'token'      => Hash::make($plainToken),
            'expires_at' => now()->addMinutes(60),
            'is_used'    => false,
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@kampus.ac.id',
            'token'                 => $plainToken,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        // Token harus ditandai is_used
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $this->user->id,
            'is_used' => true,
        ]);
    }

    public function test_reset_password_with_invalid_token_returns_422()
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@kampus.ac.id',
            'token'                 => 'token-salah',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    public function test_reset_password_with_expired_token_returns_422()
    {
        $plainToken = 'expired-reset-token';
        PasswordReset::create([
            'user_id'    => $this->user->id,
            'token'      => Hash::make($plainToken),
            'expires_at' => now()->subMinutes(1), // Sudah lewat
            'is_used'    => false,
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@kampus.ac.id',
            'token'                 => $plainToken,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }
}
