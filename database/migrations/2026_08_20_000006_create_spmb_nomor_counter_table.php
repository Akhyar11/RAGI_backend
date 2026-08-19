<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counter nomor berurutan per (tahun akademik, kode prodi) untuk NIM dan nomor pendaftaran.
     * Di-lock dengan lockForUpdate() di dalam DB::transaction agar race-safe.
     */
    public function up(): void
    {
        Schema::create('spmb_nomor_counter', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_akademik', 4)->index();
            $table->string('kode_prodi', 20)->index();
            $table->integer('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['tahun_akademik', 'kode_prodi'], 'spmb_nomor_counter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_nomor_counter');
    }
};
