<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserSessionIam;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;

class UserSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    public function test_authorized_user_can_list_active_sessions()
    {
        $user = User::factory()->create();
        
        // Buat mock session di database (pura-pura login)
        $token1 = $user->createToken('auth_token_1');
        UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $token1->token->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser/1.0',
            'created_at' => now(),
        ]);

        $token2 = $user->createToken('auth_token_2');
        UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $token2->token->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'AnotherBrowser/2.0',
            'created_at' => now(),
        ]);

        $token1Str = $token1->accessToken ?? $token1->plainTextToken;
        $token2Str = $token2->accessToken ?? $token2->plainTextToken;

        $response = $this->withToken($token2Str)->getJson('/api/auth/sessions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         '*' => ['id', 'user_id', 'token', 'ip_address', 'user_agent', 'expires_at', 'created_at']
                     ],
                     'meta' => ['current_page', 'per_page', 'total'],
                     'filters'
                 ]);
        
        // Assert total sessions
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_user_can_revoke_specific_session()
    {
        $user = User::factory()->create();
        
        $tokenToRevoke = $user->createToken('auth_token_to_revoke');
        $session = UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $tokenToRevoke->token->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser/1.0',
            'created_at' => now(),
        ]);

        $currentToken = $user->createToken('auth_token_current');
        $currentTokenStr = $currentToken->accessToken ?? $currentToken->plainTextToken;

        $response = $this->withToken($currentTokenStr)->deleteJson("/api/auth/sessions/{$session->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        // Pastikan sesi terhapus dari tabel
        $this->assertDatabaseMissing('user_sessions_iam', [
            'id' => $session->id
        ]);

        // Pastikan token di revoke di tabel oauth_access_tokens
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenToRevoke->token->id,
            'revoked' => 1
        ]);
    }

    public function test_user_cannot_revoke_others_session()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $tokenUser2 = $user2->createToken('auth_token_user_2');
        $sessionUser2 = UserSessionIam::create([
            'user_id' => $user2->id,
            'token' => $tokenUser2->token->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser/1.0',
            'created_at' => now(),
        ]);

        $tokenUser1 = $user1->createToken('auth_token_user_1');
        $tokenUser1Str = $tokenUser1->accessToken ?? $tokenUser1->plainTextToken;

        // User1 mencoba hapus sesi User2
        $response = $this->withToken($tokenUser1Str)->deleteJson("/api/auth/sessions/{$sessionUser2->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('user_sessions_iam', [
            'id' => $sessionUser2->id
        ]);
    }

    public function test_user_can_revoke_all_other_sessions()
    {
        $user = User::factory()->create();
        
        // Sesi A (Perangkat A)
        $tokenA = $user->createToken('auth_token_A');
        $sessionA = UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $tokenA->token->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'BrowserA',
        ]);

        // Sesi B (Perangkat B)
        $tokenB = $user->createToken('auth_token_B');
        $sessionB = UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $tokenB->token->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'BrowserB',
        ]);

        // Sesi C (Current)
        $tokenC = $user->createToken('auth_token_C');
        $sessionC = UserSessionIam::create([
            'user_id' => $user->id,
            'token' => $tokenC->token->id,
            'ip_address' => '10.0.0.3',
            'user_agent' => 'BrowserC',
        ]);

        $tokenCStr = $tokenC->accessToken ?? $tokenC->plainTextToken;

        $response = $this->withToken($tokenCStr)->deleteJson('/api/auth/sessions/others');

        $response->assertStatus(200);

        // A dan B harus terhapus
        $this->assertDatabaseMissing('user_sessions_iam', ['id' => $sessionA->id]);
        $this->assertDatabaseMissing('user_sessions_iam', ['id' => $sessionB->id]);
        
        // C harus tetap ada
        $this->assertDatabaseHas('user_sessions_iam', ['id' => $sessionC->id]);

        // Pastikan token Passport A dan B di revoke
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenA->token->id, 'revoked' => 1]);
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenB->token->id, 'revoked' => 1]);
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenC->token->id, 'revoked' => 0]);
    }

    public function test_admin_can_view_all_sessions()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin, 'api');

        UserSessionIam::create([
            'user_id' => $admin->id,
            'token' => 'dummy',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test'
        ]);

        $response = $this->getJson('/api/admin/sessions');
        $response->assertStatus(200)->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_destroy_any_session()
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin, 'api');

        $user = User::factory()->create();
        $session = UserSessionIam::create([
            'user_id' => $user->id,
            'token' => 'dummy',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test'
        ]);

        // Mock DB table for oauth_access_tokens
        \Illuminate\Support\Facades\DB::table('oauth_access_tokens')->insert([
            'id' => 'dummy',
            'user_id' => $user->id,
            'client_id' => 1,
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addDays(1)
        ]);

        $response = $this->deleteJson("/api/admin/sessions/{$session->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_sessions_iam', ['id' => $session->id]);
    }
}
