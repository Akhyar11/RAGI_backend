<?php

namespace Tests\Feature;

use App\Models\Sikeu\Beasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\Fakultas;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiakadMahasiswaBeasiswaTest extends TestCase
{
    protected User $admin;
    protected Mahasiswa $mhs;
    protected Beasiswa $beasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.baak@kampus.ac.id'],
            ['username' => 'baakadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $fakultas = Fakultas::firstOrCreate(
            ['kode' => 'FTIK'],
            ['nama' => 'Fakultas Teknologi Informasi dan Komunikasi']
        );

        $prodi = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI'],
            [
                'nama' => 'Teknik Informatika',
                'jenjang' => 'S1',
                'fakultas_id' => $fakultas->id,
            ]
        );

        $this->mhs = Mahasiswa::create([
            'nama_lengkap' => 'Budi Santoso Beasiswa',
            'nim' => '9988776601',
            'nik' => '3201019988776601',
            'status' => 'aktif',
            'angkatan' => 2025,
            'program_studi_id' => $prodi->id,
        ]);

        $this->beasiswa = Beasiswa::create([
            'kode' => 'KIP_TEST_SIAKAD',
            'nama' => 'KIP Kuliah Test SIAKAD',
            'sumber' => 'pemerintah',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 100,
            'is_active' => true,
        ]);
    }

    protected function headers(): array
    {
        $token = $this->admin->createToken('test-baak-token');
        $plainToken = $token->plainTextToken ?? $token->accessToken;

        return ['Authorization' => 'Bearer ' . $plainToken];
    }

    public function test_get_beasiswa_options(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/siakad/civitas/beasiswa/options');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => ['id', 'kode', 'nama', 'sumber', 'tipe_potongan', 'nilai_potongan', 'potongan_text']
            ]
        ]);
        $this->assertTrue(collect($response->json('data'))->contains('kode', 'KIP_TEST_SIAKAD'));
    }

    public function test_baak_assign_beasiswa_to_mahasiswa(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'berlaku_mulai' => '2025-09-01',
                'berlaku_sampai' => '2026-08-31',
                'status' => 'aktif',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'nim' => '9988776601',
                'nama_mahasiswa' => 'Budi Santoso Beasiswa',
                'status' => 'aktif',
            ]
        ]);

        $this->assertDatabaseHas('sikeu_mahasiswa_beasiswa', [
            'mahasiswa_id' => $this->mhs->id,
            'beasiswa_id' => $this->beasiswa->id,
            'status' => 'aktif',
        ]);
    }

    public function test_assign_duplicate_beasiswa_rejected(): void
    {
        // First assignment
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'status' => 'aktif',
            ])->assertStatus(201);

        // Second assignment (duplicate active)
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'status' => 'aktif',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['beasiswa_id']);
    }

    public function test_index_and_filter_penerima_beasiswa(): void
    {
        // Setup record
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'status' => 'aktif',
            ])->assertStatus(201);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/siakad/civitas/beasiswa?search=' . urlencode('Budi Santoso'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
            'filters',
        ]);
        $this->assertNotEmpty($response->json('data'));
        $this->assertEquals('9988776601', $response->json('data.0.nim'));
    }

    public function test_update_penerima_beasiswa(): void
    {
        $createRes = $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'status' => 'aktif',
            ])->assertStatus(201);

        $id = $createRes->json('data.id');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/siakad/civitas/beasiswa/' . $id, [
                'status' => 'selesai',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sikeu_mahasiswa_beasiswa', [
            'id' => $id,
            'status' => 'selesai',
        ]);
    }

    public function test_destroy_penerima_beasiswa(): void
    {
        $createRes = $this->withHeaders($this->headers())
            ->postJson('/api/v1/siakad/civitas/beasiswa', [
                'mahasiswa_id' => $this->mhs->id,
                'beasiswa_id' => $this->beasiswa->id,
                'status' => 'aktif',
            ])->assertStatus(201);

        $id = $createRes->json('data.id');

        $response = $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/siakad/civitas/beasiswa/' . $id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sikeu_mahasiswa_beasiswa', [
            'id' => $id,
        ]);
    }
}
