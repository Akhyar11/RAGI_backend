<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix CHECK constraint status_pembayaran di SQLite agar nilai 'disetor'
 * (fitur Setor NTPN Pajak) dapat disimpan.
 *
 * Latar belakang: migrasi 2026_09_14_000002 hanya mengubah ENUM saat driver
 * MySQL. Di SQLite, Laravel menjadikan kolom enum sebagai `varchar` dengan
 * CHECK constraint, sehingga kolom `sikeu_pengeluaran_kampus.status_pembayaran`
 * tetap hanya menerima ('lunas','pending','batal') dan POST
 * /pajak/{id}/setor gagal dengan "CHECK constraint failed: status_pembayaran".
 *
 * Migrasi ini aman & idempotent: hanya bekerja di SQLite dan melewatinya
 * (return) bila CHECK sudah memuat 'disetor'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (!Schema::hasTable('sikeu_pengeluaran_kampus')) {
            return;
        }

        $ddl = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'sikeu_pengeluaran_kampus'");
        if ($ddl && is_string($ddl->sql) && str_contains($ddl->sql, 'disetor')) {
            return; // sudah dalam keadaan benar
        }

        // Rebuild tabel (SQLite) dengan CHECK constraint yang menerima 'disetor'.
        Schema::table('sikeu_pengeluaran_kampus', function (Blueprint $table) {
            $table->enum('status_pembayaran', ['lunas', 'pending', 'batal', 'disetor'])->default('lunas')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (!Schema::hasTable('sikeu_pengeluaran_kampus')) {
            return;
        }

        Schema::table('sikeu_pengeluaran_kampus', function (Blueprint $table) {
            $table->enum('status_pembayaran', ['lunas', 'pending', 'batal'])->default('lunas')->change();
        });
    }
};