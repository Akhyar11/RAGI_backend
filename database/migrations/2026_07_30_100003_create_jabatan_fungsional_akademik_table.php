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
        Schema::create('jabatan_fungsional_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->integer('angka_kredit_min')->nullable();
            $table->integer('angka_kredit_max')->nullable();
            $table->enum('golongan', ['asisten_ahli', 'lektor', 'lektor_kepala', 'guru_besar']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jabatan_fungsional_akademik');
    }
};
