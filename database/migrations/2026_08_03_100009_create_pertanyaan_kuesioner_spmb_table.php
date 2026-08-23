<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_pertanyaan_kuesioner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuesioner_id')->constrained('spmb_kuesioner')->cascadeOnDelete();
            $table->text('pertanyaan');
            $table->enum('tipe', ['text', 'radio', 'checkbox', 'scale']);
            $table->json('opsi_jawaban')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('urutan')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pertanyaan_kuesioner');
    }
};
