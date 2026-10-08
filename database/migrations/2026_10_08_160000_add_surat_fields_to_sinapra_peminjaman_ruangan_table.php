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
        Schema::table('sinapra_peminjaman_ruangan', function (Blueprint $table) {
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'kode_peminjaman')) {
                $table->string('kode_peminjaman', 100)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'nomor_surat')) {
                $table->string('nomor_surat', 100)->nullable()->after('kode_peminjaman')->index();
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'surat_generated_at')) {
                $table->timestamp('surat_generated_at')->nullable()->after('nomor_surat');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'nomor_identitas')) {
                $table->string('nomor_identitas', 100)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('sinapra_peminjaman_ruangan', 'kontak_peminjam')) {
                $table->string('kontak_peminjam', 100)->nullable()->after('nomor_identitas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_peminjaman_ruangan', function (Blueprint $table) {
            $cols = [];
            foreach (['kode_peminjaman', 'nomor_surat', 'surat_generated_at', 'nomor_identitas', 'kontak_peminjam'] as $col) {
                if (Schema::hasColumn('sinapra_peminjaman_ruangan', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
