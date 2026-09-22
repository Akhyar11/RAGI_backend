<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Perluasan status konversi transfer agar selaras dengan validasi controller:
        // draft -> diajukan -> disetujui / ditolak
        // SQLite tidak enforce enum -> skip agar test in-memory tetap jalan.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (Schema::hasTable('siakad_konversi_transfer')) {
            DB::statement("ALTER TABLE siakad_konversi_transfer MODIFY COLUMN status ENUM('draft','diajukan','disetujui','ditolak') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (Schema::hasTable('siakad_konversi_transfer')) {
            DB::statement("ALTER TABLE siakad_konversi_transfer MODIFY COLUMN status ENUM('draft','disetujui') NOT NULL DEFAULT 'draft'");
        }
    }
};
