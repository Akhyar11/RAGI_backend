<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu Koreksi Alokasi Pembayaran SIKEU.
     *
     * Prinsip: histori pembayaran/tagihan TIDAK di-rewrite. Koreksi hanya
     * memindahkan ALOKASI (detail_tagihan.terbayar + tagihan.total_bayar)
     * antar tagihan milik mahasiswa yang sama, dengan jejak from->to.
     * Kekurangan otomatis diterbitkan sebagai tagihan baru
     * (source_system = sikeu_koreksi_alokasi).
     */
    public function up(): void
    {
        if (!Schema::hasTable('sikeu_koreksi_alokasi')) {
            Schema::create('sikeu_koreksi_alokasi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mahasiswa_id')->index();
                $table->unsignedBigInteger('calon_mahasiswa_id')->nullable()->index();
                $table->string('tipe_referensi', 30)->default('mahasiswa');
                $table->string('nomor_koreksi')->unique();
                $table->unsignedBigInteger('tahun_akademik_id')->nullable()->index();
                $table->integer('semester')->nullable()->index();
                $table->text('alasan');
                $table->string('referensi')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'cancelled'])->default('pending')->index();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->text('catatan_approval')->nullable();
                $table->decimal('total_nominal_dialihkan', 15, 2)->default(0);
                $table->boolean('buat_tagihan_kekurangan')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('requested_by')->references('id')->on('core_users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('core_users')->onDelete('set null');
                $table->foreign('rejected_by')->references('id')->on('core_users')->onDelete('set null');
                $table->index(['mahasiswa_id', 'status']);
                $table->index(['created_at']);
            });
        }

        if (!Schema::hasTable('sikeu_koreksi_alokasi_detail')) {
            Schema::create('sikeu_koreksi_alokasi_detail', function (Blueprint $table) {
                $table->id();
                $table->foreignId('koreksi_alokasi_id')->constrained('sikeu_koreksi_alokasi')->onDelete('cascade');
                $table->foreignId('from_tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('restrict');
                $table->foreignId('from_detail_tagihan_id')->nullable()->constrained('sikeu_detail_tagihan')->onDelete('restrict');
                $table->foreignId('to_tagihan_id')->constrained('sikeu_tagihan_mahasiswa')->onDelete('restrict');
                $table->foreignId('to_detail_tagihan_id')->nullable()->constrained('sikeu_detail_tagihan')->onDelete('restrict');
                $table->decimal('nominal_dialihkan', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['koreksi_alokasi_id']);
                $table->index(['from_tagihan_id']);
                $table->index(['to_tagihan_id']);
            });
        }

        // Jejak balik: tagihan kekurangan hasil koreksi menunjuk ke koreksinya.
        if (Schema::hasTable('sikeu_tagihan_mahasiswa') && !Schema::hasColumn('sikeu_tagihan_mahasiswa', 'koreksi_alokasi_id')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                $table->foreignId('koreksi_alokasi_id')->nullable()->after('source_system')->constrained('sikeu_koreksi_alokasi')->onDelete('set null');
                $table->index(['koreksi_alokasi_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_tagihan_mahasiswa') && Schema::hasColumn('sikeu_tagihan_mahasiswa', 'koreksi_alokasi_id')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                try {
                    $table->dropForeign(['koreksi_alokasi_id']);
                } catch (\Throwable $e) {
                }
                try {
                    $table->dropColumn('koreksi_alokasi_id');
                } catch (\Throwable $e) {
                }
            });
        }

        Schema::dropIfExists('sikeu_koreksi_alokasi_detail');
        Schema::dropIfExists('sikeu_koreksi_alokasi');
    }
};
