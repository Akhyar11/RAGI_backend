<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Di Neo Feeder kode mata kuliah tidak unik global: id_matkul berbeda dapat memakai kode sama
 * (mis. kode yang sama pada kurikulum berbeda atau prodi berbeda). Identitas mata kuliah adalah id_feeder (unik).
 * Keunikan kode dipersempit menjadi per kurikulum.
 */
return new class extends Migration
{
    private const GLOBAL_UNIQUE = 'siakad_mata_kuliah_kode_mk_unique';
    private const SCOPED_UNIQUE = 'siakad_mata_kuliah_kurikulum_kode_unique';

    public function up(): void
    {
        if (!Schema::hasTable('siakad_mata_kuliah')) {
            return;
        }

        if (!$this->hasIndex(self::SCOPED_UNIQUE)) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->unique(['kurikulum_id', 'kode_mk'], self::SCOPED_UNIQUE);
            });
        }
        if ($this->hasIndex(self::GLOBAL_UNIQUE)) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->dropUnique(self::GLOBAL_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('siakad_mata_kuliah')) {
            return;
        }

        $duplicates = DB::table('siakad_mata_kuliah')->select('kode_mk')
            ->groupBy('kode_mk')->havingRaw('count(*) > 1')->count();
        if ($duplicates > 0) {
            throw new RuntimeException("Tidak dapat mengembalikan unique kode_mk global: {$duplicates} kode dipakai lebih dari satu mata kuliah.");
        }

        if (!$this->hasIndex(self::GLOBAL_UNIQUE)) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->unique('kode_mk', self::GLOBAL_UNIQUE);
            });
        }
        if ($this->hasIndex(self::SCOPED_UNIQUE)) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table) {
                $table->dropUnique(self::SCOPED_UNIQUE);
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('siakad_mata_kuliah'))->contains(fn (array $index) => $index['name'] === $name);
    }
};
