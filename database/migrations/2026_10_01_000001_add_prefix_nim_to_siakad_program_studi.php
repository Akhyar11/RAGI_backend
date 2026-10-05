<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prefix NIM kustom per program studi (mis. Otomotif = "A").
     * Jika terisi, generate NIM otomatis memakai format {PREFIX}{YY}{3-digit urut}.
     * Jika kosong, fallback ke format standar {YY}{2-digit prodiId}{4-digit urut}.
     */
    public function up(): void
    {
        Schema::table('siakad_program_studi', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_program_studi', 'prefix_nim')) {
                $table->string('prefix_nim', 10)->nullable()->after('kode_prodi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siakad_program_studi', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_program_studi', 'prefix_nim')) {
                $table->dropColumn('prefix_nim');
            }
        });
    }
};
