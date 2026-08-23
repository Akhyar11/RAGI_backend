<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jadwal_ujian_spmb')) {
            Schema::create('jadwal_ujian_spmb', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gelombang_id')->constrained('gelombang_penerimaan')->cascadeOnDelete();
                $table->unsignedBigInteger('ruangan_id')->nullable();
                $table->string('nama_sesi');
                $table->date('tanggal');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->integer('kapasitas');
                $table->string('tipe_ujian', 50); // e.g. tulis, praktik, wawancara
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('peserta_ujian_spmb')) {
            Schema::create('peserta_ujian_spmb', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
                $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian_spmb')->cascadeOnDelete();
                $table->string('no_peserta')->unique();
                $table->string('nomor_kursi')->nullable();
                $table->boolean('hadir')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_ujian_spmb');
        Schema::dropIfExists('jadwal_ujian_spmb');
    }
};
