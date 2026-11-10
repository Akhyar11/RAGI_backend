<?php

namespace Tests\Feature\IAM;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LoginBannerSettingTest extends TestCase
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
    }

    public function test_public_branding_returns_default_settings_when_empty(): void
    {
        $response = $this->getJson('/api/auth/branding');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.system_logo_url', '')
            ->assertJsonPath('data.login_banner_url', '')
            ->assertJsonPath('data.login_banner_title', 'SSO Campus')
            ->assertJsonPath('data.login_banner_subtitle', 'Enterprise Identity Platform');
    }

    public function test_public_branding_returns_saved_custom_settings(): void
    {
        SystemSetting::set('system_logo_url', 'https://cdn.example.com/branding/logo.png');
        SystemSetting::set('login_banner_url', 'https://example.com/kampus-banner.jpg');
        SystemSetting::set('login_banner_title', 'Universitas Nusantara');
        SystemSetting::set('login_banner_subtitle', 'Satu Pintu Layanan Digital Terpadu');

        $response = $this->getJson('/api/auth/branding');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.system_logo_url', 'https://cdn.example.com/branding/logo.png')
            ->assertJsonPath('data.login_banner_url', 'https://example.com/kampus-banner.jpg')
            ->assertJsonPath('data.login_banner_title', 'Universitas Nusantara')
            ->assertJsonPath('data.login_banner_subtitle', 'Satu Pintu Layanan Digital Terpadu');
    }

    public function test_admin_can_save_login_banner_settings(): void
    {
        /** @var mixed $user */
        $user = $this->adminUser;
        Passport::actingAs($user);

        $payload = [
            'settings' => [
                ['key' => 'login_banner_url', 'value' => 'https://example.com/new-banner.jpg'],
                ['key' => 'login_banner_title', 'value' => 'Portal SSO Resmi'],
                ['key' => 'login_banner_subtitle', 'value' => 'Kampus Unggul & Berkarakter'],
            ],
        ];

        $response = $this->postJson('/api/admin/system-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertEquals('https://example.com/new-banner.jpg', SystemSetting::get('login_banner_url'));
        $this->assertEquals('Portal SSO Resmi', SystemSetting::get('login_banner_title'));
        $this->assertEquals('Kampus Unggul & Berkarakter', SystemSetting::get('login_banner_subtitle'));
    }

    public function test_admin_can_upload_login_banner_image(): void
    {
        /** @var mixed $user */
        $user = $this->adminUser;
        Passport::actingAs($user);
        Storage::fake('public');

        $file = UploadedFile::fake()->image('campus_banner.jpg', 1920, 1080);

        $response = $this->postJson('/api/admin/system-settings/upload-banner', [
            'banner' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['path', 'url'],
            ]);
    }

    public function test_admin_can_upload_and_remove_system_logo(): void
    {
        /** @var mixed $user */
        $user = $this->adminUser;
        Passport::actingAs($user);
        Storage::fake('public');

        $response = $this->postJson('/api/admin/system-settings/upload-logo', [
            'logo' => UploadedFile::fake()->image('system-logo.png', 300, 120),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['path', 'url']]);

        $path = $response->json('data.path');
        $this->assertSame($path, SystemSetting::get('system_logo_path'));
        $this->assertSame($response->json('data.url'), SystemSetting::get('system_logo_url'));
        Storage::disk('public')->assertExists($path);

        $this->getJson('/api/auth/branding')
            ->assertStatus(200)
            ->assertJsonPath('data.system_logo_url', $response->json('data.url'));

        $this->deleteJson('/api/admin/system-settings/logo')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', null);

        $this->assertSame('', SystemSetting::get('system_logo_path'));
        $this->assertSame('', SystemSetting::get('system_logo_url'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_system_logo_upload_rejects_unsupported_file_types(): void
    {
        /** @var mixed $user */
        $user = $this->adminUser;
        Passport::actingAs($user);
        Storage::fake('public');

        $this->postJson('/api/admin/system-settings/upload-logo', [
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertStatus(422);
    }
}
