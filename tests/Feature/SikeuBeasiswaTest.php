<?php

namespace Tests\Feature;

use App\Models\Sikeu\Beasiswa;
use App\Models\Sikeu\MasterBiaya;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuBeasiswaTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.beasiswa@kampus.ac.id'],
            ['username' => 'beasiswaadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('beasiswa-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    protected function makeKomponen(string $kode, string $nama): MasterBiaya
    {
        return MasterBiaya::create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe' => 'spp',
            'is_active' => true,
        ]);
    }

    public function test_store_beasiswa_dengan_multi_komponen(): void
    {
        $ukt = $this->makeKomponen('UKT_REG', 'UKT Reguler');
        $praktikum = $this->makeKomponen('PRAKTIKUM', 'Biaya Praktikum');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/beasiswa', [
                'kode' => 'KIP_KULIAH',
                'nama' => 'KIP Kuliah',
                'sumber' => 'pemerintah',
                'tipe_potongan' => 'nominal',
                'nilai_potongan' => 4000000,
                'jenis_biaya_ids' => [$ukt->id, $praktikum->id],
            ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['id', 'kode', 'jenis_biaya_ids']]);

        $this->assertEquals([$ukt->id, $praktikum->id], $response->json('data.jenis_biaya_ids'));

        $this->assertDatabaseHas('sikeu_beasiswa', ['kode' => 'KIP_KULIAH']);
        $this->assertDatabaseHas('sikeu_beasiswa_jenis_biaya', ['beasiswa_id' => $response->json('data.id'), 'jenis_biaya_id' => $ukt->id]);
        $this->assertDatabaseHas('sikeu_beasiswa_jenis_biaya', ['beasiswa_id' => $response->json('data.id'), 'jenis_biaya_id' => $praktikum->id]);
    }

    public function test_store_beasiswa_tanpa_komponen_berlaku_global(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/beasiswa', [
                'kode' => 'BEASISWA_TAHUNAN',
                'nama' => 'Beasiswa Tahunan',
                'sumber' => 'internal',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_beasiswa_jenis_biaya', ['beasiswa_id' => $response->json('data.id')]);
        $this->assertEquals([], $response->json('data.jenis_biaya_ids'));
    }

    public function test_store_beasiswa_validasi_komponen_tidak_valid(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/master/beasiswa', [
                'kode' => 'BEASISWA_INVALID',
                'nama' => 'Beasiswa Invalid',
                'sumber' => 'internal',
                'tipe_potongan' => 'persen',
                'nilai_potongan' => 50,
                'jenis_biaya_ids' => [999999],
            ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseMissing('sikeu_beasiswa', ['kode' => 'BEASISWA_INVALID']);
    }

    public function test_update_beasiswa_sync_daftar_komponen(): void
    {
        $ukt = $this->makeKomponen('UKT_REG_2', 'UKT Reguler 2');
        $wisuda = $this->makeKomponen('WISUDA', 'Biaya Wisuda');

        $beasiswa = Beasiswa::create([
            'kode' => 'BEASISWA_SYNC',
            'nama' => 'Beasiswa Sync',
            'sumber' => 'internal',
            'tipe_potongan' => 'nominal',
            'nilai_potongan' => 1500000,
        ]);
        $beasiswa->jenisBiaya()->attach([$ukt->id]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/master/beasiswa/' . $beasiswa->id, [
                'kode' => 'BEASISWA_SYNC',
                'nama' => 'Beasiswa Sync Updated',
                'sumber' => 'internal',
                'tipe_potongan' => 'nominal',
                'nilai_potongan' => 2000000,
                'jenis_biaya_ids' => [$wisuda->id],
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals([$wisuda->id], $response->json('data.jenis_biaya_ids'));

        $this->assertDatabaseMissing('sikeu_beasiswa_jenis_biaya', ['beasiswa_id' => $beasiswa->id, 'jenis_biaya_id' => $ukt->id]);
        $this->assertDatabaseHas('sikeu_beasiswa_jenis_biaya', ['beasiswa_id' => $beasiswa->id, 'jenis_biaya_id' => $wisuda->id]);
    }

    public function test_index_beasiswa_memuat_jenis_biaya_ids(): void
    {
        $ukt = $this->makeKomponen('UKT_INDEX', 'UKT Index');

        $beasiswa = Beasiswa::create([
            'kode' => 'BEASISWA_INDEX',
            'nama' => 'Beasiswa Index',
            'sumber' => 'mitra',
            'tipe_potongan' => 'persen',
            'nilai_potongan' => 25,
        ]);
        $beasiswa->jenisBiaya()->attach([$ukt->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/master/beasiswa');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $found = collect($response->json('data'))->firstWhere('kode', 'BEASISWA_INDEX');
        $this->assertNotNull($found);
        $this->assertEquals([$ukt->id], $found['jenis_biaya_ids']);
    }
}