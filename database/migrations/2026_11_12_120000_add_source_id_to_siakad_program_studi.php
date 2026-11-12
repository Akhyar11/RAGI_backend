<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siakad_program_studi', 'source_id')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->string('source_id', 100)->nullable()->unique()->after('kode_prodi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_program_studi', 'source_id')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->dropUnique('siakad_program_studi_source_id_unique');
                $table->dropColumn('source_id');
            });
        }
    }
};