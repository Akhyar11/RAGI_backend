<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Master Kategori Kebijakan & Pedoman SIMPEG
        Schema::create('simpeg_master_kategori_kebijakan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Kebijakan dan Pedoman Kepegawaian
        Schema::create('simpeg_kebijakan_pedoman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomor_surat_arsip_id')->nullable()->constrained('core_arsip_nomor_surat')->onDelete('set null');
            $table->string('nomor_surat', 150)->index();
            $table->string('judul', 255);
            $table->foreignId('kategori_kebijakan_id')->nullable()->constrained('simpeg_master_kategori_kebijakan')->onDelete('set null');
            $table->date('tanggal_penetapan');
            $table->date('tanggal_berlaku')->nullable();
            $table->string('pejabat_penetap', 150)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('file_dokumen_path');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'tanggal_penetapan']);
            $table->index(['kategori_kebijakan_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_kebijakan_pedoman');
        Schema::dropIfExists('simpeg_master_kategori_kebijakan');
    }
};
