<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_jafung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->onDelete('cascade');
            $table->foreignId('jafung_asal_id')->nullable()->constrained('jabatan_fungsional_akademik')->nullOnDelete();
            $table->foreignId('jafung_tujuan_id')->constrained('jabatan_fungsional_akademik')->onDelete('cascade');
            $table->integer('angka_kredit_usulan');
            $table->enum('status_usulan', ['draft', 'submitted', 'diverifikasi', 'disetujui', 'ditolak'])->default('draft');
            $table->string('file_sk_hasil')->nullable();
            $table->text('catatan_reviewer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_jafung');
    }
};
