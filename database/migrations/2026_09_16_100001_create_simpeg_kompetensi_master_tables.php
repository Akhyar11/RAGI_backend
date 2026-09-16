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
        // 1. Master Jenis Sertifikasi
        Schema::create('simpeg_master_jenis_sertifikasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kode', 50)->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Master Jenis Tes
        Schema::create('simpeg_master_jenis_tes', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kode', 50)->unique();
            $table->string('kategori', 50)->default('bahasa'); // bahasa, potensi_akademik
            $table->decimal('skor_min', 8, 2)->default(0);
            $table->decimal('skor_max', 8, 2)->default(1000);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Master Jenis Pelatihan
        Schema::create('simpeg_master_jenis_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Master Peran Pelatihan
        Schema::create('simpeg_master_peran_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Master Tingkat Kegiatan
        Schema::create('simpeg_master_tingkat_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_master_tingkat_kegiatan');
        Schema::dropIfExists('simpeg_master_peran_pelatihan');
        Schema::dropIfExists('simpeg_master_jenis_pelatihan');
        Schema::dropIfExists('simpeg_master_jenis_tes');
        Schema::dropIfExists('simpeg_master_jenis_sertifikasi');
    }
};
