<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_hasil_seleksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('program_studi_diterima_id')->nullable();
            $table->decimal('nilai_total', 8, 2)->default(0);
            $table->integer('peringkat')->nullable();
            $table->enum('status', ['lulus', 'tidak_lulus', 'cadangan', 'mengundurkan_diri'])->default('tidak_lulus');
            $table->text('catatan')->nullable();
            $table->timestamp('diumumkan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_hasil_seleksi');
    }
};
