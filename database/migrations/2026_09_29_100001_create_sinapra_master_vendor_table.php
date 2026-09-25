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
        Schema::create('sinapra_master_vendor', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('jenis_rekanan', 60)->default('penyedia_barang'); // penyedia_barang, jasa_maintenance, laboratorium_kalibrasi, kontraktor, umum
            $table->text('alamat')->nullable();
            $table->string('telepon', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('pic_nama', 100)->nullable();
            $table->string('pic_kontak', 50)->nullable();
            $table->string('nomor_npwp', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('urutan')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis_rekanan', 'is_active']);
            $table->index('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_master_vendor');
    }
};
