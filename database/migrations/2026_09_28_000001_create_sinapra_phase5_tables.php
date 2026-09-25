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
        // 1. Tabel Sesi Stock Opname Aset Fisik
        Schema::create('sinapra_stock_opname', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruangan_id')->constrained('sinapra_ruangan')->onDelete('cascade');
            $table->string('kode_opname', 50)->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->foreignId('petugas_user_id')->constrained('core_users')->onDelete('cascade');
            $table->enum('status', ['draft', 'berlangsung', 'selesai'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ruangan_id', 'status']);
        });

        // 2. Tabel Item Hasil Pemeriksaan Stock Opname
        Schema::create('sinapra_stock_opname_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('sinapra_stock_opname')->onDelete('cascade');
            $table->foreignId('aset_id')->constrained('sinapra_aset')->onDelete('cascade');
            $table->enum('status_keberadaan', ['sesuai', 'tidak_ditemukan', 'rusak', 'berlebih'])->default('sesuai');
            $table->enum('kondisi_fisik', ['baik', 'rusak_ringan', 'rusak_berat'])->default('baik');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['stock_opname_id', 'status_keberadaan']);
        });

        // 3. Tabel Mutasi Aset Antar-Ruangan / Antar-Lab
        Schema::create('sinapra_mutasi_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('sinapra_aset')->onDelete('cascade');
            $table->foreignId('ruangan_asal_id')->constrained('sinapra_ruangan')->onDelete('cascade');
            $table->foreignId('ruangan_tujuan_id')->constrained('sinapra_ruangan')->onDelete('cascade');
            $table->foreignId('pemohon_id')->constrained('core_users')->onDelete('cascade');
            $table->date('tanggal_pengajuan');
            $table->date('tanggal_disetujui')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->onDelete('set null');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->text('alasan');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aset_id', 'status']);
            $table->index(['ruangan_asal_id', 'ruangan_tujuan_id']);
        });

        // 4. Tabel Penghapusan / Pemutihan Aset (Asset Disposal)
        Schema::create('sinapra_disposal_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('sinapra_aset')->onDelete('cascade');
            $table->string('nomor_bap', 100)->unique();
            $table->date('tanggal_disposal');
            $table->enum('metode_disposal', ['pemusnahan', 'lelang', 'hibah', 'rusak_total'])->default('rusak_total');
            $table->decimal('nilai_residu', 15, 2)->default(0);
            $table->text('alasan');
            $table->foreignId('diajukan_oleh')->constrained('core_users')->onDelete('cascade');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->onDelete('set null');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aset_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_disposal_aset');
        Schema::dropIfExists('sinapra_mutasi_aset');
        Schema::dropIfExists('sinapra_stock_opname_item');
        Schema::dropIfExists('sinapra_stock_opname');
    }
};
