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
        Schema::create('sinapra_peminjaman_ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruangan_id')->constrained('sinapra_ruangan')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->string('keperluan', 255);
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->enum('status', ['pending', 'disetujui', 'ditolak', 'selesai', 'dibatalkan'])->default('pending');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->onDelete('set null');
            $table->text('catatan_penolakan')->nullable();
            $table->timestamps();

            $table->index(['ruangan_id', 'user_id', 'tanggal', 'status'], 'idx_pinjam_ruang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_peminjaman_ruangan');
    }
};
