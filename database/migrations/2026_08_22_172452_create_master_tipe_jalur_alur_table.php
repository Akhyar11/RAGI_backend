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
        Schema::create('master_tipe_jalur_alur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_tipe_jalur_id')->constrained('master_tipe_jalur')->cascadeOnDelete();
            $table->string('nama_tahap');
            $table->integer('urutan')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_tipe_jalur_alur');
    }
};
