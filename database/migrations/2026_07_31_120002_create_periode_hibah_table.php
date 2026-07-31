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
        Schema::create('periode_hibah', function (Blueprint $table) {
            $table->id();
            $table->integer('tahun_anggaran');
            $table->string('nama_gelombang', 100);
            $table->date('tgl_buka_proposal');
            $table->date('tgl_tutup_proposal');
            $table->date('tgl_tutup_monev')->nullable();
            $table->date('tgl_tutup_laporan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periode_hibah');
    }
};
