<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan pencairan dari modul eksternal (misal: SIMPEG Surat Tugas) diajukan sebelum
 * unit kas ditentukan oleh Bagian Keuangan. Kolom unit_kas_id pada sikeu_pengajuan_pencairan_kas
 * dibuat nullable agar antrean pengajuan dapat disimpan dan unit kas ditentukan saat approval keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')
            && Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'unit_kas_id')) {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                $table->unsignedBigInteger('unit_kas_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')
            && Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'unit_kas_id')) {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                $table->unsignedBigInteger('unit_kas_id')->nullable(false)->change();
            });
        }
    }
};
