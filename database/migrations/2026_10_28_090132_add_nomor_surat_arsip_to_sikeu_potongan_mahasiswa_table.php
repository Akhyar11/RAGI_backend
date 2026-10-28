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
        Schema::table('sikeu_potongan_mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('nomor_surat_arsip_id')->nullable()->after('nomor_sk')->index();
            $table->string('nomor_surat_arsip', 150)->nullable()->after('nomor_surat_arsip_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sikeu_potongan_mahasiswa', function (Blueprint $table) {
            $table->dropColumn(['nomor_surat_arsip_id', 'nomor_surat_arsip']);
        });
    }
};
