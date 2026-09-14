<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('simpeg_presensi_periode')) {
            Schema::create('simpeg_presensi_periode', function (Blueprint $table) {
                $table->id();
                $table->string('nama_periode');
                $table->date('tanggal_awal');
                $table->date('tanggal_akhir');
                $table->string('bulan_tahun')->nullable();
                $table->integer('total_record')->default(0);
                $table->text('catatan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('core_users')->onDelete('set null');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('simpeg_presensi_pegawai') && !Schema::hasColumn('simpeg_presensi_pegawai', 'presensi_periode_id')) {
            Schema::table('simpeg_presensi_pegawai', function (Blueprint $table) {
                $table->foreignId('presensi_periode_id')->nullable()->after('id')->constrained('simpeg_presensi_periode')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('simpeg_presensi_pegawai') && Schema::hasColumn('simpeg_presensi_pegawai', 'presensi_periode_id')) {
            Schema::table('simpeg_presensi_pegawai', function (Blueprint $table) {
                $table->dropForeign(['presensi_periode_id']);
                $table->dropColumn('presensi_periode_id');
            });
        }

        Schema::dropIfExists('simpeg_presensi_periode');
    }
};
