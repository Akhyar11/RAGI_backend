<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_pertemuan')) {
            Schema::create('siakad_pertemuan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
                $table->integer('pertemuan_ke'); // 1 s/d 16
                $table->date('tanggal');
                $table->string('materi')->nullable();
                $table->time('jam_mulai')->nullable();
                $table->time('jam_selesai')->nullable();
                $table->timestamps();

                $table->unique(['kelas_id', 'pertemuan_ke']);
            });
        }

        if (!Schema::hasTable('siakad_absensi_mahasiswa')) {
            Schema::create('siakad_absensi_mahasiswa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pertemuan_id')->constrained('siakad_pertemuan')->cascadeOnDelete();
                $table->foreignId('mahasiswa_id')->constrained('siakad_mahasiswa')->cascadeOnDelete();
                $table->string('status', 20)->default('hadir'); // hadir, sakit, izin, alfa
                $table->string('catatan')->nullable();
                $table->timestamps();

                $table->unique(['pertemuan_id', 'mahasiswa_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_absensi_mahasiswa');
        Schema::dropIfExists('siakad_pertemuan');
    }
};
