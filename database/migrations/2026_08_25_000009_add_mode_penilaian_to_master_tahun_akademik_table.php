<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_tahun_akademik', function (Blueprint $table) {
            if (!Schema::hasColumn('master_tahun_akademik', 'mode_penilaian')) {
                $table->string('mode_penilaian')->default('semi_obe'); // 'full_obe', 'semi_obe', 'konvensional'
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_tahun_akademik', function (Blueprint $table) {
            if (Schema::hasColumn('master_tahun_akademik', 'mode_penilaian')) {
                $table->dropColumn('mode_penilaian');
            }
        });
    }
};
