<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perpanjang `siakad_pertemuan` menjadi satu-satunya sumber jurnal presensi
     * lokal RAG. Tidak ada tabel jurnal terpisah dan tidak ada koneksi ke
     * PresensiV2 — kolom historis `id_jurnal_presensiv2` sengaja tidak disentuh.
     *
     * `ruangan_id` merujuk ke master ruangan SINAPRA (`sinapra_ruangan`),
     * bukan label statis. Bukti pembelajaran disimpan sebagai path relatif
     * pada disk privat melalui FileStorageService.
     */
    public function up(): void
    {
        if (! Schema::hasTable('siakad_pertemuan')) {
            return;
        }

        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            if (! Schema::hasColumn('siakad_pertemuan', 'jenis_kegiatan')) {
                $table->string('jenis_kegiatan', 100)->nullable()->after('catatan_pertemuan');
            }
            if (! Schema::hasColumn('siakad_pertemuan', 'ruangan_id')) {
                $table->foreignId('ruangan_id')->nullable()->after('jenis_kegiatan')
                    ->constrained('sinapra_ruangan')->nullOnDelete();
            }
            if (! Schema::hasColumn('siakad_pertemuan', 'bukti_pembelajaran_path')) {
                $table->string('bukti_pembelajaran_path', 512)->nullable()->after('ruangan_id');
            }
            if (! Schema::hasColumn('siakad_pertemuan', 'bukti_pembelajaran_disk')) {
                $table->string('bukti_pembelajaran_disk', 64)->nullable()->after('bukti_pembelajaran_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('siakad_pertemuan')) {
            return;
        }

        Schema::table('siakad_pertemuan', function (Blueprint $table) {
            // Kebalikan urutan up(): kolom turunan lebih dulu, FK sebelum kolomnya.
            if (Schema::hasColumn('siakad_pertemuan', 'bukti_pembelajaran_disk')) {
                $table->dropColumn('bukti_pembelajaran_disk');
            }
            if (Schema::hasColumn('siakad_pertemuan', 'bukti_pembelajaran_path')) {
                $table->dropColumn('bukti_pembelajaran_path');
            }
            if (Schema::hasColumn('siakad_pertemuan', 'ruangan_id')) {
                $table->dropConstrainedForeignId('ruangan_id');
            }
            if (Schema::hasColumn('siakad_pertemuan', 'jenis_kegiatan')) {
                $table->dropColumn('jenis_kegiatan');
            }
        });
    }
};
