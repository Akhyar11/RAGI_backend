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
        Schema::create('proposal_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode_hibah')->cascadeOnDelete();
            $table->foreignId('skema_id')->constrained('skema_kegiatan')->cascadeOnDelete();
            $table->foreignId('ketua_pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->unsignedBigInteger('mitra_kerjasama_id')->nullable(); // FK to KERJASAMA mitra
            $table->unsignedBigInteger('mata_kuliah_id')->nullable(); // FK to SIAKAD mata_kuliah (Integrasi konversi nilai)
            $table->string('kode_proposal', 100)->unique();
            $table->text('judul');
            $table->text('abstrak');
            $table->string('rumpun_ilmu', 150);
            $table->integer('target_tkt')->default(1);
            $table->decimal('anggaran_diajukan', 15, 2);
            $table->decimal('anggaran_disetujui', 15, 2)->default(0.00);
            $table->string('file_proposal', 255);
            $table->string('status', 50)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_kegiatan');
    }
};
