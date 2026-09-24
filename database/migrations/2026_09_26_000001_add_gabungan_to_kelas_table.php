<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_kelas', function (Blueprint $table) {
            $table->boolean('is_gabungan')->default(false)->after('status');
        });

        Schema::create('siakad_kelas_program_studi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
            $table->foreignId('program_studi_id')->constrained('siakad_program_studi')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'program_studi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_kelas_program_studi');
        Schema::table('siakad_kelas', function (Blueprint $table) {
            $table->dropColumn('is_gabungan');
        });
    }
};
