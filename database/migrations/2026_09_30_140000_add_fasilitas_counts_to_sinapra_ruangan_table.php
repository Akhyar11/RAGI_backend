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
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_ruangan', 'jumlah_ac')) {
                $table->integer('jumlah_ac')->default(0)->after('ada_ac');
            }
            if (!Schema::hasColumn('sinapra_ruangan', 'jumlah_proyektor')) {
                $table->integer('jumlah_proyektor')->default(0)->after('ada_proyektor');
            }
            if (!Schema::hasColumn('sinapra_ruangan', 'jumlah_wifi')) {
                $table->integer('jumlah_wifi')->default(0)->after('ada_wifi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_ruangan', function (Blueprint $table) {
            if (Schema::hasColumn('sinapra_ruangan', 'jumlah_wifi')) {
                $table->dropColumn('jumlah_wifi');
            }
            if (Schema::hasColumn('sinapra_ruangan', 'jumlah_proyektor')) {
                $table->dropColumn('jumlah_proyektor');
            }
            if (Schema::hasColumn('sinapra_ruangan', 'jumlah_ac')) {
                $table->dropColumn('jumlah_ac');
            }
        });
    }
};
