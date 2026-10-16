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
        Schema::table('siakad_konversi_transfer', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_konversi_transfer', 'nomor_sk')) {
                $table->string('nomor_sk', 150)->nullable()->after('prodi_asal')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_konversi_transfer', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_konversi_transfer', 'nomor_sk')) {
                $table->dropIndex(['nomor_sk']);
                $table->dropColumn('nomor_sk');
            }
        });
    }
};
