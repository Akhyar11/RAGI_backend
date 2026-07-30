<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->onDelete('cascade');
            $table->integer('tahun');
            $table->enum('semester', ['ganjil', 'genap', 'tahunan'])->default('tahunan');
            $table->decimal('nilai_skp', 5, 2)->default(0);
            $table->decimal('nilai_bkd', 5, 2)->nullable();
            $table->enum('predikat', ['sangat_baik', 'baik', 'cukup', 'kurang', 'sangat_kurang'])->default('baik');
            $table->text('catatan_evaluator')->nullable();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_kinerja');
    }
};
