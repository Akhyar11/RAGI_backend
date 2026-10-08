<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\User;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Siakad\Kurikulum;

class SiakadKurikulumListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        Passport::actingAs(User::factory()->create());

        $prodi = MasterProgramStudi::create([
            'kode_prodi' => 'INF',
            'nama' => 'Informatika',
            'jenjang' => 'S1',
        ]);

        Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode' => 'KUR-2024',
            'nama' => 'Kurikulum 2024',
            'tahun_berlaku' => 2024,
            'total_sks_lulus' => 144,
            'is_active' => true,
        ]);

        Kurikulum::create([
            'program_studi_id' => $prodi->id,
            'kode' => 'KUR-2025',
            'nama' => 'Kurikulum 2025',
            'tahun_berlaku' => 2025,
            'total_sks_lulus' => 150,
            'is_active' => false,
        ]);
    }

    public function test_default_list_hanya_kurikulum_aktif()
    {
        $this->getJson('/api/v1/siakad/akademik/kurikulum')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode', 'KUR-2024');
    }

    public function test_filter_status_nonaktif()
    {
        $this->getJson('/api/v1/siakad/akademik/kurikulum?status=nonaktif')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode', 'KUR-2025');
    }

    public function test_sort_dan_paginasi()
    {
        $this->getJson('/api/v1/siakad/akademik/kurikulum?status=nonaktif&sort_by=tahun_berlaku&sort_order=asc&per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.tahun_berlaku', 2025)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to']]);
    }

    public function test_sort_default_tahun_berlaku_desc()
    {
        $this->getJson('/api/v1/siakad/akademik/kurikulum?status=aktif&per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('data.0.tahun_berlaku', 2024);
    }
}
