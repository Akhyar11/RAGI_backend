<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `id_feeder` is normally created by the initial Feeder migration; preserve it on rollback.
        if (!Schema::hasColumn('siakad_mata_kuliah', 'id_feeder')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table): void {
                $table->string('id_feeder', 100)->nullable()->after('is_active');
            });
        }

        if (!Schema::hasColumn('siakad_mata_kuliah', 'source_id')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table): void {
                $table->string('source_id', 100)->nullable()->unique()->after('kode_mk');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_mata_kuliah', 'source_id')) {
            Schema::table('siakad_mata_kuliah', function (Blueprint $table): void {
                $table->dropUnique('siakad_mata_kuliah_source_id_unique');
                $table->dropColumn('source_id');
            });
        }
    }
};