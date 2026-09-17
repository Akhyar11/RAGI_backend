<?php

namespace Tests\Feature\IAM;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SystemSettingSmtpTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name'        => 'Super Admin',
            'slug'        => 'superadmin',
            'description' => 'Superadmin Role',
            'is_active'   => true,
        ]);

        $this->adminUser = User::create([
            'username'    => 'admin_test',
            'email'       => 'admin@kampus.ac.id',
            'password'    => Hash::make('password123'),
            'is_active'   => true,
            'is_verified' => true,
        ]);

        $this->adminUser->roles()->attach($adminRole->id);

        Passport::actingAs($this->adminUser);
    }

    public function test_system_settings_index_returns_smtp_defaults_when_empty(): void
    {
        $response = $this->getJson('/api/admin/system-settings');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'mail_host',
                    'mail_port',
                    'mail_username',
                    'mail_password',
                    'mail_from_address',
                    'mail_from_name',
                ],
            ]);
    }

    public function test_admin_can_update_smtp_settings(): void
    {
        $payload = [
            'settings' => [
                ['key' => 'mail_host', 'value' => 'smtp.mailtrap.io'],
                ['key' => 'mail_port', 'value' => '2525'],
                ['key' => 'mail_username', 'value' => 'mailtrap_user'],
                ['key' => 'mail_password', 'value' => 'mailtrap_pass'],
                ['key' => 'mail_scheme', 'value' => 'tls'],
                ['key' => 'mail_from_address', 'value' => 'noreply@kampus.ac.id'],
                ['key' => 'mail_from_name', 'value' => 'Kampus Digital'],
            ],
        ];

        $response = $this->postJson('/api/admin/system-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('core_system_settings', [
            'key'   => 'mail_host',
            'value' => 'smtp.mailtrap.io',
        ]);
        $this->assertDatabaseHas('core_system_settings', [
            'key'   => 'mail_port',
            'value' => '2525',
        ]);
        $this->assertDatabaseHas('core_system_settings', [
            'key'   => 'mail_from_name',
            'value' => 'Kampus Digital',
        ]);
    }

    public function test_admin_can_test_smtp_sending(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/admin/system-settings/test-smtp', [
            'email'             => 'destination@test.com',
            'mail_host'         => 'smtp.test.com',
            'mail_port'         => 587,
            'mail_username'     => 'testuser',
            'mail_password'     => 'testsecret',
            'mail_scheme'       => 'tls',
            'mail_from_address' => 'sender@test.com',
            'mail_from_name'    => 'Test Sender',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Email uji coba berhasil dikirim ke destination@test.com',
            ]);
    }

    public function test_test_smtp_fails_validation_without_email(): void
    {
        $response = $this->postJson('/api/admin/system-settings/test-smtp', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_system_settings_index_returns_r2_defaults(): void
    {
        $response = $this->getJson('/api/admin/system-settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'filesystem_disk',
                    'filesystem_public_disk',
                    'filesystem_private_disk',
                    'r2_access_key_id',
                    'r2_secret_access_key',
                    'r2_bucket',
                    'r2_endpoint',
                ],
            ]);
    }

    public function test_admin_can_update_r2_settings(): void
    {
        $payload = [
            'settings' => [
                ['key' => 'filesystem_disk', 'value' => 'r2'],
                ['key' => 'r2_bucket', 'value' => 'my-r2-bucket'],
                ['key' => 'r2_endpoint', 'value' => 'https://accountid.r2.cloudflarestorage.com'],
            ],
        ];

        $response = $this->postJson('/api/admin/system-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('core_system_settings', [
            'key'   => 'filesystem_disk',
            'value' => 'r2',
        ]);
        $this->assertDatabaseHas('core_system_settings', [
            'key'   => 'r2_bucket',
            'value' => 'my-r2-bucket',
        ]);
    }
}

