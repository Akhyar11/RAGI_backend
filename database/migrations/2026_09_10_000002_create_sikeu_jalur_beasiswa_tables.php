<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Jalur Kelas & Beasiswa SIKEU.
     */
    public function up(): void
    {
        // 1. Jalur Kelas Perkuliahan (Reguler, Karyawan, Internasional, Online)
        if (!Schema::hasTable('sikeu_jalur_kelas')) {
            Schema::create('sikeu_jalur_kelas', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->nullable();
                $table->string('nama_jalur');
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Tarif Kelompok UKT (Level 1-8)
        if (!Schema::hasTable('sikeu_tarif_ukt')) {
            Schema::create('sikeu_tarif_ukt', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jenis_biaya_id')->nullable()->constrained('sikeu_master_biaya')->nullOnDelete();
                $table->integer('tahun_angkatan')->default(2025);
                $table->string('jalur_kelas')->default('Reguler');
                $table->integer('kelompok_ukt')->default(1);
                $table->string('prodi')->nullable();
                $table->string('nama_kelompok')->nullable();
                $table->decimal('nominal', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 3. Program Beasiswa
        if (!Schema::hasTable('sikeu_beasiswa')) {
            Schema::create('sikeu_beasiswa', function (Blueprint $table) {
                $table->id();
                $table->string('kode')->unique();
                $table->string('nama');
                $table->string('sumber')->default('internal'); // internal, yayasan, pemerintah, mitra
                $table->enum('tipe_potongan', ['persen', 'nominal'])->default('persen');
                $table->decimal('nilai_potongan', 15, 2)->default(0);
                $table->foreignId('jenis_biaya_id')->nullable()->constrained('sikeu_master_biaya')->nullOnDelete();
                $table->integer('berlaku_angkatan_mulai')->nullable();
                $table->integer('berlaku_angkatan_sampai')->nullable();
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Mahasiswa Penerima Beasiswa (Mapping)
        if (!Schema::hasTable('sikeu_mahasiswa_beasiswa')) {
            Schema::create('sikeu_mahasiswa_beasiswa', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id');
                $table->foreignId('beasiswa_id')->constrained('sikeu_beasiswa')->cascadeOnDelete();
                $table->string('nim')->nullable();
                $table->string('nama_mahasiswa')->nullable();
                $table->date('berlaku_mulai')->nullable();
                $table->date('berlaku_sampai')->nullable();
                $table->enum('status', ['aktif', 'nonaktif', 'selesai'])->default('aktif');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_mahasiswa_beasiswa');
        Schema::dropIfExists('sikeu_beasiswa');
        Schema::dropIfExists('sikeu_tarif_ukt');
        Schema::dropIfExists('sikeu_jalur_kelas');
    }
};
