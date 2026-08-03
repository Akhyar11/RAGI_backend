<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang_penerimaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalur_masuk_id')->constrained('jalur_masuk')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id'); // Assuming table exists or will be created
            $table->string('nama');
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->date('tanggal_ujian')->nullable();
            $table->date('tanggal_pengumuman')->nullable();
            $table->integer('kuota_total')->default(0);
            $table->integer('kuota_terisi')->default(0);
            $table->decimal('biaya_pendaftaran', 15, 2)->default(0);
            $table->enum('status', ['draft', 'aktif', 'ditutup', 'selesai'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_penerimaan');
    }
};
