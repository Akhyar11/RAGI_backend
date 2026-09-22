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
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            $table->decimal('nominal_disetujui', 15, 2)->default(0)->nullable()->after('estimasi_biaya');
            $table->unsignedBigInteger('sikeu_pencairan_id')->nullable()->after('nominal_disetujui');
            $table->string('status_pencairan', 30)->default('tidak_perlu')->after('sikeu_pencairan_id'); // tidak_perlu, belum_cair, dicairkan

            $table->foreign('sikeu_pencairan_id')
                ->references('id')
                ->on('sikeu_pengajuan_pencairan_kas')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_surat_tugas', function (Blueprint $table) {
            $table->dropForeign(['sikeu_pencairan_id']);
            $table->dropColumn(['nominal_disetujui', 'sikeu_pencairan_id', 'status_pencairan']);
        });
    }
};
