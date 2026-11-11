<?php

namespace Tests\Feature\IAM;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IAM\SpmbAdminRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbAdminNotificationMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $iamAdmin;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iamAdmin = User::factory()->create();
        $role = Role::create(['slug' => fake()->unique()->slug(), 'name' => fake()->unique()->sentence(3), 'is_active' => true]);
        $permission = Permission::firstOrCreate(['slug' => 'iam.settings.update'], [
            'name' => 'Kelola Pengaturan Sistem', 'module' => 'iam', 'action' => 'update',
        ]);
        $role->permissions()->attach($permission->id);
        $this->iamAdmin->roles()->attach($role->id);
        $this->recipient = User::factory()->create();
    }

    public function test_default_mapping_is_empty_and_returns_recipient_metadata(): void
    {
        Passport::actingAs($this->iamAdmin);
        $this->getJson('/api/admin/system-settings')->assertOk()
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.value', '[]')
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.users', []);
        $this->assertDatabaseMissing('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY]);
    }

    public function test_iam_permission_can_save_read_and_clear_mapping_without_a_fixed_admin_role(): void
    {
        Passport::actingAs($this->iamAdmin);
        $encoded = json_encode([$this->recipient->id]);
        $this->postJson('/api/admin/system-settings', $this->payload($encoded))->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY, 'value' => $encoded]);
        $this->getJson('/api/admin/system-settings')->assertOk()
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.users.0.id', $this->recipient->id)
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.users.0.email', $this->recipient->email);
        $this->assertDatabaseHas('core_audit_logs', ['user_id' => $this->iamAdmin->id, 'action' => 'update_system_setting', 'table_name' => 'core_system_settings']);
        $this->postJson('/api/admin/system-settings', $this->payload('[]'))->assertOk();
        $this->assertDatabaseHas('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY, 'value' => '[]']);
    }

    public function test_duplicate_ids_are_normalized_before_storage(): void
    {
        Passport::actingAs($this->iamAdmin);
        $this->postJson('/api/admin/system-settings', $this->payload(json_encode([$this->recipient->id, $this->recipient->id])))->assertOk();
        $this->assertDatabaseHas('core_system_settings', [
            'key' => SpmbAdminRecipientService::SETTING_KEY, 'value' => json_encode([$this->recipient->id]),
        ]);
        $this->getJson('/api/admin/system-settings')->assertOk()
            ->assertJsonCount(1, 'data.'.SpmbAdminRecipientService::SETTING_KEY.'.users');
    }

    public function test_invalid_mapping_is_rejected_before_any_settings_are_written(): void
    {
        Passport::actingAs($this->iamAdmin);
        $original = json_encode([$this->recipient->id]);
        SystemSetting::set(SpmbAdminRecipientService::SETTING_KEY, $original);
        $inactive = User::factory()->create(['is_active' => false]);
        $invalidValues = ['invalid-json', '{}', 'null', '"text"', '[true]', '[0]', '[-1]', '[[]]', '[{}]',
            json_encode([(string) $this->recipient->id]), json_encode([$inactive->id]),
            json_encode([(int) User::max('id') + 1000]), json_encode(array_fill(0, 101, $this->recipient->id))];
        foreach ($invalidValues as $value) {
            $payload = $this->payload($value);
            array_unshift($payload['settings'], ['key' => 'spmb_mapping_validation_marker', 'value' => 'must-not-be-written']);
            $this->postJson('/api/admin/system-settings', $payload)->assertUnprocessable()
                ->assertJsonValidationErrors('settings.1.value');
            $this->assertDatabaseMissing('core_system_settings', ['key' => 'spmb_mapping_validation_marker']);
            $this->assertDatabaseHas('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY, 'value' => $original]);
        }
    }

    public function test_only_authorized_iam_users_can_read_edit_or_search_mapping(): void
    {
        $this->getJson('/api/admin/system-settings')->assertUnauthorized();
        $this->postJson('/api/admin/system-settings', $this->payload('[]'))->assertUnauthorized();
        $this->getJson('/api/admin/system-settings/spmb-admin-options')->assertUnauthorized();
        Passport::actingAs($this->recipient);
        $this->getJson('/api/admin/system-settings')->assertForbidden();
        $this->postJson('/api/admin/system-settings', $this->payload('[]'))->assertForbidden();
        $this->getJson('/api/admin/system-settings/spmb-admin-options')->assertForbidden();
        $this->assertDatabaseMissing('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY]);
    }

    public function test_options_search_active_users_with_pagination_and_sorting(): void
    {
        Passport::actingAs($this->iamAdmin);
        User::factory()->create(['username' => 'search.recipient.active', 'email' => 'active.recipient@example.test']);
        User::factory()->create(['username' => 'search.recipient.inactive', 'is_active' => false]);
        $this->getJson('/api/admin/system-settings/spmb-admin-options?search=search.recipient&per_page=1&sort_by=username&sort_order=asc')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.username', 'search.recipient.active')
            ->assertJsonStructure(['status', 'data' => [['id', 'name', 'username', 'email', 'is_active']], 'meta', 'filters']);
        $this->getJson('/api/admin/system-settings/spmb-admin-options?search=active.recipient%40example.test')
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/system-settings/spmb-admin-options?per_page=200')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/admin/system-settings/spmb-admin-options?sort_by=unknown')->assertUnprocessable();
    }

    public function test_role_manager_cannot_use_restricted_role_access_to_change_spmb_mapping(): void
    {
        $role = Role::create(['slug' => fake()->unique()->slug(), 'name' => fake()->unique()->sentence(3), 'is_active' => true]);
        $permission = Permission::firstOrCreate(['slug' => 'iam.roles.create'], ['name' => 'Buat Role', 'module' => 'iam', 'action' => 'create']);
        $role->permissions()->attach($permission->id);
        $this->recipient->roles()->attach($role->id);
        Passport::actingAs($this->recipient);
        $payload = $this->payload(json_encode([$this->recipient->id]));
        array_unshift($payload['settings'], ['key' => 'restricted_role_ids', 'value' => '[]']);
        $this->postJson('/api/admin/system-settings', $payload)->assertForbidden();
        $this->assertDatabaseMissing('core_system_settings', ['key' => SpmbAdminRecipientService::SETTING_KEY]);
    }

    public function test_selected_user_metadata_remains_available_after_deactivation(): void
    {
        Passport::actingAs($this->iamAdmin);
        $this->postJson('/api/admin/system-settings', $this->payload(json_encode([$this->recipient->id])))->assertOk();
        $this->recipient->update(['is_active' => false]);
        $this->getJson('/api/admin/system-settings')->assertOk()
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.users.0.id', $this->recipient->id)
            ->assertJsonPath('data.'.SpmbAdminRecipientService::SETTING_KEY.'.users.0.is_active', false);
        $this->getJson('/api/admin/system-settings/spmb-admin-options?search='.urlencode($this->recipient->email))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    private function payload(string $value): array
    {
        return ['settings' => [['key' => SpmbAdminRecipientService::SETTING_KEY, 'value' => $value]]];
    }
}
