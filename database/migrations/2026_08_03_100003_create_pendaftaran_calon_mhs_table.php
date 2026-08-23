<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_pendaftaran_calon_mhs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('spmb_gelombang_penerimaan')->cascadeOnDelete();
            $table->foreignId('user_id'); 
            $table->foreignId('program_studi_id');
            $table->foreignId('program_studi_pilihan2_id')->nullable();
            $table->string('no_pendaftaran')->unique();
            $table->string('nama_lengkap');
            $table->string('nik')->unique();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->text('alamat')->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('jurusan_sekolah')->nullable();
            $table->decimal('nilai_rata_rapor', 5, 2)->nullable();
            $table->string('tahun_lulus')->nullable();
            $table->string('nama_wali')->nullable();
            $table->string('telepon_wali')->nullable();
            $table->enum('status', ['draft', 'submitted', 'verified', 'lulus_administrasi', 'gagal_administrasi'])->default('draft');
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pendaftaran_calon_mhs');
    }
};
