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
        // 1. Master Jenis Izin Jam Kerja (Zero Hardcode)
        Schema::create('simpeg_master_jenis_izin_jam_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kode', 50)->unique();
            $table->enum('tipe_potongan', ['tidak_potong', 'potong_jam'])->default('tidak_potong');
            $table->text('deskripsi')->nullable();
            $table->integer('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Transaksional Izin Jam Kerja (Parsial)
        Schema::create('simpeg_izin_jam_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->onDelete('cascade');
            $table->foreignId('master_jenis_izin_id')->constrained('simpeg_master_jenis_izin_jam_kerja')->onDelete('restrict');
            $table->date('tanggal')->index();
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->text('alasan');
            $table->string('file_bukti', 255)->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu')->index();
            $table->text('catatan_approval')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pegawai_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_izin_jam_kerja');
        Schema::dropIfExists('simpeg_master_jenis_izin_jam_kerja');
    }
};
