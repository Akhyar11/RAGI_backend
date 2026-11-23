<?php

namespace Tests\Feature\SPMB;

use App\Models\Spmb\JalurMasuk;
use Database\Seeders\SPMB\JalurMasukSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JalurMasukSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_upserts_initial_admission_paths_idempotently(): void
    {
        $seeder = app(JalurMasukSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('spmb_jalur_masuk', 5);
        $this->assertDatabaseHas('spmb_jalur_masuk', [
            'kode' => 'reguler',
            'nama' => 'Reguler',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('spmb_jalur_masuk', [
            'kode' => 'transfer',
            'nama' => 'Transfer / Pindahan',
            'is_active' => true,
        ]);
        $this->assertSame(5, JalurMasuk::query()->where('is_active', true)->count());
    }
}