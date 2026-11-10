<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siakad_nilai_koreksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_detail_id')
                ->constrained('siakad_krs_detail')
                ->cascadeOnDelete();
            $table->string('jenis_nilai', 30);
            $table->foreignId('nilai_mahasiswa_id')
                ->nullable()
                ->constrained('siakad_nilai_mahasiswa')
                ->nullOnDelete();
            $table->foreignId('nilai_komponen_mahasiswa_id')
                ->nullable()
                ->constrained('siakad_nilai_komponen_mhs')
                ->nullOnDelete();
            $table->foreignId('ketercapaian_cpmk_mahasiswa_id')
                ->nullable()
                ->constrained('siakad_ketercapaian_cpmk_mhs')
                ->nullOnDelete();
            $table->json('nilai_lama');
            $table->json('nilai_baru');
            $table->text('alasan_koreksi');
            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diajukan_oleh')
                ->nullable()
                ->constrained('core_users')
                ->nullOnDelete();
            $table->foreignId('diputuskan_oleh')
                ->nullable()
                ->constrained('core_users')
                ->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->text('catatan_reviewer')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'siakad_nilai_koreksi_status_created_idx');
            $table->index(['krs_detail_id', 'jenis_nilai'], 'siakad_nilai_koreksi_detail_jenis_idx');
            $table->index(['diajukan_oleh', 'status'], 'siakad_nilai_koreksi_pengaju_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_nilai_koreksi');
    }
};