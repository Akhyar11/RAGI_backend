<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siakad_mahasiswa', 'id_registrasi')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
                $table->string('id_registrasi', 100)->nullable()->unique()->after('nim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_mahasiswa', 'id_registrasi')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
                $table->dropUnique('siakad_mahasiswa_id_registrasi_unique');
                $table->dropColumn('id_registrasi');
            });
        }
    }
};
