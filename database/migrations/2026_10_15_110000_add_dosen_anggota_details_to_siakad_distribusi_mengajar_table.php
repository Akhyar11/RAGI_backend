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
                if (!Schema::hasColumn('siakad_distribusi_mengajar', 'dosen_anggota_details')) {
                    $table->json('dosen_anggota_details')->nullable()->after('kelas_ids');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_distribusi_mengajar')) {
            Schema::table('siakad_distribusi_mengajar', function (Blueprint $table) {
                if (Schema::hasColumn('siakad_distribusi_mengajar', 'dosen_anggota_details')) {
                    $table->dropColumn('dosen_anggota_details');
                }
            });
        }
    }
};
