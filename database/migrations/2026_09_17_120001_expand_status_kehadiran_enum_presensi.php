<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perbaikan bug prod: AttendanceService menulis status 'terlambat',
     * 'menunggu_approval', dan 'ditolak' ke kolom status_kehadiran, tetapi
     * ENUM hanya berisi ['hadir','izin','sakit','alfa','dinas'] sehingga
     * MySQL melempar "Data truncated" dan SEMUA insert presensi gagal
     * (bahkan yang skor wajahnya lolos). ENUM diperluas mengikuti semua
     * status yang ditulis service.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE simpeg_presensi_pegawai MODIFY status_kehadiran " .
            "ENUM('hadir','terlambat','menunggu_approval','ditolak','izin','sakit','dinas','alfa') " .
            "NOT NULL DEFAULT 'hadir'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Kembalikan ke definisi awal. Baris berstatus di luar daftar awal
        // akan gagal pada downgrade bila masih ada — bersihkan dulu manual.
        DB::statement(
            "ALTER TABLE simpeg_presensi_pegawai MODIFY status_kehadiran " .
            "ENUM('hadir','izin','sakit','alfa','dinas') NOT NULL DEFAULT 'hadir'"
        );
    }
};
