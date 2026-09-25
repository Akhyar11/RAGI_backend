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
        Schema::table('lms_kelas_setting', function (Blueprint $table) {
            $table->renameColumn('allow_late_submission', 'can_submit_late');
        });

        Schema::table('lms_tugas', function (Blueprint $table) {
            $table->renameColumn('deadline', 'deadline_at');
            $table->renameColumn('allow_late_submission', 'can_submit_late');
        });

        Schema::table('lms_materi_pertemuan', function (Blueprint $table) {
            $table->foreignId('tipe_konten_id')->nullable()->after('deskripsi')->constrained('spmb_master_referensi')->nullOnDelete();
        });

        Schema::table('lms_izin_absensi', function (Blueprint $table) {
            $table->foreignId('tipe_izin_id')->nullable()->after('mahasiswa_id')->constrained('spmb_master_referensi')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_izin_absensi', function (Blueprint $table) {
            $table->dropForeign(['tipe_izin_id']);
            $table->dropColumn('tipe_izin_id');
        });

        Schema::table('lms_materi_pertemuan', function (Blueprint $table) {
            $table->dropForeign(['tipe_konten_id']);
            $table->dropColumn('tipe_konten_id');
        });

        Schema::table('lms_tugas', function (Blueprint $table) {
            $table->renameColumn('can_submit_late', 'allow_late_submission');
            $table->renameColumn('deadline_at', 'deadline');
        });

        Schema::table('lms_kelas_setting', function (Blueprint $table) {
            $table->renameColumn('can_submit_late', 'allow_late_submission');
        });
    }
};
