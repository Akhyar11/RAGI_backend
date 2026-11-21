<?php

namespace Tests\Feature;

use App\Models\Siakad\ProgramStudi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiakadMahasiswaStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_student_statuses_are_mapped_to_pddikti_codes_and_back(): void
    {
        $migration = require database_path('migrations/2026_11_20_120000_align_siakad_mahasiswa_status_to_pddikti.php');
        $migration->down();

        $program = ProgramStudi::create([
            'kode_prodi' => 'STATUS-TEST',
            'nama' => 'Program Uji Status',
            'jenjang' => 'S1',
            'is_active' => true,
        ]);

        foreach (['aktif', 'cuti', 'mangkir', 'dropout', 'lulus'] as $index => $legacyStatus) {
            DB::table('siakad_mahasiswa')->insert([
                'program_studi_id' => $program->id,
                'nim' => 'STATUS-' . $index,
                'nama_lengkap' => 'Mahasiswa Status ' . $index,
                'angkatan' => 2020,
                'status' => $legacyStatus,
            ]);
        }

        $migration->up();

        $this->assertSame('A', DB::table('siakad_mahasiswa')->where('nim', 'STATUS-0')->value('status'));
        $this->assertSame('C', DB::table('siakad_mahasiswa')->where('nim', 'STATUS-1')->value('status'));
        $this->assertSame(3, DB::table('siakad_mahasiswa')->where('status', 'N')->count());
        $this->assertSame(0, DB::table('siakad_mahasiswa')->whereIn('status', ['aktif', 'cuti', 'mangkir', 'dropout', 'lulus'])->count());

        $migration->down();

        $this->assertSame('aktif', DB::table('siakad_mahasiswa')->where('nim', 'STATUS-0')->value('status'));
        $this->assertSame('cuti', DB::table('siakad_mahasiswa')->where('nim', 'STATUS-1')->value('status'));
        $this->assertSame(3, DB::table('siakad_mahasiswa')->where('status', 'dropout')->count());
    }
}