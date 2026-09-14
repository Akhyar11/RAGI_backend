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
        Schema::table('siakad_dosen_pengampu', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'penugasan_id')) {
                $table->foreignId('penugasan_id')->nullable()->after('dosen_id')->constrained('siakad_dosen_penugasan')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'sks_substansi_total')) {
                $table->decimal('sks_substansi_total', 4, 2)->default(0.00)->after('peran');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'rencana_minggu_pertemuan')) {
                $table->integer('rencana_minggu_pertemuan')->default(16)->after('sks_substansi_total');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'realisasi_minggu_pertemuan')) {
                $table->integer('realisasi_minggu_pertemuan')->default(16)->after('rencana_minggu_pertemuan');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'jenis_evaluasi_id')) {
                $table->integer('jenis_evaluasi_id')->default(1)->after('realisasi_minggu_pertemuan')->comment('1=Evaluasi Akademik Standar');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'id_feeder')) {
                $table->string('id_feeder', 100)->nullable()->after('jenis_evaluasi_id')->comment('id_aktivitas_mengajar dari Neo Feeder');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'sync_status')) {
                $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending')->after('id_feeder');
            }
            if (!Schema::hasColumn('siakad_dosen_pengampu', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siakad_dosen_pengampu', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_dosen_pengampu', 'penugasan_id')) {
                $table->dropForeign(['penugasan_id']);
                $table->dropColumn('penugasan_id');
            }
            $columnsToDrop = [
                'sks_substansi_total',
                'rencana_minggu_pertemuan',
                'realisasi_minggu_pertemuan',
                'jenis_evaluasi_id',
                'id_feeder',
                'sync_status',
                'last_synced_at',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('siakad_dosen_pengampu', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
