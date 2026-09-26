<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan'");
            } else {
                Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                    $table->string('status', 50)->default('diajukan')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sikeu_pengajuan_pencairan_kas')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN status ENUM('draft','diajukan','pending_sarpras','pending_keuangan','pending_direktur','pending_pimpinan','disetujui','ditolak','dicairkan','lpj_pending','lpj_disetujui','selesai') NOT NULL DEFAULT 'diajukan'");
            }
        }
    }
};
