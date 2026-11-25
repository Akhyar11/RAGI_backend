<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration 2026_11_25_110000: id_feeder identitas dibuat unik setelah baris duplikat digabung.
 */
class SiakadFeederIdentityUniqueMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_25_110000_add_unique_id_feeder_identity_columns.php';

    private function migration(): object
    {
        return require database_path('migrations/' . self::MIGRATION);
    }

    private function dropUnique(string $table, string $column): void
    {
        Schema::table($table, fn ($blueprint) => $blueprint->dropUnique("{$table}_{$column}_unique"));
    }

    private function hasUnique(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($index) => ($index['unique'] ?? false) && $index['columns'] === [$column]);
    }

    private function prodi(array $attributes): int
    {
        return DB::table('siakad_program_studi')->insertGetId(array_merge([
            'jenjang' => 'D4', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    public function test_unique_indexes_exist_for_identity_columns_only(): void
    {
        foreach ([
            ['siakad_program_studi', 'id_feeder'], ['siakad_mata_kuliah', 'id_feeder'], ['siakad_dosen', 'id_feeder'],
            ['siakad_mahasiswa', 'id_feeder'], ['siakad_mahasiswa', 'id_feeder_biodata'],
            ['siakad_jadwal_kelas', 'id_feeder'], ['siakad_dosen_pengampu', 'id_feeder'],
        ] as [$table, $column]) {
            $this->assertTrue($this->hasUnique($table, $column), "{$table}.{$column} harus unik");
        }

        $this->assertFalse($this->hasUnique('siakad_dosen_penugasan', 'id_feeder'), 'id_registrasi_dosen berulang per tahun akademik');
        $this->assertFalse($this->hasUnique('siakad_mahasiswa', 'id_feeder_riwayat'));
        $this->assertFalse($this->hasUnique('siakad_feeder_mappings', 'feeder_id'));
    }

    public function test_duplicate_identity_is_rejected_by_database(): void
    {
        $this->prodi(['kode_prodi' => 'K1', 'nama' => 'Satu', 'id_feeder' => 'uuid-1']);

        $this->expectException(QueryException::class);
        $this->prodi(['kode_prodi' => 'K2', 'nama' => 'Dua', 'id_feeder' => 'uuid-1']);
    }

    public function test_null_identity_may_repeat_so_unsynced_rows_do_not_collide(): void
    {
        $this->prodi(['kode_prodi' => 'N1', 'nama' => 'Tanpa Feeder 1', 'id_feeder' => null]);
        $this->prodi(['kode_prodi' => 'N2', 'nama' => 'Tanpa Feeder 2', 'id_feeder' => null]);

        $this->assertSame(2, DB::table('siakad_program_studi')->whereNull('id_feeder')->count());
    }

    public function test_duplicate_prodi_is_merged_with_references_repointed_to_survivor(): void
    {
        $this->dropUnique('siakad_program_studi', 'id_feeder');

        // Meniru data nyata: baris legacy nonaktif punya rujukan; baris kanonik aktif tanpa rujukan.
        $legacy = $this->prodi([
            'kode_prodi' => 'A', 'nama' => 'Teknologi Rekayasa Otomotif', 'id_feeder' => 'prodi-feeder-uuid',
            'source_id' => 'prodi-feeder-uuid', 'is_active' => false,
        ]);
        $canonical = $this->prodi([
            'kode_prodi' => '21304', 'nama' => 'D4 Teknologi Rekayasa Otomotif', 'id_feeder' => 'prodi-feeder-uuid',
            'source_id' => null, 'is_active' => true,
        ]);
        $other = $this->prodi(['kode_prodi' => '99999', 'nama' => 'Tidak Terkait', 'id_feeder' => 'prodi-lain-uuid']);

        $curriculumId = DB::table('siakad_kurikulum')->insertGetId([
            'program_studi_id' => $legacy, 'kode' => 'KUR-LEGACY', 'nama' => 'Kurikulum', 'tahun_berlaku' => 2023,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentId = DB::table('siakad_mahasiswa')->insertGetId([
            'program_studi_id' => $legacy, 'nim' => 'MRG001', 'nama_lengkap' => 'Mahasiswa Legacy',
            'angkatan' => 2023, 'status' => 'A', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lecturerId = DB::table('siakad_dosen')->insertGetId([
            'nama_lengkap' => 'Dosen Legacy', 'program_studi_id' => $legacy, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $remaining = DB::table('siakad_program_studi')->where('id_feeder', 'prodi-feeder-uuid')->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($canonical, (int) $remaining->first()->id, 'Baris aktif bertahan');
        $this->assertDatabaseMissing('siakad_program_studi', ['id' => $legacy]);
        $this->assertDatabaseHas('siakad_program_studi', ['id' => $other]);

        $this->assertSame($canonical, (int) DB::table('siakad_kurikulum')->where('id', $curriculumId)->value('program_studi_id'));
        $this->assertSame($canonical, (int) DB::table('siakad_mahasiswa')->where('id', $studentId)->value('program_studi_id'));
        $this->assertSame($canonical, (int) DB::table('siakad_dosen')->where('id', $lecturerId)->value('program_studi_id'));

        $this->assertSame('prodi-feeder-uuid', $remaining->first()->source_id, 'source_id legacy dibawa ke baris bertahan');
        $this->assertTrue($this->hasUnique('siakad_program_studi', 'id_feeder'));
    }

    public function test_migration_is_idempotent_and_keeps_clean_data(): void
    {
        $a = $this->prodi(['kode_prodi' => 'I1', 'nama' => 'Idempoten', 'id_feeder' => 'idem-1']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseHas('siakad_program_studi', ['id' => $a, 'id_feeder' => 'idem-1']);
        $this->assertTrue($this->hasUnique('siakad_program_studi', 'id_feeder'));
    }

    public function test_empty_string_identity_is_normalized_to_null_before_unique(): void
    {
        $this->dropUnique('siakad_program_studi', 'id_feeder');
        $a = $this->prodi(['kode_prodi' => 'E1', 'nama' => 'Kosong 1', 'id_feeder' => '']);
        $b = $this->prodi(['kode_prodi' => 'E2', 'nama' => 'Kosong 2', 'id_feeder' => '']);

        $this->migration()->up();

        $this->assertNull(DB::table('siakad_program_studi')->where('id', $a)->value('id_feeder'));
        $this->assertNull(DB::table('siakad_program_studi')->where('id', $b)->value('id_feeder'));
        $this->assertDatabaseHas('siakad_program_studi', ['id' => $a]);
        $this->assertDatabaseHas('siakad_program_studi', ['id' => $b]);
    }

    public function test_penugasan_dosen_may_share_registration_id_across_academic_years(): void
    {
        $now = now();
        $prodiId = $this->prodi(['kode_prodi' => 'P1', 'nama' => 'Prodi Penugasan']);
        $lecturerId = DB::table('siakad_dosen')->insertGetId([
            'nama_lengkap' => 'Dosen Penugasan', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $ta = fn (string $kode, int $y) => DB::table('siakad_tahun_akademik')->insertGetId([
            'kode' => $kode, 'nama' => $kode, 'tahun_mulai' => $y, 'tahun_selesai' => $y + 1,
            'is_active' => false, 'mode_penilaian' => 'persentase', 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([$ta('20241', 2024), $ta('20251', 2025)] as $taId) {
            DB::table('siakad_dosen_penugasan')->insert([
                'dosen_id' => $lecturerId, 'program_studi_id' => $prodiId, 'tahun_akademik_id' => $taId,
                'id_feeder' => 'registrasi-dosen-sama', 'is_homebase' => true, 'sync_status' => 'synced',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->assertSame(2, DB::table('siakad_dosen_penugasan')->where('id_feeder', 'registrasi-dosen-sama')->count());
    }
}
