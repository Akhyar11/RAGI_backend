<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token kartu ujian UTS/UAS per mahasiswa per periode (pengganti
        // kartu_ujian_tokens legacy): QR kartu memuat URL validasi publik
        // berbasis token acak, bukan NIM, agar tidak bisa ditebak.
        if (!Schema::hasTable('siakad_kartu_ujian_tokens')) {
            Schema::create('siakad_kartu_ujian_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->constrained('siakad_tahun_akademik')->cascadeOnDelete();
                $table->string('tipe_ujian', 10); // UTS | UAS (closed-set domain)
                $table->string('token', 80)->unique();
                $table->timestamp('tanggal_cetak')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['mahasiswa_id', 'tahun_akademik_id', 'tipe_ujian'], 'siakad_kartu_ujian_unique');
                $table->index(['token', 'is_active'], 'siakad_kartu_ujian_token_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_kartu_ujian_tokens');
    }
};
