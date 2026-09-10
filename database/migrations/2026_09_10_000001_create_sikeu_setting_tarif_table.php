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
        Schema::create('sikeu_setting_tarif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_biaya_id')->constrained('sikeu_master_biaya')->onDelete('cascade');
            $table->integer('tahun_angkatan');
            $table->unsignedBigInteger('program_studi_id')->nullable();
            $table->integer('semester')->nullable()->comment('Semester 1-8, null = berlaku semua semester');
            $table->string('jalur_kelas')->default('Reguler');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['master_biaya_id', 'tahun_angkatan', 'jalur_kelas'], 'setting_tarif_biaya_angkatan_jalur_idx');
            $table->unique(
                ['master_biaya_id', 'tahun_angkatan', 'program_studi_id', 'semester', 'jalur_kelas'],
                'setting_tarif_unique_combo'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sikeu_setting_tarif');
    }
};
