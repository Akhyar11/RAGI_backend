<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('siakad_tahun_akademik', 'id_feeder')) {
            Schema::table('siakad_tahun_akademik', function (Blueprint $table) {
                $table->string('id_feeder', 100)->nullable()->unique()->after('kode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siakad_tahun_akademik', 'id_feeder')) {
            Schema::table('siakad_tahun_akademik', function (Blueprint $table) {
                $table->dropUnique('siakad_tahun_akademik_id_feeder_unique');
                $table->dropColumn('id_feeder');
            });
        }
    }
};