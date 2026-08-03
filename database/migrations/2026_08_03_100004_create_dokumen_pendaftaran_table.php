<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->enum('jenis_dokumen', ['ijazah', 'rapor', 'ktp', 'foto', 'lainnya']);
            $table->string('file_path');
            $table->boolean('is_verified')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps(); // ERD only mentioned created_at, but typically Laravel uses both
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendaftaran');
    }
};
