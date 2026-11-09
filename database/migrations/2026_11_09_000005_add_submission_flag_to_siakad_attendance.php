<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_absensi_mahasiswa')) {
            return;
        }

        if (!Schema::hasColumn('siakad_absensi_mahasiswa', 'is_submitted')) {
            Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
                $table->boolean('is_submitted')->default(true)->after('status');
            });
        }

        DB::table('siakad_absensi_mahasiswa')
            ->where('status', 'hadir')
            ->whereNull('catatan')
            ->whereColumn('created_at', 'updated_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('siakad_pertemuan')
                    ->whereColumn('siakad_pertemuan.id', 'siakad_absensi_mahasiswa.pertemuan_id')
                    ->whereColumn('siakad_pertemuan.created_at', 'siakad_absensi_mahasiswa.created_at');
            })
            ->update(['is_submitted' => false]);
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_absensi_mahasiswa') && Schema::hasColumn('siakad_absensi_mahasiswa', 'is_submitted')) {
            Schema::table('siakad_absensi_mahasiswa', function (Blueprint $table) {
                $table->dropColumn('is_submitted');
            });
        }
    }
};
