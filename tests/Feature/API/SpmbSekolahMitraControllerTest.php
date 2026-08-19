<?php

namespace Tests\Feature\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SpmbSekolahMitraControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup initial user for Sanctum / Auth mocking if necessary
        // Using actingAs with a generic user for test
        $user = User::factory()->create();
        // Assume user has admin role handled by IAM
        $this->actingAs($user, 'api');
    }

    public function test_can_get_list_of_sekolah_mitra()
    {
        // Insert dummy data
        DB::table('spmb_sekolah_mitra')->insert([
            'npsn' => '11223344',
            'nama_sekolah' => 'SMAN 1 Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/spmb/sekolah-mitra');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         '*' => [
                             'id',
                             'npsn',
                             'nama_sekolah',
                         ]
                     ],
                     'meta',
                     'filters'
                 ]);
    }

    public function test_can_create_new_sekolah_mitra()
    {
        $payload = [
            'npsn' => '99887766',
            'nama_sekolah' => 'SMK 1 Test Payload',
            'alamat' => 'Jl. Test No 123',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/spmb/sekolah-mitra', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Sekolah mitra berhasil ditambahkan',
                 ]);

        $this->assertDatabaseHas('spmb_sekolah_mitra', [
            'npsn' => '99887766',
            'nama_sekolah' => 'SMK 1 Test Payload',
        ]);
    }

    public function test_validation_fails_on_duplicate_npsn()
    {
        DB::table('spmb_sekolah_mitra')->insert([
            'npsn' => '55555555',
            'nama_sekolah' => 'SMA Duplicate',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'npsn' => '55555555',
            'nama_sekolah' => 'SMA Another Name',
        ];

        $response = $this->postJson('/api/spmb/sekolah-mitra', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['npsn']);
    }
}
