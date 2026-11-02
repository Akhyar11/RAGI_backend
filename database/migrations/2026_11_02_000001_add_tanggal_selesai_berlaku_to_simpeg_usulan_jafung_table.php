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
        if (Schema::hasTable('simpeg_usulan_jafung')) {
            Schema::table('simpeg_usulan_jafung', function (Blueprint $table) {
                if (!Schema::hasColumn('simpeg_usulan_jafung', 'tanggal_selesai_berlaku')) {
                    $table->date('tanggal_selesai_berlaku')->nullable()->after('tanggal_mulai_berlaku')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('simpeg_usulan_jafung')) {
            Schema::table('simpeg_usulan_jafung', function (Blueprint $table) {
                if (Schema::hasColumn('simpeg_usulan_jafung', 'tanggal_selesai_berlaku')) {
                    $table->dropIndex(['tanggal_selesai_berlaku']);
                    $table->dropColumn('tanggal_selesai_berlaku');
                }
            });
        }
    }
};
