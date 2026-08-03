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
        if (!Schema::hasTable('mahasiswa_tipe_tagihan')) {
            Schema::create('mahasiswa_tipe_tagihan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id');
                $table->string('nim')->nullable();
                $table->string('nama_mahasiswa')->nullable();
                $table->integer('tahun_angkatan')->default(2025);
                $table->string('jalur_kelas')->default('Reguler');
                $table->integer('kelompok_ukt')->default(3);
                $table->foreignId('beasiswa_id')->nullable()->constrained('beasiswa')->onDelete('set null');
                $table->string('status_pendaftaran')->default('SPMB_DITERIMA');
                $table->text('catatan_perubahan')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('mahasiswa_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_tipe_tagihan');
    }
};
