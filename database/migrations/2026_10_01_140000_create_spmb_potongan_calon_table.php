<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Potongan biaya kustom per calon mahasiswa (SPMB). Setiap baris
     * menunjuk tepat satu komponen biaya. Sumber data keputusan biaya
     * ada di SPMB; SIKEU hanya menerima hasilnya saat tagihan dibuat.
     */
    public function up(): void
    {
        Schema::create('spmb_potongan_calon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')
                ->constrained('spmb_pendaftaran_calon_mhs')
                ->cascadeOnDelete();
            $table->foreignId('komponen_biaya_id')
                ->constrained('spmb_master_komponen_biaya')
                ->restrictOnDelete();
            $table->string('nama_komponen', 150)->nullable()->comment('Snapshot nama komponen saat potongan dibuat');
            $table->string('nama_potongan');
            $table->enum('tipe_potongan', ['nominal', 'persen'])->default('nominal');
            $table->decimal('nilai_potongan', 15, 2)->default(0);
            $table->enum('tahap', ['pendaftaran', 'daftar_ulang', 'keduanya'])->default('keduanya');
            $table->string('nomor_sk')->nullable();
            $table->text('keterangan')->nullable();
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->enum('status', ['draft', 'aktif', 'dibatalkan'])->default('aktif');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pendaftaran_id', 'komponen_biaya_id'], 'spmb_potongan_calon_unique');
            $table->index(['pendaftaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_potongan_calon');
    }
};
