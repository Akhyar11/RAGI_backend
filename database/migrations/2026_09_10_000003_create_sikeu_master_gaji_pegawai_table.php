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
        if (!Schema::hasTable('sikeu_master_gaji_pegawai')) {
            Schema::create('sikeu_master_gaji_pegawai', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade')->unique();
                $table->decimal('gaji_pokok', 15, 2)->default(0);
                $table->decimal('tunjangan_tetap', 15, 2)->default(0);
                $table->decimal('potongan_tetap', 15, 2)->default(0);
                $table->decimal('tarif_transport_harian', 15, 2)->default(50000);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_master_gaji_pegawai');
    }
};
