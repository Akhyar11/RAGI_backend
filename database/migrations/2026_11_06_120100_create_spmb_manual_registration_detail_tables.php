<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_pendaftaran_pihak_ketiga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->string('nama', 255);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
            $table->index(['pendaftaran_id', 'urutan']);
        });

        Schema::create('spmb_pendaftaran_bukti_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('status', 30)->default('menunggu_verifikasi');
            $table->foreignId('diunggah_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
            $table->index(['pendaftaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pendaftaran_bukti_pembayaran');
        Schema::dropIfExists('spmb_pendaftaran_pihak_ketiga');
    }
};
