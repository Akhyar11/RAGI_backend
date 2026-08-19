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
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->enum('status_pembayaran', ['belum_bayar', 'sebagian', 'lunas', 'gratis'])->default('belum_bayar')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->dropColumn('status_pembayaran');
        });
    }
};
