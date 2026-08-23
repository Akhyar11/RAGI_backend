<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_jalur_masuk', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->enum('tipe', ['reguler', 'transfer', 'beasiswa', 'internasional', 'rpla']);
            $table->boolean('ada_ujian_tulis')->default(false);
            $table->boolean('ada_ujian_praktik')->default(false);
            $table->boolean('ada_wawancara')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_jalur_masuk');
    }
};
