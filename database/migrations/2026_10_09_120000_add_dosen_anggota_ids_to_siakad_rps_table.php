<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siakad_rps')) {
            Schema::table('siakad_rps', function (Blueprint $table) {
                if (!Schema::hasColumn('siakad_rps', 'dosen_anggota_ids')) {
                    $table->json('dosen_anggota_ids')->nullable()->after('dosen_pengembang_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_rps')) {
            Schema::table('siakad_rps', function (Blueprint $table) {
                if (Schema::hasColumn('siakad_rps', 'dosen_anggota_ids')) {
                    $table->dropColumn('dosen_anggota_ids');
                }
            });
        }
    }
};
