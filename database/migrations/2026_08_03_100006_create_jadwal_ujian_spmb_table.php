<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ujian_spmb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('gelombang_penerimaan')->cascadeOnDelete();
            $table->foreignId('ruangan_id')->nullable(); // Assuming ruangan is from another module
            $table->string('nama_sesi')->nullable();
            $table->date('tanggal')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->integer('kapasitas')->default(0);
            $table->enum('tipe_ujian', ['tulis', 'praktik', 'wawancara']);
            $table->timestamps(); // ERD only mentioned created_at, but typically Laravel uses both
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujian_spmb');
    }
};
