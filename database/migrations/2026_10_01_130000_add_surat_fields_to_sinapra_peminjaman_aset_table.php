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
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'nomor_surat')) {
                $table->string('nomor_surat', 100)->nullable()->after('kode_peminjaman')->index();
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'surat_generated_at')) {
                $table->timestamp('surat_generated_at')->nullable()->after('nomor_surat');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_aset', 'catatan_pengembalian')) {
                $table->text('catatan_pengembalian')->nullable()->after('kondisi_kembali');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_peminjaman_aset', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'nomor_surat')) {
                $cols[] = 'nomor_surat';
            }
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'surat_generated_at')) {
                $cols[] = 'surat_generated_at';
            }
            if (Schema::hasColumn('sinapra_peminjaman_aset', 'catatan_pengembalian')) {
                $cols[] = 'catatan_pengembalian';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
