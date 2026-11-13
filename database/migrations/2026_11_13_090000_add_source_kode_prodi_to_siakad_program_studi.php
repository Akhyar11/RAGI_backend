<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siakad_program_studi', 'source_kode_prodi')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->string('source_kode_prodi', 20)->nullable()->after('source_id');
            });
        }

        if (! Schema::hasColumn('siakad_program_studi', 'is_status_manual')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->boolean('is_status_manual')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_program_studi', 'is_status_manual')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->dropColumn('is_status_manual');
            });
        }

        if (Schema::hasColumn('siakad_program_studi', 'source_kode_prodi')) {
            Schema::table('siakad_program_studi', function (Blueprint $table): void {
                $table->dropColumn('source_kode_prodi');
            });
        }
    }
};