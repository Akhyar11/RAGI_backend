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
        Schema::create('sinapra_pengajuan_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('simpeg_unit_kerja')->onDelete('set null');
            $table->foreignId('diajukan_oleh')->constrained('core_users')->onDelete('cascade');
            $table->string('judul', 150);
            $table->text('alasan_kebutuhan')->nullable();
            $table->date('tanggal_pengajuan');
            $table->decimal('estimasi_anggaran', 15, 2)->default(0.00);
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'ditolak', 'proses_pengadaan', 'selesai'])->default('draft');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_kerja_id', 'diajukan_oleh', 'status'], 'idx_pengajuan_pengadaan_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_pengajuan_pengadaan');
    }
};
