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
        Schema::create('sinapra_detail_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('sinapra_pengajuan_pengadaan')->onDelete('cascade');
            $table->foreignId('kategori_aset_id')->nullable()->constrained('sinapra_kategori_aset')->onDelete('set null');
            $table->string('nama_barang', 150);
            $table->text('spesifikasi')->nullable();
            $table->integer('jumlah')->default(1);
            $table->string('satuan', 50)->default('unit');
            $table->decimal('harga_satuan_estimasi', 15, 2)->default(0.00);
            $table->decimal('total_estimasi', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index(['pengajuan_id', 'kategori_aset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_detail_pengadaan');
    }
};
