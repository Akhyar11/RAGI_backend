<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KRS historis dari Neo Feeder berbasis mata kuliah (tanpa kelas konkret).
 * Aditif: kelas_id menjadi nullable; mata_kuliah_id dan master_kelas_id ditambahkan.
 * Baris KRS online tetap mengisi kelas_id; baris impor Feeder mengisi mata_kuliah_id (+ master_kelas_id).
 * Tidak ada unique baru pada (krs_id, mata_kuliah_id) karena satu MK dapat diambil pada kelas teori dan praktik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siakad_krs_detail')) {
            return;
        }

        Schema::table('siakad_krs_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_krs_detail', 'mata_kuliah_id')) {
                $table->foreignId('mata_kuliah_id')->nullable()->after('kelas_id')
                    ->constrained('siakad_mata_kuliah')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_krs_detail', 'master_kelas_id')) {
                $table->foreignId('master_kelas_id')->nullable()->after('mata_kuliah_id')
                    ->constrained('siakad_master_kelas')->nullOnDelete();
            }
        });

        Schema::table('siakad_krs_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('kelas_id')->nullable()->change();
        });

        // Isi mata_kuliah_id baris lama dari kelasnya agar pembaca cukup memakai satu kolom.
        if (Schema::hasTable('siakad_jadwal_kelas')) {
            DB::table('siakad_krs_detail')
                ->whereNull('mata_kuliah_id')
                ->whereNotNull('kelas_id')
                ->update([
                    'mata_kuliah_id' => DB::raw('(select mata_kuliah_id from siakad_jadwal_kelas where siakad_jadwal_kelas.id = siakad_krs_detail.kelas_id)'),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('siakad_krs_detail')) {
            return;
        }

        // Baris tanpa kelas tidak dapat dipertahankan bila kelas_id kembali wajib.
        DB::table('siakad_krs_detail')->whereNull('kelas_id')->delete();

        Schema::table('siakad_krs_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('kelas_id')->nullable(false)->change();
        });

        Schema::table('siakad_krs_detail', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_krs_detail', 'master_kelas_id')) {
                $table->dropConstrainedForeignId('master_kelas_id');
            }
            if (Schema::hasColumn('siakad_krs_detail', 'mata_kuliah_id')) {
                $table->dropConstrainedForeignId('mata_kuliah_id');
            }
        });
    }
};
