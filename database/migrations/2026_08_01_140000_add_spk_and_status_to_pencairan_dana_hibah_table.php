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
        Schema::table('sippm_kontrak_kegiatan', function (Blueprint $table) {
            if (!Schema::hasColumn('kontrak_kegiatan', 'file_spk_ttd')) {
                $table->string('file_spk_ttd', 255)->nullable()->after('file_kontrak');
            }
            if (!Schema::hasColumn('kontrak_kegiatan', 'status_spk')) {
                $table->string('status_spk', 50)->default('waiting_upload')->after('file_spk_ttd');
            }
        });

        Schema::table('sippm_pencairan_dana_hibah', function (Blueprint $table) {
            if (!Schema::hasColumn('pencairan_dana_hibah', 'status_termin')) {
                $table->string('status_termin', 50)->default('waiting_document')->after('status');
            }
            if (!Schema::hasColumn('pencairan_dana_hibah', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable()->after('bukti_transfer');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sippm_kontrak_kegiatan', function (Blueprint $table) {
            $table->dropColumn(['file_spk_ttd', 'status_spk']);
        });

        Schema::table('sippm_pencairan_dana_hibah', function (Blueprint $table) {
            $table->dropColumn(['status_termin', 'catatan_verifikasi']);
        });
    }
};
