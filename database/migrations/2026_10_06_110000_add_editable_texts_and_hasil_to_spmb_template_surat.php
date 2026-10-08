<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah parameter teks yang dapat diedit penuh (WYSIWYG) + logika keputusan
     * Diterima/Ditolak pada template surat SPMB.
     */
    public function up(): void
    {
        Schema::table('spmb_template_surat', function (Blueprint $table) {
            $table->string('hasil', 20)->default('diterima')->after('jenis_surat');
            $table->string('label_keputusan', 150)->nullable()->after('teks_keputusan');
            $table->text('teks_pernyataan')->nullable()->after('teks_pembuka');
            $table->string('teks_prodi', 255)->nullable()->after('teks_keputusan');
            $table->text('teks_penutup')->nullable()->after('teks_keputusan');
            $table->string('judul_petunjuk', 150)->nullable()->after('petunjuk_daftar_ulang');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_template_surat', function (Blueprint $table) {
            $table->dropColumn([
                'hasil',
                'label_keputusan',
                'teks_pernyataan',
                'teks_prodi',
                'teks_penutup',
                'judul_petunjuk',
            ]);
        });
    }
};
