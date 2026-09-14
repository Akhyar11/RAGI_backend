<?php

namespace Tests\Feature\IAM;

use App\Mail\PasswordResetMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\IAM\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_email_to_registered_active_user(): void
    {
        Mail::fake();

        $user = User::create([
            'username'    => 'testuser',
            'email'       => 'testuser@kampus.ac.id',
            'password'    => Hash::make('OldPassword123!'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'testuser@kampus.ac.id',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Jika email terdaftar, link reset password akan dikirimkan.',
            ]);

        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id,
            'is_used' => false,
        ]);

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        Mail::assertSent(PasswordResetMail::class, function ($mail) use ($user, $frontendUrl) {
            return $mail->hasTo($user->email)
                && str_starts_with($mail->resetUrl, $frontendUrl . '/reset-password')
                && str_contains($mail->resetUrl, 'token=')
                && str_contains($mail->resetUrl, 'email=' . urlencode($user->email));
        });
    }

    public function test_forgot_password_returns_200_without_sending_email_for_unknown_user(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@kampus.ac.id',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Jika email terdaftar, link reset password akan dikirimkan.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token_and_email(): void
    {
        $user = User::create([
            'username'    => 'resetuser',
            'email'       => 'resetuser@kampus.ac.id',
            'password'    => Hash::make('OldPassword123!'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        $service = app(PasswordResetService::class);
        $plainToken = $service->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $plainToken,
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Password berhasil diperbarui. Silakan login kembali.',
            ]);

        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id,
            'is_used' => true,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));
    }

    public function test_reset_password_fails_if_email_is_missing(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'token'                 => 'sometoken',
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::create([
            'username'    => 'invalidtokenuser',
            'email'       => 'invalidtoken@kampus.ac.id',
            'password'    => Hash::make('OldPassword123!'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => 'invalid-token-value',
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Token tidak valid, sudah digunakan, atau telah kedaluwarsa.',
            ]);
    }

    public function test_reset_password_fails_if_token_already_used(): void
    {
        $user = User::create([
            'username'    => 'usedtokenuser',
            'email'       => 'usedtoken@kampus.ac.id',
            'password'    => Hash::make('OldPassword123!'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        $service = app(PasswordResetService::class);
        $plainToken = $service->createToken($user);

        // Tandai sudah dipakai
        PasswordReset::where('user_id', $user->id)->update(['is_used' => true]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $plainToken,
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Token tidak valid, sudah digunakan, atau telah kedaluwarsa.',
            ]);
    }
}
