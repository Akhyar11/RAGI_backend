<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    public function test_observer_records_audit_log_on_create_update_delete()
    {
        // Pura-puranya kita sedang login sebagai seseorang
        $admin = User::factory()->create();
        $this->actingAs($admin, 'api');

        // Test Create
        $role = Role::create([
            'name' => 'Role Testing Audit',
            'slug' => 'role-testing-audit',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'IAM',
            'action' => 'create',
            'table_name' => 'roles',
            'record_id' => $role->id,
            'user_id' => $admin->id
        ]);

        // Test Update
        $role->update(['name' => 'Role Testing Updated']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'IAM',
            'action' => 'update',
            'table_name' => 'roles',
            'record_id' => $role->id,
            'user_id' => $admin->id
        ]);

        // Test Delete
        $role->delete();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'IAM',
            'action' => 'delete',
            'table_name' => 'roles',
            'record_id' => $role->id,
            'user_id' => $admin->id
        ]);
    }

    public function test_observer_sanitizes_password_on_user_update()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password')
        ]);

        $user->update([
            'password' => Hash::make('new_password')
        ]);

        $log = AuditLog::where('table_name', 'users')
            ->where('record_id', $user->id)
            ->where('action', 'update')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('********', $log->old_values['password']);
        $this->assertEquals('********', $log->new_values['password']);
    }

    public function test_auth_controller_records_login_success()
    {
        $user = User::factory()->create([
            'email' => 'testlogin@kampus.ac.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'testlogin@kampus.ac.id',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'IAM',
            'action' => 'login',
            'table_name' => 'users',
            'record_id' => $user->id
        ]);
    }

    public function test_auth_controller_records_login_failed()
    {
        $user = User::factory()->create([
            'email' => 'testfailed@kampus.ac.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'testfailed@kampus.ac.id',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'IAM',
            'action' => 'login_failed',
            'table_name' => 'users',
            'record_id' => $user->id
        ]);
        
        $log = AuditLog::where('action', 'login_failed')->first();
        $this->assertEquals('testfailed@kampus.ac.id', $log->new_values['email']);
    }

    public function test_admin_can_view_audit_logs()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $role = Role::factory()->create();
        $permission = \App\Models\Permission::factory()->create(['slug' => 'view-audit-logs']);
        $role->permissions()->attach($permission->id);
        $admin->roles()->attach($role->id);

        $tokenStr = $admin->createToken('admin_token')->accessToken ?? $admin->createToken('admin_token')->plainTextToken;
        
        AuditLog::create([
            'module' => 'IAM',
            'action' => 'dummy',
            'table_name' => 'dummy'
        ]);

        $response = $this->withToken($tokenStr)->getJson('/api/admin/audit-logs');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta', 'filters']);
    }

    public function test_non_admin_cannot_view_audit_logs()
    {
        $mahasiswa = User::factory()->create(['user_type' => 'mahasiswa']);
        $token = $mahasiswa->createToken('mhs_token')->accessToken ?? clone $mahasiswa->createToken('mhs_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/audit-logs');

        // Should be forbidden because no view-audit-logs permission
        $response->assertStatus(403);
    }
}
