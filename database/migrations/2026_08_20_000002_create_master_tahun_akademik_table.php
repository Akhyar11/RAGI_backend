<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Tahun Akademik lokal SPMB (sementara sampai modul SIAKAD tersedia).
     */
    public function up(): void
    {
        Schema::create('spmb_master_tahun_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->integer('tahun_mulai');
            $table->integer('tahun_selesai');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_master_tahun_akademik');
    }
};
