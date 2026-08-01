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
        Schema::create('kontrak_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal_kegiatan')->cascadeOnDelete();
            $table->string('nomor_kontrak', 100)->unique();
            $table->decimal('dana_disetujui', 15, 2);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->string('file_kontrak', 255)->nullable();
            $table->enum('status', ['aktif', 'selesai', 'dibatalkan'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrak_kegiatan');
    }
};
