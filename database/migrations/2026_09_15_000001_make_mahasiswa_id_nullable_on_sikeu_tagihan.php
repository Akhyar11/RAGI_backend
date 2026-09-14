<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan eksternal (SPMB/SIPPM) hanya memiliki calon_mahasiswa_id tanpa
 * mahasiswa_id. Kolom mahasiswa_id pada sikeu_tagihan_mahasiswa dibuat nullable
 * agar tagihan calon mahasiswa bisa disimpan tanpa mengisi mahasiswa_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sikeu_tagihan_mahasiswa')
            && Schema::hasColumn('sikeu_tagihan_mahasiswa', 'mahasiswa_id')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                $table->unsignedBigInteger('mahasiswa_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sikeu_tagihan_mahasiswa')
            && Schema::hasColumn('sikeu_tagihan_mahasiswa', 'mahasiswa_id')) {
            Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
                $table->unsignedBigInteger('mahasiswa_id')->nullable(false)->change();
            });
        }
    }
};