<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('siakad_dosen', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_dosen', 'source_id')) {
                $table->string('source_id', 100)
                    ->nullable()
                    ->after('id_feeder')
                    ->comment('ID dosen stabil dari API SIAKAD legacy (xid_ptk) untuk rekonsiliasi sync');

                $table->unique('source_id', 'siakad_dosen_source_id_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_dosen', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_dosen', 'source_id')) {
                $table->dropUnique('siakad_dosen_source_id_unique');
                $table->dropColumn('source_id');
            }
        });
    }
};
