<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Program Studi lokal SPMB (sementara sampai modul SIAKAD tersedia).
     */
    public function up(): void
    {
        Schema::create('master_program_studi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_prodi', 20)->unique();
            $table->string('nama');
            $table->string('jenjang', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_program_studi');
    }
};
