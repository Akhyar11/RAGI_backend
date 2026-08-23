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
        Schema::create('sippm_pengumuman_hibah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->nullable()->constrained('sippm_periode_hibah')->nullOnDelete();
            $table->string('nomor_surat', 100);
            $table->date('tgl_surat');
            $table->string('hal_surat', 255);
            $table->string('tahun_anggaran', 20);
            $table->text('tujuan_yth')->nullable();
            $table->text('kualifikasi_dosen')->nullable();
            $table->string('kategori_pendanaan', 255)->default('Monotahun (dana riset dan dana luaran)');
            $table->date('tgl_buka_proposal');
            $table->date('tgl_tutup_proposal');
            $table->string('nama_ketua_uppm', 150);
            $table->string('nik_ketua_uppm', 50)->nullable();
            $table->string('nama_direktur', 150);
            $table->string('nik_direktur', 50)->nullable();
            $table->string('file_draft_pdf_path', 255)->nullable();
            $table->string('file_signed_pdf_path', 255)->nullable();
            $table->string('file_template_mitra_indo_path', 255)->nullable();
            $table->string('file_template_mitra_intl_path', 255)->nullable();
            $table->enum('status', ['draft', 'pending_scan', 'published'])->default('draft');
            $table->json('lampiran_jadwal')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tahun_anggaran']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_pengumuman_hibah');
    }
};
