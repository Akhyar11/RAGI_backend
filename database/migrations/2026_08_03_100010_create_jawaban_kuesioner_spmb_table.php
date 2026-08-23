<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_jawaban_kuesioner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('pertanyaan_id')->constrained('spmb_pertanyaan_kuesioner')->cascadeOnDelete();
            $table->text('jawaban')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_jawaban_kuesioner');
    }
};
