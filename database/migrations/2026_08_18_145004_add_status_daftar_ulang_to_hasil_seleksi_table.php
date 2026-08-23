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
        Schema::table('spmb_hasil_seleksi', function (Blueprint $table) {
            $table->enum('status_daftar_ulang', ['belum', 'menunggu_pembayaran', 'lunas'])->default('belum')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_hasil_seleksi', function (Blueprint $table) {
            $table->dropColumn('status_daftar_ulang');
        });
    }
};
