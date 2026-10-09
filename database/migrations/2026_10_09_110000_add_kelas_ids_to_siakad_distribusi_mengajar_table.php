<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_distribusi_mengajar')) {
            Schema::table('siakad_distribusi_mengajar', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_distribusi_mengajar', 'kelas_ids')) {
                    $table->json('kelas_ids')->nullable()->after('dosen_anggota_ids'); // array of master_kelas IDs
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_distribusi_mengajar')) {
            Schema::table('siakad_distribusi_mengajar', function (Blueprint $table) {
                if (Schema::hasColumn('siakad_distribusi_mengajar', 'kelas_ids')) {
                    $table->dropColumn('kelas_ids');
                }
            });
        }
    }
};
