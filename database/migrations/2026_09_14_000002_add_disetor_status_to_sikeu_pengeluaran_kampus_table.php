<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('sikeu_pengeluaran_kampus')) {
            DB::statement("ALTER TABLE sikeu_pengeluaran_kampus MODIFY status_pembayaran ENUM('lunas','pending','batal','disetor') NOT NULL DEFAULT 'lunas'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('sikeu_pengeluaran_kampus')) {
            DB::statement("ALTER TABLE sikeu_pengeluaran_kampus MODIFY status_pembayaran ENUM('lunas','pending','batal') NOT NULL DEFAULT 'lunas'");
        }
    }
};