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
        Schema::create('pencairan_dana_hibah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontrak_id')->constrained('kontrak_kegiatan')->cascadeOnDelete();
            $table->integer('termin_ke');
            $table->decimal('persen_pencairan', 5, 2);
            $table->decimal('nominal', 15, 2);
            $table->enum('status', ['draft', 'pengajuan', 'disetujui', 'cair', 'ditolak'])->default('draft');
            $table->date('tgl_cair')->nullable();
            $table->string('bukti_transfer', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pencairan_dana_hibah');
    }
};
