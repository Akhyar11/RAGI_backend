<?php

namespace Tests\Feature\SPMB;

use App\Models\Siakad\ProgramStudi;
use App\Services\Spmb\SpmbKonversiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmbGenerateNimTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgramStudi(array $attributes = []): ProgramStudi
    {
        return ProgramStudi::create(array_merge([
            'kode_prodi' => '62310',
            'nama' => 'D4 Akuntansi Perpajakan',
            'jenjang' => 'D4',
            'is_active' => true,
        ], $attributes));
    }

    public function test_generate_nim_memakai_prefix_nim_bukan_kode_prodi(): void
    {
        $prodi = $this->makeProgramStudi([
            'kode_prodi' => '62310',
            'prefix_nim' => 'c',
        ]);

        $nim = (new SpmbKonversiService())->generateNIM(2026, $prodi->id);

        $this->assertSame('C26001', $nim);
    }

    public function test_generate_nim_melanjutkan_urutan_dari_nim_prefix_yang_sama(): void
    {
        $prodi = $this->makeProgramStudi(['prefix_nim' => 'C']);

        \App\Models\Siakad\Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => 'C26001',
            'nama_lengkap' => 'Mahasiswa Lama',
            'angkatan' => 2026,
        ]);

        $nim = (new SpmbKonversiService())->generateNIM(2026, $prodi->id);

        $this->assertSame('C26002', $nim);
    }

    public function test_generate_nim_memakai_format_fallback_saat_prefix_nim_kosong(): void
    {
        $prodi = $this->makeProgramStudi(['prefix_nim' => null]);

        $nim = (new SpmbKonversiService())->generateNIM(2026, $prodi->id);

        $this->assertSame(
            '26' . str_pad((string) $prodi->id, 2, '0', STR_PAD_LEFT) . '0001',
            $nim
        );
    }
}
