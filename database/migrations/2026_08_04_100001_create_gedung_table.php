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
        Schema::create('gedung', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->integer('jumlah_lantai')->default(1);
            $table->text('alamat')->nullable();
            $table->integer('tahun_bangun')->nullable();
            $table->decimal('luas_m2', 10, 2)->nullable();
            $table->enum('status', ['aktif', 'renovasi', 'tidak_aktif'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gedung');
    }
};
