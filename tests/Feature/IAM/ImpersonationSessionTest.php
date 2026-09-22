<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Models\User;
use App\Models\ImpersonationSession;

class ImpersonationSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $targetA;

    private User $targetB;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        // User pertama (id=1) dianggap superadmin oleh fallback User::isSuperAdmin().
        $this->admin = User::factory()->create(['username' => 'admin_root']);
        $this->targetA = User::factory()->create(['username' => 'target_a']);
        $this->targetB = User::factory()->create(['username' => 'target_b']);
    }

    private function bearerToken(User $user, string $name = 'test-token'): string
    {
        $result = $user->createToken($name);

        return $result->plainTextToken ?? $result->accessToken;
    }

    /**
     * Guard auth Passport di-cache per instance aplikasi, sehingga setiap
     * request yang memakai token berbeda WAJIB reset guard dulu.
     * (Di produksi tiap request adalah proses PHP terpisah jadi tidak ada isu ini.)
     */
    private function apiAs(string $token)
    {
        auth()->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    public function test_impersonate_creates_per_token_session_and_status(): void
    {
        $adminToken = $this->bearerToken($this->admin, 'admin-device-1');

        $res = $this->apiAs($adminToken)
            ->postJson("/api/admin/users/{$this->targetA->id}/impersonate");

        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $impersonationToken = $res->json('data.access_token') ?? $res->json('data.token');
        $this->assertNotEmpty($impersonationToken);

        $this->assertDatabaseHas('core_impersonation_sessions', [
            'admin_id' => $this->admin->id,
            'target_user_id' => $this->targetA->id,
        ]);

        // Status dari token impersonasi harus true ...
        $status = $this->apiAs($impersonationToken)
            ->getJson('/api/admin/impersonate-status');
        $status->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_impersonating', true)
            ->assertJsonPath('data.impersonated_by.id', $this->admin->id);

        // ... sedangkan token admin asli tetap false.
        $adminStatus = $this->apiAs($adminToken)
            ->getJson('/api/admin/impersonate-status');
        $adminStatus->assertStatus(200)->assertJsonPath('data.is_impersonating', false);
    }

    public function test_two_devices_same_admin_stay_independent(): void
    {
        // Simulasi 2 device: 2 token admin berbeda.
        $adminToken1 = $this->bearerToken($this->admin, 'admin-device-1');
        $adminToken2 = $this->bearerToken($this->admin, 'admin-device-2');

        $resA = $this->apiAs($adminToken1)
            ->postJson("/api/admin/users/{$this->targetA->id}/impersonate");
        $resA->assertStatus(200);
        $tokenA = $resA->json('data.access_token') ?? $resA->json('data.token');

        $resB = $this->apiAs($adminToken2)
            ->postJson("/api/admin/users/{$this->targetB->id}/impersonate");
        $resB->assertStatus(200);
        $tokenB = $resB->json('data.access_token') ?? $resB->json('data.token');

        $this->assertNotEquals($tokenA, $tokenB);
        $this->assertEquals(2, ImpersonationSession::whereNull('ended_at')->count());

        // Kedua device sama-sama terdeteksi merasuki.
        $this->apiAs($tokenA)
            ->getJson('/api/admin/impersonate-status')
            ->assertStatus(200)
            ->assertJsonPath('data.is_impersonating', true)
            ->assertJsonPath('data.impersonated_by.id', $this->admin->id);

        $this->apiAs($tokenB)
            ->getJson('/api/admin/impersonate-status')
            ->assertStatus(200)
            ->assertJsonPath('data.is_impersonating', true)
            ->assertJsonPath('data.impersonated_by.id', $this->admin->id);

        // Keluar dari device A: device B tidak ikut tertutup.
        $leaveA = $this->apiAs($tokenA)
            ->postJson('/api/admin/users/leave-impersonate');
        $leaveA->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertNotEmpty($leaveA->json('data.access_token') ?? $leaveA->json('data.token'));

        $this->assertEquals(1, ImpersonationSession::whereNull('ended_at')->count());
        $this->assertEquals(1, ImpersonationSession::whereNotNull('ended_at')->count());

        // Token A sudah dihapus -> 401; token B masih aktif.
        $this->apiAs($tokenA)
            ->getJson('/api/admin/impersonate-status')
            ->assertStatus(401);

        $this->apiAs($tokenB)
            ->getJson('/api/admin/impersonate-status')
            ->assertStatus(200)
            ->assertJsonPath('data.is_impersonating', true);
    }

    public function test_leave_without_impersonation_is_rejected(): void
    {
        $plainToken = $this->bearerToken($this->targetA, 'plain-login');

        $this->apiAs($plainToken)
            ->postJson('/api/admin/users/leave-impersonate')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }
}
