<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan peserta ujian:
 * - status: terjadwal | hadir | tidak_hadir (string, bukan enum — kebijakan repo)
 * - waktu_mulai / waktu_selesai: penanda waktu pengerjaan sesi CAT
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_spmb', function (Blueprint $table) {
            $table->string('status', 20)->default('terjadwal')->after('hadir');
            $table->timestamp('waktu_mulai')->nullable()->after('status');
            $table->timestamp('waktu_selesai')->nullable()->after('waktu_mulai');
            $table->index(['jadwal_ujian_id', 'status'], 'peserta_jadwal_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_spmb', function (Blueprint $table) {
            $table->dropIndex('peserta_jadwal_status_index');
            $table->dropColumn(['waktu_selesai', 'waktu_mulai', 'status']);
        });
    }
};
