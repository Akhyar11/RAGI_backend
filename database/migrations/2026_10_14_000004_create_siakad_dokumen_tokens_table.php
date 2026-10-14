<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token validasi publik dokumen akademik (KHS per semester & Transkrip
        // kumulatif): QR pada dokumen cetak memuat URL validasi berbasis token
        // acak + snapshot nilai saat diterbitkan sebagai bukti anti-tamper.
        if (!Schema::hasTable('siakad_dokumen_tokens')) {
            Schema::create('siakad_dokumen_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('jenis', 20); // KHS | TRANSKRIP (closed-set domain)
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->foreignId('tahun_akademik_id')->nullable()->constrained('siakad_tahun_akademik')->cascadeOnDelete();
                $table->string('nomor_dokumen', 60)->unique();
                $table->string('token', 80)->unique();
                $table->json('snapshot')->nullable();
                $table->timestamp('tanggal_terbit')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['mahasiswa_id', 'jenis', 'is_active'], 'siakad_dok_token_mhs_idx');
                $table->index(['token', 'is_active'], 'siakad_dok_token_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_dokumen_tokens');
    }
};
