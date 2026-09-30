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
        Schema::table('sinapra_pengajuan_pengadaan', function (Blueprint $table) {
            $table->foreignId('sikeu_pencairan_id')
                ->nullable()
                ->after('disetujui_oleh')
                ->constrained('sikeu_pengajuan_pencairan_kas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinapra_pengajuan_pengadaan', function (Blueprint $table) {
            $table->dropForeign(['sikeu_pencairan_id']);
            $table->dropColumn('sikeu_pencairan_id');
        });
    }
};
