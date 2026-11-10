<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat tabel audit dan tracking transaksi H2H Bank (BTN Syariah dan bank lainnya).
     */
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_h2h_inquiries')) {
            Schema::create('sikeu_h2h_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('id_transaksi', 100)->unique();
                $table->dateTime('tanggal_transaksi');
                $table->string('id_tagihan', 30);
                $table->string('nomor_pembayaran', 30)->index();
                $table->string('kode_bank', 20)->default('BTNS');
                $table->string('kode_biller', 20)->default('31310');
                $table->string('kode_channel', 50)->nullable();
                $table->string('kode_terminal', 50)->nullable();
                $table->string('checksum', 100);
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sikeu_h2h_reversals')) {
            Schema::create('sikeu_h2h_reversals', function (Blueprint $table) {
                $table->id();
                $table->string('id_transaksi', 100)->unique();
                $table->dateTime('tanggal_transaksi');
                $table->dateTime('tanggal_transaksi_asal');
                $table->string('nomor_pembayaran', 30)->index();
                $table->string('nomor_jurnal_pembukuan', 100);
                $table->decimal('total_nominal', 15, 2);
                $table->string('kode_bank', 20)->default('BTNS');
                $table->string('checksum', 100);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_h2h_reversals');
        Schema::dropIfExists('sikeu_h2h_inquiries');
    }
};
