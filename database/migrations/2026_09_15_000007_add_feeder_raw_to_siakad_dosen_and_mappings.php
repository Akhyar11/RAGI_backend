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
        Schema::table('siakad_feeder_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_feeder_mappings', 'raw_data')) {
                $table->json('raw_data')->nullable()->after('feeder_id')->comment('Snapshot payload mentah dari Neo Feeder');
            }
        });

        Schema::table('siakad_dosen', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_dosen', 'feeder_raw')) {
                $table->json('feeder_raw')->nullable()->after('id_feeder')->comment('Snapshot payload mentah dari Neo Feeder');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_feeder_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_feeder_mappings', 'raw_data')) {
                $table->dropColumn('raw_data');
            }
        });

        Schema::table('siakad_dosen', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_dosen', 'feeder_raw')) {
                $table->dropColumn('feeder_raw');
            }
        });
    }
};
