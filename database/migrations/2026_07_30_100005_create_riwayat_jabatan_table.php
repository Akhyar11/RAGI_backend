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
        Schema::create('simpeg_riwayat_jabatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->nullable()->constrained('simpeg_jabatan')->nullOnDelete();
            $table->foreignId('jabatan_fungsional_id')->nullable()->constrained('simpeg_jabatan_fungsional_akademik')->nullOnDelete();
            $table->date('mulai_jabatan')->nullable();
            $table->date('selesai_jabatan')->nullable();
            $table->string('sk_nomor')->nullable();
            $table->date('sk_tanggal')->nullable();
            $table->string('file_sk')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_riwayat_jabatan');
    }
};
