<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpeg_gaji_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->string('periode_bulan_tahun'); // e.g. "2026-07"
            $table->decimal('gaji_pokok', 12, 2)->default(0);
            $table->decimal('total_tunjangan', 12, 2)->default(0);
            $table->decimal('total_potongan', 12, 2)->default(0);
            $table->decimal('gaji_bersih', 12, 2)->default(0);
            $table->enum('status_transfer', ['draft', 'paid', 'cancelled'])->default('draft');
            $table->timestamp('tanggal_transfer')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('bank_nama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_pegawai');
    }
};
